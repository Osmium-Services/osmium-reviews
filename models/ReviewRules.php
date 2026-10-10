<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * What a visitor may submit as a review. Pure checks: nothing here touches the database.
 */
class ReviewRules
{
    public const MAX_NAME = 50;
    public const MAX_EMAIL = 255;
    public const MAX_MESSAGE = 2000;

    /**
     * @param array<string, mixed> $input The submitted fields: name, email, pid, rating, message
     * @return array{error: ?string, review: ?array{name: string, contact: string, product_id: int, rating: int, content: string}}
     */
    public static function check(array $input): array
    {
        $name = \trim((string) ($input['name'] ?? ''));
        $email = \trim((string) ($input['email'] ?? ''));
        $message = \trim((string) ($input['message'] ?? ''));
        $productId = self::wholeNumber($input['pid'] ?? null);
        $rating = self::wholeNumber($input['rating'] ?? null);

        $error = self::firstProblem(name: $name, email: $email, message: $message, productId: $productId, rating: $rating);
        if ($error !== null) return ['error' => $error, 'review' => null];

        return ['error' => null, 'review' => [
            'name' => $name,
            'contact' => $email,
            'product_id' => (int) $productId,
            'rating' => (int) $rating,
            'content' => $message,
        ]];
    }

    private static function firstProblem(string $name, string $email, string $message, ?int $productId, ?int $rating): ?string
    {
        $noName = $name === '';
        if ($noName) return 'Please enter your name';

        $nameTooLong = \mb_strlen($name) > self::MAX_NAME;
        if ($nameTooLong) return 'Your name can be up to ' . self::MAX_NAME . ' characters';

        $emailInvalid = !self::isEmail($email);
        if ($emailInvalid) return 'Please enter a valid email address';

        $noProduct = $productId === null || $productId < 1;
        if ($noProduct) return 'We could not tell which product this review is for';

        $badRating = $rating === null || $rating < 1 || $rating > 5;
        if ($badRating) return 'Please choose a rating from 1 to 5';

        $messageTooLong = \mb_strlen($message) > self::MAX_MESSAGE;
        if ($messageTooLong) return 'Your review can be up to ' . self::MAX_MESSAGE . ' characters';

        return null;
    }

    /** PHP's own check refuses anything longer than an address can be, so no separate length limit is needed */
    private static function isEmail(string $email): bool
    {
        return \filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** A whole number typed as text ("4", not "4.5" or "abc"), or null */
    private static function wholeNumber(mixed $value): ?int
    {
        $isText = \is_string($value) || \is_int($value);
        if (!$isText) return null;

        $digits = \filter_var((string) $value, FILTER_VALIDATE_INT);

        return $digits === false ? null : $digits;
    }
}
