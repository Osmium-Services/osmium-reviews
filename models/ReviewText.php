<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * Review text and ratings prepared for the theme and the admin lists. Visitor text is stored as plain
 * text and escaped here, so no markup a visitor types can ever run.
 */
class ReviewText
{
    private const PREVIEW_LENGTH = 100;

    /** The review as safe HTML paragraphs, or '' when there is no text */
    public static function publicHtml(?string $text): string
    {
        $trimmed = \trim((string) $text);
        if ($trimmed === '') return '';

        $paragraphs = \preg_split('/\R{2,}/', $trimmed) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . \nl2br(\htmlspecialchars(\trim($paragraph))) . '</p>';
        }

        return $html;
    }

    /** @param int[] $ratings @return float|null The mean to one decimal place, or null when there are none */
    public static function average(array $ratings): ?float
    {
        if ($ratings === []) return null;

        return \round(\array_sum($ratings) / \count($ratings), 1);
    }

    /** A short plain-text start of a review for the admin list */
    public static function preview(?string $content): string
    {
        $plain = \strip_tags((string) $content);
        $cut = \mb_substr(string: $plain, start: 0, length: self::PREVIEW_LENGTH);
        $needsDots = \mb_strlen($plain) > self::PREVIEW_LENGTH;

        return $cut . ($needsDots ? '...' : '');
    }

    /** Five star icons, filled up to the rating (admin lists) */
    public static function stars(int $rating, string $extra = ''): string
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $color = $i <= $rating ? '#ffc107' : '#e0e0e0';
            $stars .= '<i class="bx bxs-star" style="color: ' . $color . '; font-size: 0.9rem;"' . $extra . '></i>';
        }

        return $stars;
    }
}
