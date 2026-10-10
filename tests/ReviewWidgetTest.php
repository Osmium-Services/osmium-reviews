<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewWidget;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewWidget.php';

/**
 * The dashboard card for reviews waiting for approval.
 */
final class ReviewWidgetTest extends TestCase
{
    public function test_it_counts_the_pending_reviews_and_lists_them_with_a_link_to_each(): void
    {
        $rows = [['id' => 7, 'name' => 'Sam', 'rating' => 4, 'product_title' => 'Panel']];

        $widget = ReviewWidget::build(pendingCount: 3, rows: $rows);

        $this->assertSame(3, $widget['count']);
        $this->assertSame('Panel', $widget['lines'][0]['text']);
        $this->assertSame('Sam - 4/5 stars', $widget['lines'][0]['sub']);
        $this->assertSame('reviews/edit/?id=7', $widget['lines'][0]['route']);
    }

    public function test_with_nothing_pending_the_card_says_so(): void
    {
        $widget = ReviewWidget::build(pendingCount: 0, rows: []);

        $this->assertSame(0, $widget['count']);
        $this->assertSame([], $widget['lines']);
        $this->assertSame('No pending reviews.', $widget['emptyText']);
    }

    public function test_a_review_of_a_deleted_product_still_has_a_line(): void
    {
        $widget = ReviewWidget::build(pendingCount: 1, rows: [['id' => 2, 'name' => 'Pat', 'rating' => 1, 'product_title' => null]]);

        $this->assertSame('Unknown product', $widget['lines'][0]['text']);
    }

    public function test_the_card_is_for_admins_and_editors_and_links_to_the_list(): void
    {
        $widget = ReviewWidget::build(pendingCount: 0, rows: []);

        $this->assertSame(['admin', 'editor'], $widget['roles']);
        $this->assertSame('reviews/', $widget['link']['route']);
    }
}
