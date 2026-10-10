<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewText.php';

/**
 * How review text and ratings are prepared for the theme and the admin lists.
 */
final class ReviewTextTest extends TestCase
{
    public function test_public_text_is_escaped_so_markup_cannot_run(): void
    {
        $html = ReviewText::publicHtml('<script>alert(1)</script> & "quotes"');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    public function test_blank_lines_make_paragraphs_and_single_breaks_stay_in_the_paragraph(): void
    {
        $html = ReviewText::publicHtml("First line\nsecond line\n\nNext paragraph");

        $this->assertSame('<p>First line<br />' . "\n" . 'second line</p><p>Next paragraph</p>', $html);
    }

    public function test_empty_text_gives_no_markup(): void
    {
        $this->assertSame('', ReviewText::publicHtml(''));
        $this->assertSame('', ReviewText::publicHtml("  \n "));
        $this->assertSame('', ReviewText::publicHtml(null));
    }

    public function test_the_average_is_rounded_to_one_decimal_and_empty_has_none(): void
    {
        $this->assertSame(4.3, ReviewText::average([5, 4, 4]));
        $this->assertSame(5.0, ReviewText::average([5]));
        $this->assertNull(ReviewText::average([]));
    }

    public function test_a_preview_is_cut_at_100_characters_with_dots_and_tags_removed(): void
    {
        $this->assertSame('Short', ReviewText::preview('<b>Short</b>'));
        $this->assertSame(\str_repeat('a', 100) . '...', ReviewText::preview(\str_repeat('a', 101)));
        $this->assertSame(\str_repeat('a', 100), ReviewText::preview(\str_repeat('a', 100)));
    }

    public function test_stars_fill_to_the_rating_and_the_rest_are_grey(): void
    {
        $stars = ReviewText::stars(3);

        $this->assertSame(3, \substr_count($stars, '#ffc107'));
        $this->assertSame(2, \substr_count($stars, '#e0e0e0'));
    }
}
