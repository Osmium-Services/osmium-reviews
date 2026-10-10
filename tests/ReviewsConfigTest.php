<?php

declare(strict_types=1);

namespace Tests\Services\Reviews;

use Osmium\Services\Reviews\Models\ReviewsConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/ReviewsConfig.php';

/**
 * The people emailed about new reviews, kept in the service's settings file.
 */
final class ReviewsConfigTest extends TestCase
{
    public function test_no_file_means_nobody_is_emailed(): void
    {
        $this->assertSame([], ReviewsConfig::fromContent(null)->recipients);
    }

    public function test_recipients_are_read_back_from_what_was_saved(): void
    {
        $content = ReviewsConfig::toContent(existing: null, recipients: ['a@example.com', 'b@example.com']);

        $this->assertSame(['a@example.com', 'b@example.com'], ReviewsConfig::fromContent($content)->recipients);
    }

    public function test_the_saved_file_keeps_its_php_guard_and_other_settings(): void
    {
        $existing = "<?php exit(); ?>\n" . '{"other": {"x": 1}}';

        $content = ReviewsConfig::toContent(existing: $existing, recipients: ['a@example.com']);

        $this->assertStringStartsWith("<?php exit(); ?>\n", $content);
        $this->assertStringContainsString('"other"', $content);
    }

    public function test_typed_addresses_are_split_by_line_or_comma_and_cleaned(): void
    {
        $parsed = ReviewsConfig::parseAddresses("a@example.com\n b@example.com, A@Example.com ,,\r\nnot-an-email\n");

        $this->assertSame(['a@example.com', 'b@example.com'], $parsed['recipients']);
        $this->assertSame(['not-an-email'], $parsed['invalid']);
    }
}
