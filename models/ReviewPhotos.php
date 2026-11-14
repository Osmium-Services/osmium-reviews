<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

use Osmium\Core\Library\ImageTools;
use Osmium\Core\Library\ImageUpload;

/**
 * The photos an admin attaches to a review. Each review's photos live in their own folder, named after the review
 * id, and the review stores their paths one per line. Older photos stored elsewhere under /assets/images/ stay
 * valid and are shown, but are never deleted from disk. New photos are turned upright, scaled down to fit and saved as WebP.
 */
class ReviewPhotos
{
    public const DIR = '/assets/images/shop/reviews';
    public const MAX_PHOTOS = 6;
    public const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_WIDTH = 1200;
    private const WEBP_QUALITY = 80;
    private const TYPES = ['jpg', 'png', 'webp'];

    public static function folder(int $reviewId): string
    {
        return self::DIR . '/' . $reviewId . '/';
    }

    /** The uploaded files in $_FILES['new_photos'] as a plain list, with empty slots left out */
    public static function uploads(?array $files): array
    {
        if ($files === null || !\is_array($files['name'] ?? null)) return [];

        $uploads = [];
        foreach (\array_keys($files['name']) as $i) {
            $file = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            if (ImageUpload::wasUploaded($file)) $uploads[] = $file;
        }

        return $uploads;
    }

    /** Only paths the review already has can be kept, so a posted path can never point somewhere new */
    public static function kept(array $posted, ?string $stored): array
    {
        $current = ReviewText::photoPaths($stored);

        return \array_values(\array_filter($current, static fn(string $path): bool => \in_array($path, $posted, true)));
    }

    /** What is wrong with the chosen photos, in words for the admin, or null */
    public static function problem(int $keptCount, array $uploads): ?string
    {
        if ($keptCount + \count($uploads) > self::MAX_PHOTOS) return 'A review can have up to ' . self::MAX_PHOTOS . ' photos';

        foreach ($uploads as $upload) {
            $error = (new ImageUpload(self::TYPES, self::MAX_BYTES))->errorFor($upload);
            if ($error !== null) return ($upload['name'] ?? 'A photo') . ': ' . $error;
        }

        return null;
    }

    /**
     * Save the new uploads into the review's folder and delete the removed photos from it.
     *
     * @param string[] $kept Paths that stay
     * @return string|null The review's photo paths one per line, or null when there are none
     * @throws \RuntimeException|\InvalidArgumentException when a photo can't be saved
     */
    public static function apply(int $reviewId, ?string $stored, array $kept, array $uploads, string $documentRoot): ?string
    {
        $folder = self::folder($reviewId);
        $directory = $documentRoot . $folder;
        $paths = $kept;

        $tools = new ImageTools();
        foreach ($uploads as $upload) {
            $name = 'review-' . $reviewId . '-' . \bin2hex(\random_bytes(4)) . '.webp';
            $tools->storeAsWebp(file: $upload, maxBytes: self::MAX_BYTES, path: $directory . $name, maxWidth: self::MAX_WIDTH, quality: self::WEBP_QUALITY);
            $paths[] = $folder . $name;
        }

        foreach (ReviewText::photoPaths($stored) as $old) {
            $removed = !\in_array($old, $kept, true);
            $ownFolder = \str_starts_with($old, $folder);
            if ($removed && $ownFolder) ImageUpload::delete($directory, \basename($old));
        }

        return $paths === [] ? null : \implode("\n", $paths);
    }
}
