<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ProductReview;
use Tests\Helpers\ShopDatabaseTestCase;

require_once __DIR__ . '/../models/ProductReview.php';

/**
 * The SQL behind reviews_waiting, run against real rows: unpublished and not deleted reviews wait, they are
 * grouped by product, and the list is oldest first.
 */
class ReviewsWaitingSqlTest extends ShopDatabaseTestCase
{
    private ProductReview $reviews;
    private string $runTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviews = new ProductReview($this->db);
        $this->runTag = 'rt' . \substr(\uniqid(), -6);

        $prefix = $this->db->tablePrefix();
        $tableExists = $this->db->prepare("SHOW TABLES LIKE '{$prefix}product_reviews'")->single() !== false;
        if (!$tableExists) $this->markTestSkipped('The Reviews table is not installed');
    }

    private function review(int $productId, string $suffix, int $rating = 5, bool $verified = false, ?string $submittedAt = null): int
    {
        $id = $this->reviews->create(
            ['name' => "Name {$suffix}", 'contact' => "{$this->runTag}-{$suffix}@example.com", 'product_id' => $productId, 'rating' => $rating, 'content' => 'Fine'],
            '203.0.113.1',
            false,
            $verified,
        );
        if ($submittedAt !== null) {
            $prefix = $this->db->tablePrefix();
            $this->db->prepare("UPDATE {$prefix}product_reviews SET submitted_at = :at WHERE id = :id", [':at' => $submittedAt, ':id' => $id])->execute();
        }

        return $id;
    }

    /** @return array<int, string> contacts of this run's reviews in the waiting list, oldest first */
    private function listedContacts(): array
    {
        $rows = $this->reviews->waitingList(1000);
        $contacts = \array_column($rows, 'contact');

        return \array_values(\array_filter($contacts, fn(string $contact): bool => \str_starts_with($contact, $this->runTag)));
    }

    public function test_only_unpublished_and_not_deleted_reviews_wait_oldest_first(): void
    {
        $product = $this->createTestProduct();
        $this->review($product, 'newer', submittedAt: '2026-10-09 10:00:00');
        $this->review($product, 'older', submittedAt: '2026-10-01 10:00:00');
        $this->reviews->setPublished($this->review($product, 'published'), true);
        $this->reviews->softDelete($this->review($product, 'deleted'));

        $this->assertSame(["{$this->runTag}-older@example.com", "{$this->runTag}-newer@example.com"], $this->listedContacts());
    }

    public function test_the_totals_move_by_exactly_the_waiting_and_published_reviews_added(): void
    {
        $product = $this->createTestProduct();
        $before = $this->reviews->totals();

        $this->review($product, 'w1', submittedAt: '2000-01-01 10:00:00');
        $this->review($product, 'w2');
        $this->reviews->setPublished($this->review($product, 'p1'), true);
        $this->reviews->softDelete($this->review($product, 'gone'));

        $after = $this->reviews->totals();

        $this->assertSame(2, (int) $after['waiting'] - (int) $before['waiting']);
        $this->assertSame(1, (int) $after['published'] - (int) $before['published']);
        $this->assertSame('2000-01-01', \substr((string) $after['oldest_waiting'], 0, 10));
    }

    public function test_waiting_reviews_are_grouped_by_product_with_count_average_oldest_and_verified(): void
    {
        $busy = $this->createTestProduct();
        $quiet = $this->createTestProduct();
        $this->review($busy, 'a', 5, true, '2026-10-03 10:00:00');
        $this->review($busy, 'b', 2, false, '2026-10-01 10:00:00');
        $this->review($quiet, 'c', 4);
        $this->reviews->softDelete($this->review($busy, 'deleted', 1));
        $this->reviews->setPublished($this->review($quiet, 'published', 1), true);

        $byProduct = [];
        foreach ($this->reviews->waitingByProduct() as $row) {
            $byProduct[(int) $row['product_id']] = $row;
        }

        $this->assertSame(2, (int) $byProduct[$busy]['waiting']);
        $this->assertEqualsWithDelta(3.5, (float) $byProduct[$busy]['average_rating'], 0.001);
        $this->assertSame('2026-10-01', \substr((string) $byProduct[$busy]['oldest'], 0, 10));
        $this->assertSame(1, (int) $byProduct[$busy]['verified']);
        $this->assertSame(1, (int) $byProduct[$quiet]['waiting']);
        $this->assertEqualsWithDelta(4.0, (float) $byProduct[$quiet]['average_rating'], 0.001);
    }
}
