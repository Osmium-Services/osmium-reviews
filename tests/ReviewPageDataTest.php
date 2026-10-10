<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewPageData;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewText.php';
require_once __DIR__ . '/../models/ReviewPageData.php';

/**
 * What the product page gets from the reviews service: the published reviews, a count, an average and a
 * one-time notice about the visitor's own submission.
 */
final class ReviewPageDataTest extends TestCase
{
    private function row(int $id, int $rating, string $content = 'Fine'): array
    {
        return ['id' => $id, 'name' => 'Sam', 'contact' => 'sam@example.com', 'rating' => $rating, 'content' => $content, 'created_at' => '2026-10-10 12:00:00', 'verified_purchase' => 0];
    }

    public function test_it_returns_the_reviews_a_count_and_an_average(): void
    {
        $data = ReviewPageData::build(rows: [$this->row(1, 5), $this->row(2, 4)], notice: null);

        $this->assertCount(2, $data['reviews']);
        $this->assertSame(2, $data['reviewCount']);
        $this->assertSame(4.5, $data['ratingAverage']);
    }

    public function test_each_review_says_whether_it_is_a_verified_purchase(): void
    {
        $verified = $this->row(1, 5);
        $verified['verified_purchase'] = 1;

        $data = ReviewPageData::build(rows: [$verified, $this->row(2, 3)], notice: null);

        $this->assertTrue($data['reviews'][0]['verified']);
        $this->assertFalse($data['reviews'][1]['verified']);
    }

    public function test_a_reviewers_email_is_never_given_to_the_theme(): void
    {
        $data = ReviewPageData::build(rows: [$this->row(1, 5)], notice: null);

        $this->assertArrayNotHasKey('contact', $data['reviews'][0]);
        $this->assertStringNotContainsString('sam@example.com', \json_encode($data));
    }

    public function test_the_text_is_given_as_safe_html(): void
    {
        $data = ReviewPageData::build(rows: [$this->row(1, 5, '<b>hi</b>')], notice: null);

        $this->assertSame('<p>&lt;b&gt;hi&lt;/b&gt;</p>', $data['reviews'][0]['content']);
    }

    public function test_with_no_reviews_there_is_no_average(): void
    {
        $data = ReviewPageData::build(rows: [], notice: null);

        $this->assertSame([], $data['reviews']);
        $this->assertSame(0, $data['reviewCount']);
        $this->assertNull($data['ratingAverage']);
    }

    public function test_the_notice_is_passed_through(): void
    {
        $data = ReviewPageData::build(rows: [], notice: ['status' => 'failed', 'message' => 'Nope']);

        $this->assertSame(['status' => 'failed', 'message' => 'Nope'], $data['reviewNotice']);
    }

    public function test_a_notice_is_read_once_from_the_session_and_then_gone(): void
    {
        $session = ['review_notice' => ['status' => 'sent', 'message' => 'Thanks']];

        $notice = ReviewPageData::takeNotice($session);

        $this->assertSame('sent', $notice['status']);
        $this->assertArrayNotHasKey('review_notice', $session);
        $this->assertNull(ReviewPageData::takeNotice($session));
    }
}
