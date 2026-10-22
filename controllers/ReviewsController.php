<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Controllers;

use Osmium\Core\Library\Input;
use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Reviews\Models\ProductReview;
use Osmium\Services\Reviews\Models\ReviewRules;
use Osmium\Services\Reviews\Models\ReviewText;

/**
 * Reviews admin: the list with its bin, the edit page, and the AJAX actions behind the list.
 *
 * Routes:
 *   - index()  → /admin/reviews/
 *   - edit()   → /admin/reviews/edit/?id=N   (no id: add a review)
 *   - action() → /admin/reviews/action/      (unpublish, delete, restore, permanently_delete, purge_deleted)
 */
class ReviewsController extends AdminController
{
    private const RECORD_TYPE = 'review';

    public function index(): void
    {

        $reviews = new ProductReview($this->osmium->dataSource);
        $active = $reviews->listActive();
        $publishedCount = \count(\array_filter($active, static fn(array $row): bool => (bool) $row['is_published']));

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/reviews.php';
        $this->data['admin']['reviews'] = \array_map($this->formatReview(...), $active);
        $this->data['admin']['recentReviews'] = \array_map($this->formatRecentReview(...), $reviews->listRecent());
        $this->data['admin']['deletedReviews'] = \array_map($this->formatDeletedReview(...), $reviews->listDeleted());
        $this->data['admin']['publishedCount'] = $publishedCount;
        $this->data['admin']['unpublishedCount'] = \count($active) - $publishedCount;

        $this->setView('reviews/index.phtml');
    }

    public function edit(): void
    {
        $stashedId = (int) $this->osmium->getStashedParam(key: 'id', default: 0); // Core moves query params out of $_GET
        $this->osmium->clearStashedQuerystring();

        $reviews = new ProductReview($this->osmium->dataSource);
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        $id = $isPost ? Input::post()->int(key: 'id') : $stashedId; // The form posts to the clean address, so its id is in the body
        $error = $isPost ? $this->save(reviews: $reviews, id: $id) : null;

        $stored = $id ? $reviews->get($id) : null;
        $isMissing = $id && (!$stored || $stored['deleted_at'] !== null);
        if ($isMissing) $this->redirect('reviews/');

        $this->data['admin']['review'] = $this->formatForEdit($isPost && $error ? $this->submittedFields($id) : $stored);
        $this->data['admin']['products'] = $reviews->listProducts();
        $this->data['admin']['isNew'] = !$id;
        $this->data['admin']['error'] = $error;
        $this->data['admin']['showSavedMessage'] = $this->takeSavedFlag();

        $this->setView('reviews/edit.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $action = (string) ($input['action'] ?? '');
        $id = (int) ($input['id'] ?? 0);
        $needsId = $action !== 'purge_deleted';
        $missingId = $needsId && $id <= 0;
        if ($missingId) $this->admin->jsonError('Invalid ID');

        try {
            $this->admin->jsonSuccess($this->run(action: $action, id: $id));
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Actions
    // =========================================================================

    private function run(string $action, int $id): array
    {
        $reviews = new ProductReview($this->osmium->dataSource);

        return match ($action) {
            'unpublish' => $this->change(fn() => $reviews->setPublished(id: $id, published: false), $id, 'Unpublished', $action),
            'delete' => $this->change(fn() => $reviews->softDelete($id), $id, 'Deleted', $action),
            'restore' => $this->change(fn() => $reviews->restore($id), $id, 'Restored', $action),
            'permanently_delete' => $this->permanentlyDelete(reviews: $reviews, id: $id),
            'purge_deleted' => $this->purgeDeleted($reviews),
            default => throw new \InvalidArgumentException('Unknown action'),
        };
    }

    private function change(callable $apply, int $id, string $label, string $action): array
    {
        $apply();
        $this->logChange(label: $label, id: $id);

        return ['success' => true, 'action' => $action, 'id' => $id];
    }

    private function permanentlyDelete(ProductReview $reviews, int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $this->logChange(label: 'Permanently deleted', id: $id); // Logged first: the name is looked up from the row
        $reviews->hardDelete($id);

        return ['success' => true, 'action' => 'permanently_delete', 'id' => $id];
    }

    private function purgeDeleted(ProductReview $reviews): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $count = $reviews->purgeDeleted();
        $this->admin->model->changelog->log(description: "Purged {$count} deleted review(s)", recordType: self::RECORD_TYPE, recordId: 0);

        return ['success' => true, 'action' => 'purge_deleted', 'count' => $count];
    }

    // =========================================================================
    // Edit form
    // =========================================================================

    /** @return string|null What is wrong with the submitted form, or null once it has been saved (and the page redirected) */
    private function save(ProductReview $reviews, int $id): ?string
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) return 'Invalid form submission. Please try again.';

        $fields = $this->submittedFields($id);
        $problem = $this->problem($fields);
        if ($problem !== null) return $problem;

        $savedId = $this->store(reviews: $reviews, id: $id, fields: $fields);
        $_SESSION['reviews_saved'] = true;
        $this->redirect('reviews/edit/?id=' . $savedId);

        return null;
    }

    private function store(ProductReview $reviews, int $id, array $fields): int
    {
        $name = $fields['name'];
        if ($id) {
            $reviews->update(id: $id, fields: $fields);
            $this->admin->model->changelog->log(description: "Updated review by {$name}", recordType: self::RECORD_TYPE, recordId: $id);

            return $id;
        }

        $newId = $reviews->create(review: $this->asNewReview($fields), ipAddress: null, published: (bool) $fields['is_published']);
        $this->admin->model->changelog->log(description: "Created review by {$name}", recordType: self::RECORD_TYPE, recordId: $newId);

        return $newId;
    }

