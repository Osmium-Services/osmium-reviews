<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewRules;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewRules.php';

/**
 * What a visitor may submit as a review: a name, an email, a product, a rating from 1 to 5 and an optional message.
 */
final class ReviewRulesTest extends TestCase
{
    private function valid(array $changes = []): array
    {
        return \array_merge(['name' => 'Sam', 'email' => 'sam@example.com', 'pid' => '12', 'rating' => '4', 'message' => 'Works well'], $changes);
    }

    private function errorFor(array $changes): ?string
    {
        return ReviewRules::check($this->valid($changes))['error'];
    }

    public function test_a_complete_review_is_accepted_and_cleaned(): void
    {
        $result = ReviewRules::check($this->valid());

        $this->assertNull($result['error']);
        $this->assertSame(['name' => 'Sam', 'contact' => 'sam@example.com', 'product_id' => 12, 'rating' => 4, 'content' => 'Works well'], $result['review']);
    }

    public function test_the_name_and_message_are_trimmed(): void
    {
        $result = ReviewRules::check($this->valid(['name' => '  Sam  ', 'message' => "  Nice\n"]));

        $this->assertSame('Sam', $result['review']['name']);
        $this->assertSame('Nice', $result['review']['content']);
    }

    public function test_the_message_is_optional(): void
    {
        $result = ReviewRules::check($this->valid(['message' => '   ']));

        $this->assertNull($result['error']);
        $this->assertSame('', $result['review']['content']);
    }

    public function test_a_name_is_required(): void
    {
        $this->assertNotNull($this->errorFor(['name' => '   ']));
    }

    public function test_a_name_over_fifty_characters_is_refused_and_fifty_is_kept(): void
    {
        $this->assertNotNull($this->errorFor(['name' => \str_repeat('a', 51)]));
        $this->assertNull($this->errorFor(['name' => \str_repeat('a', 50)]));
    }

    public function test_an_email_must_be_valid(): void
    {
        $this->assertNotNull($this->errorFor(['email' => 'not-an-email']));
        $this->assertNotNull($this->errorFor(['email' => '']));
    }

    public function test_an_email_over_255_characters_is_refused(): void
    {
        $this->assertNotNull($this->errorFor(['email' => \str_repeat('a', 250) . '@b.co']));
    }

    public function test_the_rating_must_be_a_whole_number_from_one_to_five(): void
    {
        foreach (['0', '6', '99', '-1', '3.5', 'abc', ''] as $rating) {
            $this->assertNotNull($this->errorFor(['rating' => $rating]), "rating '{$rating}' should be refused");
        }
        foreach (['1', '2', '3', '4', '5'] as $rating) {
            $this->assertNull($this->errorFor(['rating' => $rating]), "rating '{$rating}' should be accepted");
        }
    }

    public function test_the_product_must_be_a_positive_whole_number(): void
    {
        foreach (['0', '-3', 'x', '', '1.5'] as $pid) {
            $this->assertNotNull($this->errorFor(['pid' => $pid]), "pid '{$pid}' should be refused");
        }
    }

    public function test_a_message_over_2000_characters_is_refused_and_2000_is_kept(): void
    {
        $this->assertNotNull($this->errorFor(['message' => \str_repeat('x', 2001)]));
        $this->assertNull($this->errorFor(['message' => \str_repeat('x', 2000)]));
    }

    public function test_missing_fields_are_refused_not_fatal(): void
    {
        $this->assertNotNull(ReviewRules::check([])['error']);
    }
}
