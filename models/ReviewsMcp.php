<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * Osmium Reviews' tool for the /mcp endpoint, offered through core's `mcp.tools` hook: one read-only summary of
 * the reviews waiting for approval. Reviewer names and emails are visitor-supplied and personal, so they only
 * appear masked (first and last letters kept) and the review text and IP addresses are never returned. The
 * review id is what identifies a review. There are no write tools: reviews are published by a person in the admin.
 */
class ReviewsMcp
{
    private const LIST_LIMIT = 20;
    private const MAX_REVIEWER_LENGTH = 40;
    private const SECOND_LEVEL_ENDINGS = ['co', 'com', 'org', 'ac', 'gov', 'me', 'ltd', 'plc', 'net', 'sch'];
    private const MAX_NAME_LENGTH = 80;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        return [
            [
                'name' => 'reviews_waiting',
                'description' => 'Product reviews awaiting approval: how many are waiting, the date of the oldest, how many are published with their average star rating, and up to 20 products with reviews waiting (how many, oldest date, average stars, how many are from verified purchasers). Also lists the 20 oldest waiting reviews by id with product, stars, date, verified purchase and the reviewer name and email masked (first and last letters only, for example G***n C*x and g*******x@g***l.com). Review text and IP addresses are never returned. Read only: reviews can only be approved or rejected in the admin, using the review id.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => self::class . '::reviewsWaiting',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function reviewsWaiting(array $call): array
    {
        $reviews = new ProductReview($call['dataSource']);

        return self::shape($reviews->totals(), $reviews->waitingByProduct(), $reviews->waitingList(self::LIST_LIMIT));
    }

    /**
     * @param array<string, mixed> $totals from review-totals.sql
     * @param array<int, array<string, mixed>> $rows from review-waiting-by-product.sql, most waiting first
     * @param array<int, array<string, mixed>> $waitingReviews from review-waiting-list.sql, oldest first
     * @return array<string, mixed>
     */
    public static function shape(array $totals, array $rows, array $waitingReviews = []): array
    {
        $products = [];
        foreach ($rows as $row) {
            $products[] = self::product($row);
        }

        return [
            'waiting' => (int) ($totals['waiting'] ?? 0),
            'oldest_waiting' => self::date($totals['oldest_waiting'] ?? null),
            'published' => (int) ($totals['published'] ?? 0),
            'average_rating_published' => isset($totals['average_rating']) ? (float) $totals['average_rating'] : null,
            'products_with_waiting_reviews' => \count($products),
            'products' => \array_slice($products, 0, self::LIST_LIMIT),
            'oldest_waiting_reviews' => \array_map(self::review(...), \array_slice($waitingReviews, 0, self::LIST_LIMIT)),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function product(array $row): array
    {
        $gone = $row['model_name'] === null || $row['product_deleted_at'] !== null;

        return [
            'id' => (int) $row['product_id'],
            'name' => $gone ? '(deleted product)' : self::clean(\trim((string) ($row['brand_name'] ?? '') . ' ' . (string) $row['model_name'])),
            'waiting' => (int) $row['waiting'],
            'oldest' => self::date($row['oldest'] ?? null),
            'average_rating' => (float) $row['average_rating'],
            'verified_purchases' => (int) $row['verified'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function review(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'product' => self::productName($row),
            'rating' => (int) $row['rating'],
            'submitted' => self::date($row['submitted_at'] ?? null),
            'verified_purchase' => (int) $row['verified_purchase'] === 1,
            'reviewer' => self::maskName((string) ($row['name'] ?? '')),
            'email' => self::maskEmail((string) ($row['contact'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function productName(array $row): string
    {
        $gone = $row['model_name'] === null || $row['product_deleted_at'] !== null;

        return $gone ? '(deleted product)' : self::clean(\trim((string) ($row['brand_name'] ?? '') . ' ' . (string) $row['model_name']));
    }

    /** Every word keeps only its first and last letter: "Gavin Cox" becomes "G***n C*x". Null when there is no name. */
    public static function maskName(string $name): ?string
    {
        $clean = \trim((string) \preg_replace('/\s+/u', ' ', (string) \preg_replace('/[\p{C}]+/u', ' ', $name)));
        if ($clean === '') return null;

        $words = \explode(' ', \mb_substr($clean, 0, self::MAX_REVIEWER_LENGTH));

        return \implode(' ', \array_map(self::maskWord(...), $words));
    }

    /**
     * The part before the @ keeps first and last letters of each dotted piece, every domain label before the ending
     * does the same, and the ending stays whole (.com, or .co.uk): "gavinpcox@gmail.com" becomes "g*******x@g***l.com".
     * Anything that is not shaped like an email address is not shown at all.
     */
    public static function maskEmail(string $email): ?string
    {
        $email = \trim($email);
        if ($email === '') return null;

        $shaped = \preg_match('/^([^@\s]+)@([^@\s]+\.[A-Za-z0-9-]{2,})$/u', $email, $parts) === 1;
        if (!$shaped) return '(not an email address)';

        $local = \implode('.', \array_map(self::maskWord(...), \explode('.', $parts[1])));

        return $local . '@' . self::maskDomain($parts[2]);
    }

    /** "mail.example.co.uk" becomes "m**l.e*****e.co.uk": the ending is kept, every label before it is masked */
    private static function maskDomain(string $domain): string
    {
        $labels = \explode('.', $domain);
        $endingLength = 1;
        $isCountryCode = \mb_strlen($labels[\count($labels) - 1]) === 2;
        $hasSecondLevel = \count($labels) >= 3 && \in_array(\strtolower($labels[\count($labels) - 2]), self::SECOND_LEVEL_ENDINGS, true);
        if ($isCountryCode && $hasSecondLevel) $endingLength = 2;

        $ending = \array_splice($labels, -$endingLength);

        return \implode('.', \array_merge(\array_map(self::maskWord(...), $labels), $ending));
    }

    private static function maskWord(string $word): string
    {
        $length = \mb_strlen($word);
        if ($length <= 1) return '*';
        if ($length === 2) return \mb_substr($word, 0, 1) . '*';

        return \mb_substr($word, 0, 1) . \str_repeat('*', $length - 2) . \mb_substr($word, -1);
    }

    private static function date(mixed $value): ?string
    {
        return $value === null ? null : \substr((string) $value, 0, 10);
    }

    private static function clean(string $text): string
    {
        $clean = \trim((string) \preg_replace('/\s+/u', ' ', (string) \preg_replace('/[\p{C}]+/u', ' ', $text)));

        return \mb_strlen($clean) > self::MAX_NAME_LENGTH ? \mb_substr($clean, 0, self::MAX_NAME_LENGTH) . '…' : $clean;
    }
}
