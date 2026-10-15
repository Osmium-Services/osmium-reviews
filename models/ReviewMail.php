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

    /**
     * The same email in the site's email styling: logo header, green accents, the review set out in full. It carries no link to the admin: the team signs in to approve.
     *
     * @param array{name: string, contact: string, rating: int, content: string} $review
     * @param array{siteName?: string, logoUrl?: string} $brand The site's name and its email logo, each optional
     */
    public static function html(array $review, string $productName, array $brand = []): string
    {
        $e = static fn(string $value): string => \htmlspecialchars($value, ENT_QUOTES);
        $siteName = $e((string) ($brand['siteName'] ?? ''));
        $logoUrl = (string) ($brand['logoUrl'] ?? '');

        $rating = \max(0, \min(5, (int) $review['rating']));
        $stars = \str_repeat('&#9733;', $rating) . '<span style="color: #d1d5db;">' . \str_repeat('&#9733;', 5 - $rating) . '</span>';

        $logo = $logoUrl === ''
            ? '<span style="font-size: 22px; font-weight: bold; color: #166534;">' . $siteName . '</span>'
            : '<img src="' . $e($logoUrl) . '" alt="' . $siteName . '" style="max-width: 220px; height: auto;">';

        $message = \nl2br($e($review['content']), false);
        $reviewer = $e($review['name']) . ' (<a href="mailto:' . $e($review['contact']) . '" style="color: #166534;">' . $e($review['contact']) . '</a>)';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td style="padding: 30px; text-align: center; background-color: #ffffff; border-bottom: 4px solid #166534;">{$logo}</td>
        </tr>
        <tr>
            <td style="padding: 40px 30px;">
                <h2 style="color: #166534; margin: 0 0 20px 0; font-size: 22px;">A new review is waiting for approval</h2>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 6px 0;"><strong>Product:</strong> {$e($productName)}</p>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 6px 0;"><strong>From:</strong> {$reviewer}</p>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 20px 0;"><strong>Rating:</strong> <span style="color: #f59e0b; font-size: 18px;">{$stars}</span> {$rating} out of 5</p>
                <div style="background-color: #f8f8f8; border-radius: 8px; padding: 20px; color: #444; font-size: 16px; line-height: 1.5;">{$message}</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px; background-color: #f8f8f8; text-align: center;">
                <p style="color: #999; font-size: 12px; margin: 0;">&copy; {$siteName} - This is an automated message, please do not reply.</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
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
