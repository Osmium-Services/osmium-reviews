<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewMail;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewMail.php';

/**
 * The email telling the team a review has come in.
 */
final class ReviewMailTest extends TestCase
{
    private function review(): array
    {
        return ['name' => 'Sam <b>', 'contact' => 'sam@example.com', 'product_id' => 4, 'rating' => 4, 'content' => "Line one\nLine two"];
    }

    public function test_the_text_names_the_product_reviewer_rating_and_message(): void
    {
        $text = ReviewMail::text(review: $this->review(), productName: 'Panel 200W');

        $this->assertStringContainsString('Panel 200W', $text);
        $this->assertStringContainsString('Sam <b>', $text);
        $this->assertStringContainsString('sam@example.com', $text);
        $this->assertStringContainsString('4 out of 5', $text);
        $this->assertStringContainsString("Line one\nLine two", $text);
    }

    public function test_recipients_are_shaped_for_the_mailer(): void
    {
        $recipients = ReviewMail::recipients(['a@example.com', 'b@example.com']);

        $this->assertSame([['email' => 'a@example.com', 'name' => 'a@example.com'], ['email' => 'b@example.com', 'name' => 'b@example.com']], $recipients);
    }

    public function test_the_html_body_escapes_the_text(): void
    {
        $html = ReviewMail::html(review: $this->review(), productName: 'Panel');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function test_the_html_body_is_styled_with_the_site_logo(): void
    {
        $html = ReviewMail::html(review: $this->review(), productName: 'Panel', brand: [
            'siteName' => 'Pest & Co',
            'logoUrl' => 'https://example.com/logo-dark.png',
        ]);

        $this->assertStringContainsString('src="https://example.com/logo-dark.png"', $html);
        $this->assertStringNotContainsString('admin', $html);
        $this->assertStringContainsString('Pest &amp; Co', $html);
        $this->assertStringContainsString('4 out of 5', $html);
        $this->assertStringContainsString("Line one<br>\nLine two", $html);
    }

    public function test_the_html_body_falls_back_to_the_site_name_without_a_logo(): void
    {
        $html = ReviewMail::html(review: $this->review(), productName: 'Panel', brand: ['siteName' => 'Pest']);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('>Pest</span>', $html);
    }

    public function test_the_sample_is_made_up_and_builds_a_complete_email(): void
    {
        $sample = ReviewMail::sample();
        $html = ReviewMail::html(review: $sample['review'], productName: $sample['productName']);

        $this->assertStringContainsString('example.com', $sample['review']['contact']);
        $this->assertStringContainsString('Example Product Name', $html);
        $this->assertStringContainsString('4 out of 5', $html);
    }
}
