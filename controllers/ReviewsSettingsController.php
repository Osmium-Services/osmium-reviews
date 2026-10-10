<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Reviews\Models\ReviewsConfig;

/**
 * Review settings: who is emailed when a customer sends a review. A full-page form POST/redirect flow,
 * like the other services' settings pages.
 *
 * Routes:
 *   - index() → /admin/settings/reviews/  (GET shows the form, POST saves it)
 */
class ReviewsSettingsController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/osmium-reviews.json.php';

    public function index(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['recipients'] = \implode("\n", ReviewsConfig::get()->recipients);
        $this->data['admin']['settingsSaved'] = $_SESSION['reviews_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['reviews_settings_error'] ?? false;
        unset($_SESSION['reviews_settings_saved'], $_SESSION['reviews_settings_error']);

        $this->setView('reviews/settings.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) $this->fail('Invalid form submission. Please try again.');

        $parsed = ReviewsConfig::parseAddresses((string) ($_POST['recipients'] ?? ''));
        $hasInvalid = $parsed['invalid'] !== [];
        if ($hasInvalid) $this->fail('These are not email addresses: ' . \implode(', ', $parsed['invalid']));

        $this->saveConfig($parsed['recipients']);
        $this->admin->model->changelog->log(description: 'Updated Reviews settings', recordType: 'settings');
        ReviewsConfig::clearCache();

        $_SESSION['reviews_settings_saved'] = true;
        $this->redirect('settings/reviews/');
    }

    private function fail(string $message): never
    {
        $_SESSION['reviews_settings_error'] = $message;
        $this->redirect('settings/reviews/');
    }

    private function saveConfig(array $recipients): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        $existing = $configExists ? (string) \file_get_contents(self::CONFIG_FILE_PATH) : null;
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $content = ReviewsConfig::toContent(existing: $existing, recipients: $recipients);

        \file_put_contents(self::CONFIG_FILE_PATH, $content);
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }
}
