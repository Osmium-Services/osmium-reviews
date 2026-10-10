<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * The email telling the team a review has come in. Free of the database and the mailer.
 */
class ReviewMail
{
    public const SUBJECT = 'A product review was added';

    /** @param array{name: string, contact: string, rating: int, content: string} $review */
    public static function text(array $review, string $productName): string
    {
        return "A new review is waiting for approval.\n\n"
            . "Product: {$productName}\n"
            . "From: {$review['name']} ({$review['contact']})\n"
            . "Rating: {$review['rating']} out of 5\n\n"
            . $review['content'] . "\n";
    }

    /** @param array{name: string, contact: string, rating: int, content: string} $review */
    public static function html(array $review, string $productName): string
    {
        $text = self::text(review: $review, productName: $productName);

        return '<pre style="font-family: inherit;">' . \htmlspecialchars($text) . '</pre>';
    }

    /**
     * @param string[] $addresses
     * @return array<int, array{email: string, name: string}>
     */
    public static function recipients(array $addresses): array
    {
        return \array_map(static fn(string $address): array => ['email' => $address, 'name' => $address], $addresses);
    }
}
