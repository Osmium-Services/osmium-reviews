<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * Product reviews. A review waits unpublished until an admin approves it, and is soft-deleted first
 * and only removed for good from the bin.
 */
class ProductReview extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'product_reviews');
    }

    /**
     * @param array{name: string, contact: string, product_id: int, rating: int, content: string} $review
     * @return int The new review's id
     */
    public function create(array $review, ?string $ipAddress, bool $published = false, bool $verifiedPurchase = false): int
    {
        $this->run('review-insert.sql', [
            ':product_id' => $review['product_id'],
            ':name' => $review['name'],
            ':contact' => $review['contact'],
            ':ip_address' => $ipAddress,
            ':rating' => $review['rating'],
            ':content' => $review['content'],
            ':is_published' => (int) $published,
            ':verified_purchase' => (int) $verifiedPurchase,
        ]);

        return (int) $this->database->lastInsertId();
    }

    /** A review whether or not it is deleted (check deleted_at), or null when there is no such review */
    public function get(int $id): ?array
    {
        $row = $this->one('review-get.sql', [':id' => $id]);

        return $row === false ? null : $row;
    }

    /** @param array{product_id: int, name: string, contact: ?string, rating: int, content: ?string, is_published: int} $fields */
    public function update(int $id, array $fields): void
    {
        $this->run('review-update.sql', [
            ':id' => $id,
            ':product_id' => $fields['product_id'],
            ':name' => $fields['name'],
            ':contact' => $fields['contact'],
            ':rating' => $fields['rating'],
            ':content' => $fields['content'],
            ':is_published' => $fields['is_published'],
        ]);
    }

    public function setPublished(int $id, bool $published): void
    {
        $this->run('review-set-published.sql', [':id' => $id, ':is_published' => (int) $published]);
    }

    public function softDelete(int $id): void
    {
        $this->run('review-soft-delete.sql', [':id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->run('review-restore.sql', [':id' => $id]);
    }

    public function hardDelete(int $id): void
    {
        $this->run('review-hard-delete.sql', [':id' => $id]);
    }

    /** @return int How many reviews were removed */
    public function purgeDeleted(): int
    {
        $this->database->prepare(sql: $this->loadSqlFile('review-purge-deleted.sql'), bindings: [])->execute();

        return $this->database->rowCount();
    }

    public function countPending(): int
    {
        $row = $this->one('review-count-pending.sql', []);

        return (int) ($row['count'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPending(int $limit): array
    {
        return $this->many('review-list-pending.sql', [':limit' => $limit]);
    }

    /** @return array<string, mixed> waiting, oldest_waiting, published and average_rating over live reviews */
    public function totals(): array
    {
        return $this->one('review-totals.sql', []) ?: [];
    }

    /** @return array<int, array<string, mixed>> Products with reviews waiting: how many, the oldest, average rating and verified count */
    public function waitingByProduct(): array
    {
        return $this->many('review-waiting-by-product.sql', []);
    }

    /** @return array<int, array<string, mixed>> The oldest waiting reviews first, with their product */
    public function waitingList(int $limit): array
    {
        return $this->many('review-waiting-list.sql', [':limit' => $limit]);
    }

    /** @return array<int, array<string, mixed>> Every live review with its product, newest first */
    public function listActive(): array
    {
        return $this->many('review-list-active.sql', []);
    }

    /** @return array<int, array<string, mixed>> Submitted or changed in the last 24 hours, newest first, capped at 10 */
    public function listRecent(): array
    {
        return $this->many('review-list-recent.sql', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function listDeleted(): array
    {
        return $this->many('review-list-deleted.sql', []);
    }

    /** @return array<int, array<string, mixed>> What the public sees: published, not deleted, oldest first */
    public function publishedForProduct(int $productId): array
    {
        return $this->many('review-list-published-for-product.sql', [':product_id' => $productId]);
    }

    public function countRecentByIp(string $ipAddress, int $minutes): int
    {
        $row = $this->one('review-count-by-ip.sql', [':ip_address' => $ipAddress, ':minutes' => $minutes]);

        return (int) ($row['count'] ?? 0);
    }

    /** Whether this email has a paid (or dispatched) order for the product: refunded, cancelled and test orders do not count */
    public function isVerifiedPurchase(string $email, int $productId): bool
    {
        $row = $this->one('review-verified-purchase.sql', [':email' => $email, ':product_id' => $productId]);

        return $row !== false;
    }

    public function productExists(int $productId): bool
    {
        $row = $this->one('review-product-exists.sql', [':id' => $productId]);

        return $row !== false;
    }

    /** The product's name for the team email, or 'Unknown product' */
    public function productName(int $productId): string
    {
        $row = $this->one('review-product-name.sql', [':id' => $productId]);

        return $row === false ? 'Unknown product' : (string) $row['model_name'];
    }

    /** The product's page address (no slashes), or null when it has no live page */
    public function productPageUrl(int $productId): ?string
    {
        $row = $this->one('review-product-page-url.sql', [':product_id' => $productId]);

        return $row === false ? null : (string) $row['url'];
    }

    /** @return array<int, array{id: int, model_name: string, reference: ?string}> Live products for the edit page's picker */
    public function listProducts(): array
    {
        return $this->many('review-list-products.sql', []);
    }

    private function run(string $file, array $bindings): void
    {
        $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->execute();
    }

    private function one(string $file, array $bindings): array|false
    {
        return $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->single();
    }

    private function many(string $file, array $bindings): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->resultset();
    }
}
