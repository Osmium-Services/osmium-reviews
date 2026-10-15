<?php

use Osmium\Core\Library\MailerFactory;
use Osmium\Core\Library\ServiceHooks;
use Osmium\Core\Library\ServiceLoader;
use Osmium\Core\Models\ServiceModel;
use Osmium\Services\Reviews\Models\ProductReview;
use Osmium\Services\Reviews\Models\ReviewMail;
use Osmium\Services\Reviews\Models\ReviewPageData;
use Osmium\Services\Reviews\Models\ReviewProtection;
use Osmium\Services\Reviews\Models\ReviewsConfig;
use Osmium\Services\Reviews\Models\ReviewsHooks;
use Osmium\Services\Reviews\Models\ReviewSubmission;

/**
 * Review submission endpoint (POST /api/reviews). Fields: name, email, pid, rating, message, plus the
 * form's token and the zip honeypot, and the answer of any bot protection installed (form.verify). The visitor is always sent back to the product page (or the shop),
 * with a one-time reviewNotice for the theme to show.
 */

$requestMethod = $this->osmium->superGlobal->get(type: 'SERVER', name: 'REQUEST_METHOD');
$notPost = $requestMethod !== 'POST';
if ($notPost) {
    $this->osmium->header->redirect(targetURL: '/shop/', statusCode: 302);
}

$sg = $this->osmium->superGlobal;
$notice = fn(string $message) => ReviewPageData::rememberNotice(session: $_SESSION, status: 'failed', message: $message);
$back = fn(?string $url) => $this->osmium->header->redirect(targetURL: $url === null ? '/shop/' : '/' . \trim($url, '/') . '/', statusCode: 302);
$input = [
    'name' => $sg->get(type: 'POST', name: 'name'),
    'email' => $sg->get(type: 'POST', name: 'email'),
    'pid' => $sg->get(type: 'POST', name: 'pid'),
    'rating' => $sg->get(type: 'POST', name: 'rating'),
    'message' => $sg->get(type: 'POST', name: 'message'),
];

$reviews = new ProductReview($this->osmium->dataSource);
$pid = (int) $input['pid'];
$productUrl = $pid > 0 ? $reviews->productPageUrl($pid) : null;

$honeyPot = $sg->get(type: 'POST', name: 'zip');
$botDetected = !empty($honeyPot);

$submittedToken = $sg->get(type: 'POST', name: 'token');
$sessionToken = $sg->get(type: 'SESSION', name: 'token');
$tokenIsValid = !empty($submittedToken)
    && !empty($sessionToken)
    && \hash_equals(known_string: $sessionToken, user_string: $submittedToken);

$tokenIssuedAt = $sg->get(type: 'SESSION', name: 'token_issued_at');
$secondsSinceTokenIssued = $tokenIssuedAt ? (\time() - (int) $tokenIssuedAt) : 0;
$submittedTooFast = $secondsSinceTokenIssued < 4;

$sg->delete(type: 'SESSION', name: 'token'); // One token, one submission
$sg->delete(type: 'SESSION', name: 'token_issued_at');

// Any installed bot protection answers the form.verify hook; this service never names one
$protectionAnswers = (new ServiceHooks(new ServiceLoader(new ServiceModel($this->osmium->dataSource))))
    ->collect('form.verify', ['form' => 'reviews', 'post' => $_POST, 'dataSource' => $this->osmium->dataSource]);
$protectionFailed = !ReviewProtection::passed($protectionAnswers);

$looksLikeABot = $botDetected || !$tokenIsValid || $submittedTooFast || $protectionFailed;
if ($looksLikeABot) {
    $notice(ReviewSubmission::TECHNICAL_PROBLEM); // Never say which check failed
    $back($productUrl);
}

$ipAddress = $this->osmium->ipAddress->getVisitorIP($this->osmium->config->site->trustProxyHeaders ?? false); // A visitor-supplied header must not decide who is rate limited

$notify = function (array $review, string $productName): void {
    $recipients = ReviewMail::recipients(ReviewsConfig::get()->recipients);
    $nobodyToTell = $recipients === [];
    if ($nobodyToTell) return;

    $result = MailerFactory::make($this->osmium->config)->send(
        recipients: $recipients,
        subject: ReviewMail::SUBJECT,
        htmlBody: ReviewMail::html(review: $review, productName: $productName, brand: ReviewsHooks::brand($this->osmium->config)),
    );
    if (!$result['success']) \error_log('Review email failed: ' . $result['error']);
};

$result = (new ReviewSubmission(reviews: $reviews, notify: $notify))->submit(input: $input, ipAddress: $ipAddress);

if ($result['ok']) {
    ReviewPageData::rememberNotice(session: $_SESSION, status: 'sent', message: 'Thank you. Your review will appear once it has been approved');
    $back($result['productUrl']);
}

$notice($result['error']);
$back($productUrl);
