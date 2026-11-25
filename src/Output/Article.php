<?php

declare(strict_types=1);

namespace JournalHarvester\Output;

/**
 * One harvested article, flattened for CSV output.
 *
 * $uniqueId is stable across runs, so it can be used as the Drupal Feeds
 * "GUID" to update existing nodes instead of creating duplicates.
 */
final class Article
{
    public const COLUMNS = [
        'journal', 'unique_id', 'issue_id', 'article_id', 'publication_id',
        'title', 'abstract', 'keywords', 'authors', 'published_at',
        'doi', 'article_url', 'pdf_url', 'references', 'publisher',
        'issue_title', 'issue_identification', 'issue_year', 'issue_url',
    ];

    /**
     * @param list<string> $keywords
     * @param list<string> $authors
     */
    public function __construct(
        public readonly string $journal,
        public readonly string $uniqueId,
        public readonly string $issueId,
        public readonly string $articleId,
        public readonly string $publicationId,
        public readonly string $title,
        public readonly string $abstract,
        public readonly array $keywords,
        public readonly array $authors,
        public readonly string $publishedAt,
        public readonly string $doi,
        public readonly string $articleUrl,
        public readonly string $pdfUrl,
        public readonly string $references,
        public readonly string $publisher,
        public readonly string $issueTitle,
        public readonly string $issueIdentification,
        public readonly string $issueYear,
        public readonly string $issueUrl,
    ) {
    }

    /**
     * @return list<string>
     */
    public function toRow(): array
    {
        return [
            $this->journal, $this->uniqueId, $this->issueId, $this->articleId, $this->publicationId,
            $this->title, $this->abstract,
            implode('; ', array_filter($this->keywords)),
            implode('; ', array_filter($this->authors)),
            $this->publishedAt, $this->doi, $this->articleUrl, $this->pdfUrl, $this->references, $this->publisher,
            $this->issueTitle, $this->issueIdentification, $this->issueYear, $this->issueUrl,
        ];
    }
}
