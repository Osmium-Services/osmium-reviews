<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Reviews lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (statusBadge, ratingStars,
 * productDisplay...) are prepared by ReviewsController::formatReview() and formatDeletedReview().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

return [
    'recent' => [
        'id' => 'reviews-recent',
        'title' => 'Recent Changes',
        'noun' => ['review', 'reviews'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'reviews/action/',
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
            ['key' => 'reviewerName', 'label' => 'Reviewer', 'type' => 'raw', 'strong' => true, 'fill' => true],
            ['key' => 'productTitle', 'label' => 'Product', 'type' => 'raw'],
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'updatedDate', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'active' => [
        'id' => 'reviews',
        'title' => 'Review Management',
        'noun' => ['review', 'reviews'],
        'endpoint' => 'reviews/action/',
        'emptyText' => 'No reviews yet. They appear here, waiting for approval, once customers send them.',
        'order' => [5, 'desc'],
        'subtitle' => fn(array $context): string => $context['publishedCount'] . ' published'
            . ($context['unpublishedCount'] > 0 ? ', ' . $context['unpublishedCount'] . ' pending' : ''),
        'columns' => [
            ['key' => 'statusBadge', 'label' => 'Status', 'type' => 'raw', 'sortKey' => 'is_published'],
            ['key' => 'productDisplay', 'label' => 'Product', 'type' => 'raw', 'sortKey' => 'product_title'],
            ['key' => 'reviewerDisplay', 'label' => 'Reviewer', 'type' => 'raw', 'sortKey' => 'name'],
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true, 'sortKey' => 'rating'],
            ['key' => 'contentPreview', 'label' => 'Review', 'type' => 'text', 'small' => true, 'fill' => true],
            ['key' => 'submittedDate', 'label' => 'Submitted', 'type' => 'text', 'small' => true, 'nowrap' => true, 'sortKey' => 'submittedTimestamp'],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
            [
                'label' => 'Unpublish', // Publishing is only from the edit page, so nobody approves a review without opening it
                'icon' => 'bx-hide',
                'action' => 'unpublish',
                'visible' => fn(array $row, array $context): bool => (bool) $row['is_published'],
            ],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this review? It can be restored from Deleted Reviews.',
                'danger' => true,
            ],
        ],
    ],

    'deleted' => ListView::deletedPreset([
        'id' => 'reviews-deleted',
        'title' => 'Deleted Reviews',
        'noun' => ['item', 'items'],
        'endpoint' => 'reviews/action/',
        'hideWhenEmpty' => false,
        'headerButton' => [
            'label' => 'Purge All',
            'icon' => 'bx-trash',
            'class' => 'btn-outline-danger',
            'action' => 'purge_deleted',
            'confirm' => 'Permanently delete all deleted reviews? This cannot be undone!',
            'visible' => fn(array $rows, array $context): bool => (bool) $context['isAdmin'],
        ],
        'columns' => [
            ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
            ['key' => 'reviewerName', 'label' => 'Reviewer', 'type' => 'raw', 'strong' => true, 'fill' => true],
            ['key' => 'productTitle', 'label' => 'Product', 'type' => 'raw'],
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'deletedDate', 'label' => 'Deleted', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ]),
];
