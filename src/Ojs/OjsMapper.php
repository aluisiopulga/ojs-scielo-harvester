<?php

declare(strict_types=1);

namespace JournalHarvester\Ojs;

use JournalHarvester\Output\Article;
use JournalHarvester\Support\Localized;
use JournalHarvester\Support\Text;

/**
 * Turns raw OJS API payloads into an Article. Pure function: no HTTP here,
 * which keeps it easy to test against saved JSON fixtures.
 */
final class OjsMapper
{
    /**
     * @param array<string, mixed> $submission  Item from /submissions
     * @param array<string, mixed> $publication Response of /submissions/{id}/publications/{id}
     * @param array<string, mixed> $issue       Response of /issues/{id}
     */
    public static function toArticle(OjsJournal $journal, array $submission, array $publication, array $issue): Article
    {
        $issueId = (string) ($issue['id'] ?? '');
        $submissionId = (string) ($submission['id'] ?? '');
        $publicationId = (string) ($submission['currentPublicationId'] ?? ($publication['id'] ?? ''));

        return new Article(
            journal: $journal->name,
            uniqueId: "{$journal->code}-art_{$issueId}_{$submissionId}_{$publicationId}",
            issueId: $issueId,
            articleId: $submissionId,
            publicationId: $publicationId,
            title: Text::truncate((string) Localized::pick($publication['title'] ?? '', isset($publication['title']) ? '' : 'Untitled')),
            abstract: Text::clean((string) Localized::pick($publication['abstract'] ?? '')),
            keywords: self::keywords($publication['keywords'] ?? []),
            authors: self::authors($publication['authors'] ?? []),
            publishedAt: self::date($publication['datePublished'] ?? ''),
            doi: (string) ($publication['doiObject']['doi'] ?? ($publication['pub-id::doi'] ?? '')),
            articleUrl: (string) ($submission['urlPublished'] ?? ''),
            pdfUrl: self::pdfUrl($publication['galleys'] ?? []),
            references: self::references($publication),
            publisher: $journal->publisher,
            issueTitle: Text::truncate((string) Localized::pick($issue['title'] ?? '', isset($issue['title']) ? '' : 'Untitled issue')),
            issueIdentification: (string) ($issue['identification'] ?? ''),
            issueYear: (string) ($issue['year'] ?? ''),
            issueUrl: (string) ($issue['publishedUrl'] ?? ''),
        );
    }

    /**
     * OJS 3.4+ returns each keyword as ['name' => ...]; older versions return plain strings.
     *
     * @return list<string>
     */
    private static function keywords(mixed $field): array
    {
        $keywords = (array) Localized::pick($field, []);

        return array_values(array_filter(array_map(
            static fn (mixed $k): string => trim((string) (is_array($k) ? ($k['name'] ?? '') : $k)),
            $keywords,
        )));
    }

    /**
     * @param array<mixed> $authors
     * @return list<string>
     */
    private static function authors(array $authors): array
    {
        $names = [];
        foreach ($authors as $author) {
            $names[] = trim(Localized::pick($author['givenName'] ?? '') . ' ' . Localized::pick($author['familyName'] ?? ''));
        }

        return array_values(array_filter($names));
    }

    /**
     * @param array<mixed> $galleys
     */
    private static function pdfUrl(array $galleys): string
    {
        foreach ($galleys as $galley) {
            if (stripos((string) ($galley['label'] ?? ''), 'pdf') !== false) {
                return (string) ($galley['urlPublished'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $publication
     */
    private static function references(array $publication): string
    {
        $raw = $publication['references'] ?? null;
        $text = is_array($raw) && $raw !== []
            ? implode("\n", array_map(static fn (mixed $r): string => is_array($r) ? (string) ($r['rawCitation'] ?? '') : (string) $r, $raw))
            : (string) ($publication['citationsRaw'] ?? '');

        // Drop inline styles/attributes that OJS editors paste into <span> tags.
        return (string) preg_replace('/<span[^>]*>/i', '<span>', $text);
    }

    private static function date(string $value): string
    {
        $timestamp = $value !== '' ? strtotime($value) : false;

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '';
    }
}
