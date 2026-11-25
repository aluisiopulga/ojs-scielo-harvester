<?php

declare(strict_types=1);

namespace JournalHarvester\Scielo;

use JournalHarvester\Http\HttpClient;
use JournalHarvester\Output\Article;
use JournalHarvester\Output\CsvWriter;
use JournalHarvester\Support\FuzzyDate;
use JournalHarvester\Support\Text;
use JournalHarvester\Support\YearFilter;

/**
 * Walks grid -> issues -> articles for one SciELO journal.
 */
final class ScieloHarvester
{
    /** @var callable(string): void */
    private $progress;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param callable(string): void|null $progress
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly ScieloJournal $journal,
        private readonly YearFilter $years,
        ?callable $progress = null,
    ) {
        $this->progress = $progress ?? static function (string $s): void {};
    }

    public function harvest(CsvWriter $writer): int
    {
        $grid = $this->http->get($this->journal->gridUrl());
        if (!$grid->ok()) {
            $this->warnings[] = "HTTP {$grid->status} on {$this->journal->gridUrl()}";

            return 0;
        }

        $issues = ScieloParser::issueLinks($grid->body, $this->journal);
        $selectedYears = $this->years->selectYears(array_values($issues));

        $written = 0;
        foreach ($issues as $issueUrl => $year) {
            if (!in_array($year, $selectedYears, true)) {
                continue;
            }
            $issuePage = $this->http->get($issueUrl);
            if (!$issuePage->ok()) {
                $this->warnings[] = "HTTP {$issuePage->status} on {$issueUrl}";
                continue;
            }

            $issueInfo = ScieloParser::issueIdentification($issueUrl);
            foreach (ScieloParser::articleLinks($issuePage->body, $this->journal) as $pid => $link) {
                $uniqueId = "{$this->journal->acronym}-{$pid}";
                if ($writer->has($uniqueId)) {
                    continue;
                }

                $html = $this->fetchArticle($link['read_from'], $link['url']);
                $metadata = $html !== null ? ScieloParser::articleMetadata($html) : null;
                if ($metadata === null) {
                    ($this->progress)('x');
                    continue;
                }

                $article = new Article(
                    journal: $this->journal->name,
                    uniqueId: $uniqueId,
                    issueId: $issueInfo['year'],
                    articleId: $pid,
                    publicationId: $pid,
                    title: Text::truncate($metadata['title']),
                    abstract: Text::stripAbstractLabel(Text::clean($metadata['abstract'])),
                    keywords: $metadata['keywords'],
                    authors: $metadata['authors'],
                    publishedAt: FuzzyDate::find($html, $issueInfo['year']) ?: ($issueInfo['year'] !== '' ? "{$issueInfo['year']}-01-01 00:00:00" : ''),
                    doi: $metadata['doi'],
                    articleUrl: $link['url'],
                    pdfUrl: $metadata['pdf_url'],
                    references: '',
                    publisher: $this->journal->publisher,
                    issueTitle: Text::truncate("{$this->journal->acronym} {$issueInfo['identification']}"),
                    issueIdentification: $issueInfo['identification'],
                    issueYear: $issueInfo['year'],
                    issueUrl: $issueUrl,
                );

                if ($writer->write($article)) {
                    $written++;
                    ($this->progress)('.');
                }
            }
        }

        return $written;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Reads the preferred-language page first, falls back to the recorded URL,
     * and retries once after a pause when the page comes back without metadata.
     */
    private function fetchArticle(string $readFrom, string $fallback): ?string
    {
        $attempts = $readFrom !== $fallback ? [$readFrom, $fallback] : [$readFrom];
        $attempts[] = $fallback; // last chance, after a pause

        foreach ($attempts as $i => $url) {
            if ($i === count($attempts) - 1) {
                sleep(3);
            }
            $response = $this->http->get($url);
            if ($response->ok() && ScieloParser::articleMetadata($response->body) !== null) {
                return $response->body;
            }
        }

        return null;
    }
}
