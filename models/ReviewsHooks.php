<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

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
}
