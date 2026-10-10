<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * What the product page gets from this service. Free of the database.
 */
class ReviewPageData
{
    private const NOTICE_KEY = 'review_notice';

    /**
     * @param array<int, array{id: int, name: string, rating: int, content: ?string, created_at: string}> $rows Published reviews
     * @param array{status: string, message: string}|null $notice What happened to this visitor's own submission, if anything
     * @return array{reviews: array, reviewCount: int, ratingAverage: ?float, reviewNotice: ?array}
     */
    public static function build(array $rows, ?array $notice): array
    {
        $reviews = \array_map(self::review(...), $rows);
        $ratings = \array_map(static fn(array $row): int => (int) $row['rating'], $rows);

        return [
            'reviews' => $reviews,
            'reviewCount' => \count($reviews),
            'ratingAverage' => ReviewText::average($ratings),
            'reviewNotice' => $notice,
        ];
    }

    /** The visitor's own notice, read once: it is removed from the session */
    public static function takeNotice(array &$session): ?array
    {
        $notice = $session[self::NOTICE_KEY] ?? null;
        unset($session[self::NOTICE_KEY]);

        return \is_array($notice) ? $notice : null;
    }

    /** Remember what happened to the visitor's submission, for the page they are sent back to */
    public static function rememberNotice(array &$session, string $status, string $message): void
    {
        $session[self::NOTICE_KEY] = ['status' => $status, 'message' => $message];
    }

    /** One review for the theme: the reviewer's email is never included */
    private static function review(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'rating' => (int) $row['rating'],
            'content' => ReviewText::publicHtml($row['content']),
            'created_at' => (string) $row['created_at'],
        ];
    }
}
