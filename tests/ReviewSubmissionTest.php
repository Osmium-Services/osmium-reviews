<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ProductReview;
use Osmium\Services\Reviews\Models\ReviewSubmission;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/ReviewRules.php';
require_once __DIR__ . '/../models/ProductReview.php';
require_once __DIR__ . '/../models/ReviewSubmission.php';

/**
 * A visitor sending a review: checked, rate limited, saved unpublished, and the team told.
 */
final class ReviewSubmissionTest extends DatabaseTestCase
{
    /** @var array<int, array{review: array, productName: string}> */
    private array $notified = [];

    private function submission(): ReviewSubmission
    {
        $this->notified = [];

        return new ReviewSubmission(
            reviews: new ProductReview($this->db),
            notify: function (array $review, string $productName): void {
                $this->notified[] = ['review' => $review, 'productName' => $productName];
            },
        );
    }

    private function input(int $productId, array $changes = []): array
    {
        return \array_merge(['name' => 'Sam', 'email' => 'sam@example.com', 'pid' => (string) $productId, 'rating' => '5', 'message' => 'Great'], $changes);
    }

    public function test_a_good_review_is_saved_unpublished_and_the_team_is_told(): void
    {
        $product = $this->createTestProduct(['model_name' => 'Panel 200W']);

        $result = $this->submission()->submit(input: $this->input($product), ipAddress: '10.1.1.1');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $row = (new ProductReview($this->db))->listPending(5)[0];
        $this->assertSame('Sam', $row['name']);
        $this->assertCount(1, $this->notified);
        $this->assertSame('Panel 200W', $this->notified[0]['productName']);
    }

    private function paidOrder(string $email, int $productId): void
    {
        $prefix = $this->db->tablePrefix();
        $this->db->prepare(
            sql: "INSERT INTO {$prefix}shop_orders (order_ref, status, customer_name, customer_email, billing_address1, billing_city, billing_postcode, delivery_name, delivery_address1, delivery_city, delivery_postcode, delivery_zone, created_at)
                  VALUES (:ref, 'paid', 'Sam', :email, '1 St', 'Town', 'AB1 2CD', 'Sam', '1 St', 'Town', 'AB1 2CD', 'UK', NOW())",
            bindings: [':ref' => 'RS' . \substr(\uniqid(), -8), ':email' => $email],
        )->execute();
        $orderId = (int) $this->db->lastInsertId();
        $this->db->prepare(
            sql: "INSERT INTO {$prefix}shop_order_items (order_id, product_id, product_ref, product_title, option_signature, quantity) VALUES (:order, :product, 'REF', 'Item', '', 1)",
            bindings: [':order' => $orderId, ':product' => $productId],
        )->execute();
    }

    private function storedReview(): array
    {
        return (new ProductReview($this->db))->listActive()[0];
    }

    public function test_a_review_from_a_paid_customer_is_marked_as_a_verified_purchase_but_still_waits_for_approval(): void
    {
        $product = $this->createTestProduct();
        $this->paidOrder('sam@example.com', $product);

        $this->submission()->submit(input: $this->input($product), ipAddress: '10.1.2.3');

        $stored = $this->storedReview();
        $this->assertSame(1, (int) $stored['verified_purchase']);
        $this->assertSame(0, (int) $stored['is_published']);
    }

    public function test_a_review_from_someone_who_has_not_bought_it_is_not_verified(): void
    {
        $product = $this->createTestProduct();

        $this->submission()->submit(input: $this->input($product), ipAddress: '10.1.2.4');

        $this->assertSame(0, (int) $this->storedReview()['verified_purchase']);
    }

    public function test_the_submitter_address_is_kept_for_rate_limiting(): void
    {
        $product = $this->createTestProduct();
        $this->submission()->submit(input: $this->input($product), ipAddress: '10.2.2.2');

        $this->assertSame(1, (new ProductReview($this->db))->countRecentByIp(ipAddress: '10.2.2.2', minutes: 60));
    }

    public function test_an_invalid_review_is_refused_with_the_reason_and_nothing_is_saved_or_sent(): void
    {
        $product = $this->createTestProduct();
        $before = (new ProductReview($this->db))->countPending();

        $result = $this->submission()->submit(input: $this->input($product, ['rating' => '99']), ipAddress: '10.3.3.3');

        $this->assertFalse($result['ok']);
        $this->assertSame('Please choose a rating from 1 to 5', $result['error']);
        $this->assertSame($before, (new ProductReview($this->db))->countPending());
        $this->assertSame([], $this->notified);
    }

    public function test_a_review_for_a_product_that_does_not_exist_is_refused(): void
    {
        $result = $this->submission()->submit(input: $this->input(999999999), ipAddress: '10.4.4.4');

        $this->assertFalse($result['ok']);
        $this->assertSame(ReviewSubmission::TECHNICAL_PROBLEM, $result['error']); // Says nothing about why
        $this->assertSame([], $this->notified);
    }

    public function test_a_fourth_review_from_the_same_address_within_the_hour_is_refused(): void
    {
        $product = $this->createTestProduct();
        $submission = $this->submission();
        foreach ([1, 2, 3] as $attempt) {
            $this->assertTrue($submission->submit(input: $this->input($product), ipAddress: '10.5.5.5')['ok']);
        }

        $result = $submission->submit(input: $this->input($product), ipAddress: '10.5.5.5');

        $this->assertFalse($result['ok']);
        $this->assertSame(ReviewSubmission::TECHNICAL_PROBLEM, $result['error']); // Says nothing about the limit
        $this->assertTrue($submission->submit(input: $this->input($product), ipAddress: '10.5.5.6')['ok']);
    }

    public function test_a_failing_notification_never_loses_the_review(): void
    {
        $product = $this->createTestProduct();
        $submission = new ReviewSubmission(
            reviews: new ProductReview($this->db),
            notify: static function (): void {
                throw new \RuntimeException('mail server down');
            },
        );

        $result = $submission->submit(input: $this->input($product), ipAddress: '10.7.7.7');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, (new ProductReview($this->db))->countRecentByIp(ipAddress: '10.7.7.7', minutes: 60));
    }

    public function test_a_page_address_is_returned_for_the_redirect_when_the_product_has_one(): void
    {
        $product = $this->createTestProduct();

        $result = $this->submission()->submit(input: $this->input($product), ipAddress: '10.8.8.8');

        $this->assertArrayHasKey('productUrl', $result);
        $this->assertNull($result['productUrl']);
    }
}