    private function asNewReview(array $fields): array
    {
        return [
            'name' => $fields['name'],
            'contact' => (string) $fields['contact'],
            'product_id' => $fields['product_id'],
            'rating' => $fields['rating'],
            'content' => (string) $fields['content'],
        ];
    }

    private function submittedFields(int $id): array
    {
        $input = Input::post();
        $rating = \max(1, \min(5, $input->int(key: 'rating', default: 5)));

        return [
            'id' => $id,
            'product_id' => $input->int(key: 'product_id'),
            'name' => \trim($input->has(key: 'name') ? $input->string(key: 'name') : ''),
            'contact' => $input->nullableString(key: 'contact'),
            'rating' => $rating,
            'content' => $input->nullableString(key: 'content'),
            'is_published' => (int) $input->has(key: 'is_published'),
        ];
    }

    private function problem(array $fields): ?string
    {
        $noProduct = $fields['product_id'] < 1;
        if ($noProduct) return 'Please select a product';

        $noName = $fields['name'] === '';
        if ($noName) return 'Reviewer name is required';

        $nameTooLong = \mb_strlen($fields['name']) > ReviewRules::MAX_NAME;
        if ($nameTooLong) return 'The name can be up to ' . ReviewRules::MAX_NAME . ' characters';

        $contactTooLong = \mb_strlen((string) $fields['contact']) > ReviewRules::MAX_EMAIL;
        if ($contactTooLong) return 'The contact can be up to ' . ReviewRules::MAX_EMAIL . ' characters';

        $contentTooLong = \mb_strlen((string) $fields['content']) > ReviewRules::MAX_MESSAGE;
        if ($contentTooLong) return 'The review can be up to ' . ReviewRules::MAX_MESSAGE . ' characters';

        return null;
    }

    private function takeSavedFlag(): bool
    {
        $saved = isset($_SESSION['reviews_saved']);
        unset($_SESSION['reviews_saved']);

        return $saved;
    }

    // =========================================================================
    // Row shaping
    // =========================================================================

    private function formatReview(array $review): array
    {
        $title = (string) ($review['product_title'] ?? '');
        $hasProduct = $title !== '';
        $sku = (string) ($review['product_sku'] ?? '');
        $productDisplay = $hasProduct
            ? '<strong>' . \htmlspecialchars($title) . '</strong>' . ($sku !== '' ? '<br><small class="text-muted">' . \htmlspecialchars($sku) . '</small>' : '')
            : '<span class="text-muted">Product #' . (int) $review['product_id'] . '</span>';

        $contact = (string) ($review['contact'] ?? '');
        $reviewerDisplay = '<strong>' . \htmlspecialchars((string) $review['name']) . '</strong>'
            . ($contact !== '' ? '<br><small class="text-muted">' . \htmlspecialchars($contact) . '</small>' : '');

        $submittedTimestamp = \strtotime((string) $review['submitted_at']);

        return $review + [
            'edit_url' => $this->getBasePath() . 'reviews/edit/?id=' . (int) $review['id'],
            'statusBadge' => ($review['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning">Pending</span>')
                . ($review['verified_purchase'] ? '<br><span class="badge bg-label-info mt-1">Verified purchase</span>' : ''),
            'productDisplay' => $productDisplay,
            'reviewerDisplay' => $reviewerDisplay,
            'ratingStars' => ReviewText::stars((int) $review['rating']),
            'contentPreview' => ReviewText::preview($review['content']),
            'submittedDate' => \date('d M Y', $submittedTimestamp),
            'submittedTimestamp' => $submittedTimestamp,
        ];
    }

    private function formatRecentReview(array $review): array
    {
        $changed = $review['updated_at'] ?? $review['submitted_at'];

        return [
            'id' => $review['id'],
            'edit_url' => $this->getBasePath() . 'reviews/edit/?id=' . (int) $review['id'],
            'reviewerName' => \htmlspecialchars((string) $review['name']),
            'productTitle' => \htmlspecialchars((string) ($review['product_title'] ?? 'Unknown')),
            'ratingStars' => ReviewText::stars((int) $review['rating']),
            'updatedDate' => \date('j M Y, g:ia', \strtotime((string) $changed)),
        ];
    }

    private function formatDeletedReview(array $review): array
    {
        return [
            'id' => $review['id'],
            'reviewerName' => \htmlspecialchars((string) $review['name']),
            'productTitle' => \htmlspecialchars((string) ($review['product_title'] ?? 'Unknown')),
            'ratingStars' => ReviewText::stars((int) $review['rating']),
            'deletedDate' => \date('d M Y', \strtotime((string) $review['deleted_at'])),
        ];
    }

    private function formatForEdit(?array $review): array
    {
        $submitted = $review['submitted_at'] ?? null;

        return [
            'id' => $review['id'] ?? null,
            'product_id' => (int) ($review['product_id'] ?? 0),
            'name' => (string) ($review['name'] ?? 'Anonymous'),
            'contact' => (string) ($review['contact'] ?? ''),
            'rating' => (int) ($review['rating'] ?? 5),
            'content' => (string) ($review['content'] ?? ''),
            'is_published' => (int) ($review['is_published'] ?? 0),
            'verified_purchase' => (int) ($review['verified_purchase'] ?? 0),
            'submittedFormatted' => $submitted ? \date('d M Y H:i', \strtotime((string) $submitted)) : null,
        ];
    }

    private function logChange(string $label, int $id): void
    {
        $row = (new ProductReview($this->osmium->dataSource))->get($id);
        $name = (string) ($row['name'] ?? 'Unknown');

        $this->admin->model->changelog->log(description: "{$label} review by {$name}", recordType: self::RECORD_TYPE, recordId: $id);
    }
}
