<?php

declare(strict_types=1);

namespace JournalHarvester\Ojs;

use JournalHarvester\Output\CsvWriter;
use JournalHarvester\Support\YearFilter;

/**
 * Harvests every published article of the selected years from one OJS journal.
 */
final class OjsHarvester
{
    /** @var callable(string): void */
    private $progress;

    /**
     * @param callable(string): void|null $progress Receives short progress strings (e.g. ".").
     */
    public function __construct(
        private readonly OjsClient $client,
        private readonly OjsJournal $journal,
        private readonly YearFilter $years,
        ?callable $progress = null,
    ) {
        $this->progress = $progress ?? static function (string $s): void {};
    }

    /**
     * @return int Number of articles written.
     */
    public function harvest(CsvWriter $writer): int
    {
        $issues = $this->client->publishedIssues();
        $selectedYears = $this->years->selectYears(array_map(static fn (array $i): int => (int) ($i['year'] ?? 0), $issues));

        $written = 0;
        foreach ($issues as $issueSummary) {
            if (!in_array((int) ($issueSummary['year'] ?? 0), $selectedYears, true)) {
                continue;
            }

            $issue = $this->client->issue((int) $issueSummary['id']) ?: $issueSummary;
            foreach ($this->client->publishedSubmissionsInIssue((int) $issueSummary['id']) as $submission) {
                $submissionId = (int) ($submission['id'] ?? 0);
                $publicationId = (int) ($submission['currentPublicationId'] ?? 0);
                $uniqueId = "{$this->journal->code}-art_{$issueSummary['id']}_{$submissionId}_{$publicationId}";
                if ($submissionId === 0 || $publicationId === 0 || $writer->has($uniqueId)) {
                    continue;
                }

                $publication = $this->client->publication($submissionId, $publicationId);
                if ($publication === []) {
                    ($this->progress)('x');
                    continue;
                }

                if ($writer->write(OjsMapper::toArticle($this->journal, $submission, $publication, $issue))) {
                    $written++;
                    ($this->progress)('.');
                }
            }
        }

        return $written;
    }
}
