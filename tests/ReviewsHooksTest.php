<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ProductReview;
use Osmium\Services\Reviews\Models\ReviewsHooks;
use Tests\Helpers\ShopDatabaseTestCase;

require_once __DIR__ . '/../models/ReviewText.php';
require_once __DIR__ . '/../models/ReviewPageData.php';
require_once __DIR__ . '/../models/ReviewWidget.php';
require_once __DIR__ . '/../models/ProductReview.php';
require_once __DIR__ . '/../models/ReviewsHooks.php';

/**
 * The core hooks wired to real stored reviews.
 */
final class ReviewsHooksTest extends ShopDatabaseTestCase
{
    private function review(int $productId, int $rating, bool $published): void
    {
        (new ProductReview($this->db))->create(
            review: ['name' => 'Sam', 'contact' => 'sam@example.com', 'product_id' => $productId, 'rating' => $rating, 'content' => 'Fine'],
            ipAddress: null,
            published: $published,
        );
    }

    public function test_the_product_page_gets_only_that_products_published_reviews(): void
    {
        $product = $this->createTestProduct();
        $this->review($product, 5, true);
        $this->review($product, 3, true);
        $this->review($product, 1, false);
        $this->review($this->createTestProduct(), 2, true);

        $data = ReviewsHooks::productPageData(['productId' => $product, 'dataSource' => $this->db]);

        $this->assertSame(2, $data['reviewCount']);
        $this->assertSame(4.0, $data['ratingAverage']);
    }

    public function test_a_verified_purchase_review_reaches_the_product_page_as_verified(): void
    {
        $product = $this->createTestProduct();
        (new ProductReview($this->db))->create(
            review: ['name' => 'Sam', 'contact' => 'sam@example.com', 'product_id' => $product, 'rating' => 5, 'content' => 'Fine'],
            ipAddress: null,
            published: true,
            verifiedPurchase: true,
        );

        $data = ReviewsHooks::productPageData(['productId' => $product, 'dataSource' => $this->db]);

        $this->assertTrue($data['reviews'][0]['verified']);
    }

    public function test_the_dashboard_card_counts_pending_reviews(): void
    {
        $product = $this->createTestProduct();
        $before = (new ProductReview($this->db))->countPending();
        $this->review($product, 4, false);
        $this->review($product, 4, true);

        $osmium = (object) ['dataSource' => $this->db];
        $widget = ReviewsHooks::dashboard(['osmium' => $osmium, 'role' => 'admin']);

        $this->assertSame($before + 1, $widget['count']);
    }

    public function test_the_new_review_email_is_offered_as_a_sample_to_the_email_test_tool(): void
    {
        $sample = ReviewsHooks::emailSamples(['osmium' => (object) []]);

        $this->assertSame('reviews.new_review', $sample['id']);
        $this->assertSame('Osmium Reviews', $sample['group']);
        $this->assertTrue(\is_callable(\explode('::', $sample['handler'])));
    }

    public function test_the_brand_is_the_sites_name_and_email_logo(): void
    {
        $config = (object) ['site' => (object) ['name' => 'Pest', 'FQDN' => 'https://example.com']];

        $brand = ReviewsHooks::brand($config);

        $this->assertSame('Pest', $brand['siteName']);
        $this->assertArrayHasKey('logoUrl', $brand);
    }
}
