<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * The dashboard card for reviews waiting for approval, in the shape core's dashboard.widgets hook
 * takes. Free of the database.
 */
class ReviewWidget
{
    /**
     * @param array<int, array{id: int, name: string, rating: int, product_title: ?string}> $rows The newest pending reviews
     * @return array<string, mixed>
     */
    public static function build(int $pendingCount, array $rows): array
    {
        return [
            'id' => 'reviews-pending',
            'title' => 'Pending reviews',
            'icon' => 'bx-star',
            'roles' => ['admin', 'editor'],
            'order' => 50,
            'cols' => 'col-12 col-lg-6',
            'count' => $pendingCount,
            'link' => ['label' => 'View all', 'route' => 'reviews/'],
            'lines' => \array_map(self::line(...), $rows),
            'emptyText' => 'No pending reviews.',
        ];
    }

    /** @return array{text: string, sub: string, route: string} */
    private static function line(array $row): array
    {
        $product = $row['product_title'] ?? null;

        return [
            'text' => $product ?? 'Unknown product',
            'sub' => $row['name'] . ' - ' . (int) $row['rating'] . '/5 stars',
            'route' => 'reviews/edit/?id=' . (int) $row['id'],
        ];
    }
}
