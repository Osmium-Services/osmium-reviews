<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ProductReview;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/ProductReview.php';

/**
 * The stored reviews: what the public sees, what waits for approval, and the bin.
 */
final class ProductReviewTest extends DatabaseTestCase
{
    private ProductReview $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviews = new ProductReview($this->db);
    }

    private function add(int $productId, array $changes = [], bool $published = false, ?string $ip = '10.0.0.1'): int
    {
        $review = \array_merge(['name' => 'Sam', 'contact' => 'sam@example.com', 'product_id' => $productId, 'rating' => 4, 'content' => 'Good'], $changes);

        return $this->reviews->create(review: $review, ipAddress: $ip, published: $published);
    }

    public function test_a_new_review_waits_for_approval(): void
    {
        $id = $this->add($this->createTestProduct());

        $this->assertSame(0, (int) $this->reviews->get($id)['is_published']);
        $this->assertSame(1, $this->reviews->countPending());
    }

    public function test_the_public_sees_only_published_reviews_of_that_product(): void
    {
        $product = $this->createTestProduct();
        $other = $this->createTestProduct();
        $shown = $this->add($product, ['name' => 'Shown'], published: true);
        $this->add($product, ['name' => 'Pending']);
        $this->add($other, ['name' => 'Elsewhere'], published: true);

        $rows = $this->reviews->publishedForProduct($product);

        $this->assertSame([$shown], \array_map(fn(array $row): int => (int) $row['id'], $rows));
    }

    public function test_deleted_reviews_are_not_shown_or_counted_as_pending(): void
    {
        $product = $this->createTestProduct();
        $published = $this->add($product, published: true);
        $pending = $this->add($product);
        $this->reviews->softDelete($published);
        $this->reviews->softDelete($pending);

        $this->assertSame([], $this->reviews->publishedForProduct($product));
        $this->assertSame(0, $this->reviews->countPending());
    }

    public function test_a_review_can_be_published_and_unpublished(): void
    {
        $id = $this->add($this->createTestProduct());

        $this->reviews->setPublished(id: $id, published: true);
        $this->assertSame(1, (int) $this->reviews->get($id)['is_published']);

        $this->reviews->setPublished(id: $id, published: false);
        $this->assertSame(0, (int) $this->reviews->get($id)['is_published']);
    }

    public function test_deleting_moves_a_review_to_the_bin_and_restore_brings_it_back(): void
    {
        $id = $this->add($this->createTestProduct());

        $this->reviews->softDelete($id);
        $inBin = \array_column($this->reviews->listDeleted(), 'id');
        $this->assertContains((string) $id, \array_map('strval', $inBin));
        $this->assertNotContains((string) $id, \array_map('strval', \array_column($this->reviews->listActive(), 'id')));

        $this->reviews->restore($id);
        $this->assertContains((string) $id, \array_map('strval', \array_column($this->reviews->listActive(), 'id')));
    }

    public function test_hard_delete_removes_one_review_and_purge_removes_only_binned_ones(): void
    {
        $product = $this->createTestProduct();
        $gone = $this->add($product);
        $binned = $this->add($product);
        $kept = $this->add($product);
        $this->reviews->hardDelete($gone);
        $this->reviews->softDelete($binned);

        $purged = $this->reviews->purgeDeleted();

        $this->assertGreaterThanOrEqual(1, $purged);
        $this->assertNull($this->reviews->get($gone));
        $this->assertNull($this->reviews->get($binned));
        $this->assertNotNull($this->reviews->get($kept));
    }

    public function test_a_review_can_be_edited(): void
    {
        $product = $this->createTestProduct();
        $id = $this->add($product);

        $this->reviews->update(id: $id, fields: ['product_id' => $product, 'name' => 'Pat', 'contact' => 'pat@example.com', 'rating' => 2, 'content' => 'Meh', 'is_published' => 1]);

        $row = $this->reviews->get($id);
        $this->assertSame(['Pat', 'pat@example.com', 2, 'Meh', 1], [$row['name'], $row['contact'], (int) $row['rating'], $row['content'], (int) $row['is_published']]);
    }

    public function test_pending_reviews_list_newest_first_and_respect_the_limit(): void
    {
        $product = $this->createTestProduct();
        $this->add($product, ['name' => 'First']);
        $this->add($product, ['name' => 'Second']);

        $rows = $this->reviews->listPending(1);

        $this->assertCount(1, $rows);
    }

    public function test_recent_reviews_from_one_address_are_counted_within_the_window(): void
    {
        $product = $this->createTestProduct();
        $this->add($product, ip: '10.9.9.9');
        $this->add($product, ip: '10.9.9.9');
        $this->add($product, ip: '10.8.8.8');

        $this->assertSame(2, $this->reviews->countRecentByIp(ipAddress: '10.9.9.9', minutes: 60));
        $this->assertSame(0, $this->reviews->countRecentByIp(ipAddress: '10.7.7.7', minutes: 60));
    }

    public function test_a_review_older_than_the_window_is_not_counted(): void
    {
        $id = $this->add($this->createTestProduct(), ip: '10.6.6.6');
        $prefix = $this->db->tablePrefix();
        $this->db->prepare(sql: "UPDATE {$prefix}product_reviews SET submitted_at = NOW() - INTERVAL 2 HOUR WHERE id = :id", bindings: [':id' => $id])->execute();

        $this->assertSame(0, $this->reviews->countRecentByIp(ipAddress: '10.6.6.6', minutes: 60));
    }

    public function test_a_live_product_exists_and_a_deleted_or_unknown_one_does_not(): void
    {
        $live = $this->createTestProduct();
        $deleted = $this->createTestProduct();
        $prefix = $this->db->tablePrefix();
        $this->db->prepare(sql: "UPDATE {$prefix}products SET deleted_at = NOW() WHERE id = :id", bindings: [':id' => $deleted])->execute();

        $this->assertTrue($this->reviews->productExists($live));
        $this->assertFalse($this->reviews->productExists($deleted));
        $this->assertFalse($this->reviews->productExists(999999999));
    }

    public function test_a_product_without_a_page_has_no_page_url(): void
    {
        $this->assertNull($this->reviews->productPageUrl($this->createTestProduct()));
    }
}
