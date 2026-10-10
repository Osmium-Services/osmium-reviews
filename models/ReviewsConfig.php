<?php

declare(strict_types=1);

namespace Osmium\Services\Reviews\Models;

/**
 * This service's settings file (app/config/services/osmium-reviews.json.php): who is emailed when a
 * review comes in. Nobody is emailed until at least one address is saved.
 */
class ReviewsConfig
{
    private static ?object $config = null;
    private static string $configPath = 'app/config/services/osmium-reviews.json.php';

    public static function get(): object
    {
        if (self::$config !== null) return self::$config;

        $content = \file_exists(self::$configPath) ? (string) \file_get_contents(self::$configPath) : null;
        self::$config = self::fromContent($content);

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * @param string|null $content The settings file's text (a PHP header, then JSON), or null when there is no file
     * @return object{recipients: string[]}
     */
    public static function fromContent(?string $content): object
    {
        $saved = [];

        $jsonStart = $content === null ? false : \strpos(haystack: $content, needle: '{');
        if ($jsonStart !== false) {
            $decoded = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);
            $saved = \is_array($decoded) ? ($decoded['reviews'] ?? []) : [];
        }

        $recipients = \is_array($saved['recipients'] ?? null) ? $saved['recipients'] : [];

        return (object) ['recipients' => \array_values(\array_filter($recipients, \is_string(...)))];
    }

    /**
     * The settings file's new text: its PHP guard line and any other settings are kept
     *
     * @param string|null $existing The file's current text, or null when there is none
     * @param string[] $recipients
     */
    public static function toContent(?string $existing, array $recipients): string
    {
        $jsonStart = $existing === null ? false : \strpos(haystack: $existing, needle: '{');
        $header = $jsonStart === false ? "<?php exit(); ?>\n" : \substr(string: $existing, offset: 0, length: $jsonStart);
        $decoded = $jsonStart === false ? [] : \json_decode(\substr(string: $existing, offset: $jsonStart), associative: true);
        $data = \is_array($decoded) ? $decoded : [];

        $data['reviews'] = ['recipients' => \array_values($recipients)];

        $json = \json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $header . $json . "\n";
    }

    /**
     * Addresses typed one per line or separated by commas
     *
     * @return array{recipients: string[], invalid: string[]} Valid addresses in lower case without repeats, and what was not an address
     */
    public static function parseAddresses(string $typed): array
    {
        $recipients = [];
        $invalid = [];

        $pieces = \preg_split('/[\s,;]+/', $typed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($pieces as $piece) {
            $address = \strtolower($piece);
            $isAddress = \filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
            if ($isAddress) $recipients[$address] = $address;
            if (!$isAddress) $invalid[] = $piece;
        }

        return ['recipients' => \array_values($recipients), 'invalid' => $invalid];
    }
}
