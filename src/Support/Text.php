<?php

declare(strict_types=1);

namespace JournalHarvester\Support;

/**
 * Small text helpers shared by the OJS and SciELO harvesters.
 */
final class Text
{
    /**
     * Strips tags and entities, then truncates to $limit characters (multibyte-safe).
     *
     * Drupal title fields are limited to 255 characters, hence the default.
     */
    public static function truncate(?string $text, int $limit = 255): string
    {
        $text = self::clean($text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3) . '...';
    }

    /**
     * Decodes HTML entities, removes tags and trims surrounding whitespace.
     */
    public static function clean(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * True when the value carries real content: a non-empty list, or text with at
     * least one letter or digit. Placeholders such as "." or "<p></p>" are ignored.
     */
    public static function hasContent(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) preg_match('/[\p{L}\p{N}]/u', strip_tags((string) $value));
    }

    /**
     * Removes a leading "Abstract"/"Resumo"/"Resumen"/"Résumé" label from an abstract.
     */
    public static function stripAbstractLabel(string $abstract): string
    {
        return (string) preg_replace('/^(Resumo|Resumen|Abstract|Résumé)\s+/iu', '', $abstract);
    }
}
