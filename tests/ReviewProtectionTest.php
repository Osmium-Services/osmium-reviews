<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewProtection;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewProtection.php';

/**
 * Bot protection is optional and provider-agnostic: only an explicit failure stops a review.
 */
final class ReviewProtectionTest extends TestCase
{
    public function testNoProtectionInstalledLetsTheReviewThrough(): void
    {
        $this->assertTrue(ReviewProtection::passed([]));
    }

    public function testAPassedCheckLetsTheReviewThrough(): void
    {
        $this->assertTrue(ReviewProtection::passed([true]));
    }

    public function testAFailedCheckStopsTheReview(): void
    {
        $this->assertFalse(ReviewProtection::passed([false]));
    }

    public function testOneFailureAmongPassesStopsTheReview(): void
    {
        $this->assertFalse(ReviewProtection::passed([true, false]));
    }
}
