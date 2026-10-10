<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * A visitor sending a review: the fields are checked, the product must exist, one address may send a few an
 * hour, and the review is saved unpublished. The team is told afterwards, and a failure to tell them never
 * loses the review.
 */
class ReviewSubmission
{
    private const MAX_PER_HOUR = 3;
    private const WINDOW_MINUTES = 60;

    /** @param callable(array, string): void $notify Called with the saved review and its product's name */
    public function __construct(private ProductReview $reviews, private $notify) {}

    /**
     * @param array<string, mixed> $input The submitted fields: name, email, pid, rating, message
     * @return array{ok: bool, error: ?string, productUrl: ?string}
     */
    public function submit(array $input, ?string $ipAddress): array
    {
        $checked = ReviewRules::check($input);
        $invalid = $checked['error'] !== null;
        if ($invalid) return $this->refused($checked['error']);

        $review = $checked['review'];
        $productId = $review['product_id'];

        $productMissing = !$this->reviews->productExists($productId);
        if ($productMissing) return $this->refused('That product could not be found');

        $tooMany = $this->tooManyRecently($ipAddress);
        if ($tooMany) return $this->refused('You have sent several reviews recently. Please try again later');

        $verified = $this->reviews->isVerifiedPurchase(email: $review['contact'], productId: $productId);
        $this->reviews->create(review: $review, ipAddress: $ipAddress, verifiedPurchase: $verified);
        $this->tellTheTeam(review: $review);

        return ['ok' => true, 'error' => null, 'productUrl' => $this->reviews->productPageUrl($productId)];
    }

    private function refused(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'productUrl' => null];
    }

    private function tooManyRecently(?string $ipAddress): bool
    {
        $unknownAddress = $ipAddress === null || $ipAddress === '';
        if ($unknownAddress) return false;

        $recent = $this->reviews->countRecentByIp(ipAddress: $ipAddress, minutes: self::WINDOW_MINUTES);

        return $recent >= self::MAX_PER_HOUR;
    }

    private function tellTheTeam(array $review): void
    {
        try {
            ($this->notify)($review, $this->reviews->productName($review['product_id']));
        } catch (\Throwable $e) {
            \error_log('Review notification failed: ' . $e->getMessage()); // The review is already saved
        }
    }
}
