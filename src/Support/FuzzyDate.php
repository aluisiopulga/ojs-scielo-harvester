<?php

declare(strict_types=1);

namespace JournalHarvester\Support;

/**
 * Finds a publication date in free text such as "Epub 12 Mar 2025" or
 * "15 de março de 2025". Month names are matched by their first three
 * letters in Portuguese, English and Spanish.
 */
final class FuzzyDate
{
    private const MONTHS = [
        'jan' => '01', 'ene' => '01',
        'fev' => '02', 'feb' => '02',
        'mar' => '03',
        'abr' => '04', 'apr' => '04',
        'mai' => '05', 'may' => '05',
        'jun' => '06',
        'jul' => '07',
        'ago' => '08', 'aug' => '08',
        'set' => '09', 'sep' => '09',
        'out' => '10', 'oct' => '10',
        'nov' => '11',
        'dez' => '12', 'dec' => '12', 'dic' => '12',
    ];

    /**
     * Returns "YYYY-MM-DD 00:00:00", or an empty string when nothing plausible is found.
     *
     * 1. An "Epub <date>" mention (the suggested citation) wins.
     * 2. Otherwise, the first date within one year of $issueYear is used, which skips
     *    historical dates that appear in the article body.
     */
    public static function find(string $html, string|int $issueYear = ''): string
    {
        $text = (string) preg_replace('/\s+/u', ' ', strip_tags($html));
        $monthPattern = implode('|', array_keys(self::MONTHS));
        $datePattern = "(\d{1,2})(?:\s+de)?\s+({$monthPattern})[\p{L}]*\.?(?:\s+de)?\s+(\d{4})";

        $candidates = [];
        if (preg_match("/Epub\s+{$datePattern}/iu", $text, $match)) {
            $candidates[] = $match;
        }
        if (preg_match_all("/{$datePattern}/iu", $text, $all, PREG_SET_ORDER)) {
            $candidates = array_merge($candidates, $all);
        }

        foreach ($candidates as [, $day, $monthName, $year]) {
            if ($issueYear !== '' && abs((int) $year - (int) $issueYear) > 1) {
                continue;
            }
            $month = self::MONTHS[mb_strtolower(mb_substr($monthName, 0, 3))] ?? null;
            if ($month === null || (int) $day < 1 || (int) $day > 31) {
                continue;
            }

            return sprintf('%s-%s-%02d 00:00:00', $year, $month, (int) $day);
        }

        return '';
    }
}
