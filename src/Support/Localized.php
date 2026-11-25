<?php

declare(strict_types=1);

namespace JournalHarvester\Support;

/**
 * Resolves OJS multilingual fields.
 *
 * OJS returns translatable fields as ["pt_BR" => "...", "en" => "..."].
 * OJS 3.3 uses "en_US"/"es_ES" locale keys, while 3.4+ uses "en"/"es".
 */
final class Localized
{
    /**
     * @param list<string> $preferredLocales Locales tried in order before falling back to any filled value.
     */
    public static function pick(mixed $field, mixed $default = '', array $preferredLocales = ['pt_BR', 'en', 'en_US', 'es', 'es_ES']): mixed
    {
        if (!is_array($field) || array_is_list($field)) {
            return Text::hasContent($field) ? $field : $default;
        }

        foreach ($preferredLocales as $locale) {
            if (isset($field[$locale]) && Text::hasContent($field[$locale])) {
                return $field[$locale];
            }
        }

        foreach ($field as $value) {
            if (Text::hasContent($value)) {
                return $value;
            }
        }

        return $default;
    }
}
