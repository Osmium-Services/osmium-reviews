<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

use Osmium\Core\Library\BrandLogo;
use Osmium\Core\Library\MailerFactory;

/**
 * The handlers for the core hooks this service listens to.
 */
class ReviewsHooks
{
    private const DASHBOARD_LINES = 5;

    /**
     * product.page.data: the product's published reviews, their count and average, and the visitor's one-time notice.
     *
     * @param array{productId: int, dataSource: object} $payload
     */
    public static function productPageData(array $payload): array
    {
        $rows = (new ProductReview($payload['dataSource']))->publishedForProduct((int) $payload['productId']);

        $hasSession = isset($_SESSION) && \is_array($_SESSION);
        $notice = $hasSession ? ReviewPageData::takeNotice($_SESSION) : null;

        return ReviewPageData::build(rows: $rows, notice: $notice);
    }

    /**
     * dashboard.widgets: the "Pending reviews" card.
     *
     * @param array{osmium: object, role: string} $payload
     */
    public static function dashboard(array $payload): array
    {
        $reviews = new ProductReview($payload['osmium']->dataSource);

        return ReviewWidget::build(pendingCount: $reviews->countPending(), rows: $reviews->listPending(self::DASHBOARD_LINES));
    }

    /**
     * email.samples: the new-review notification, for the admin's Email test tool.
     *
     * @return array{id: string, label: string, handler: string}
     */
    public static function emailSamples(array $payload): array
    {
        return ['id' => 'reviews.new_review', 'label' => 'New review notification (sample data)', 'handler' => self::class . '::sendSampleEmail'];
    }

    /**
     * Sends the real new-review email, built from made-up data, to the address given and nobody else.
     *
     * @param array{toEmail: string, osmium: object} $payload
     * @return array{success: bool, error?: string}
     */
    public static function sendSampleEmail(array $payload): array
    {
        $sample = ReviewMail::sample();
        $osmium = $payload['osmium'];

        return MailerFactory::make($osmium->config)->send(
            recipients: ReviewMail::recipients([$payload['toEmail']]),
            subject: ReviewMail::SUBJECT,
            htmlBody: ReviewMail::html(review: $sample['review'], productName: $sample['productName'], brand: self::brand($osmium->config)),
        );
    }

    /**
     * The site's name and email logo, for the notification's header.
     *
     * @return array{siteName: string, logoUrl: string}
     */
    public static function brand(object $config): array
    {
        return ['siteName' => (string) $config->site->name, 'logoUrl' => BrandLogo::emailUrl($config->site->FQDN)];
    }
}
