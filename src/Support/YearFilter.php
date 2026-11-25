<?php

declare(strict_types=1);

namespace JournalHarvester\Support;

/**
 * Decides which issues to harvest based on their publication year.
 *
 * By default only the latest year found in the journal and the year before it
 * are kept, which is what a nightly sync needs. A fixed range can be forced.
 */
final class YearFilter
{
    public function __construct(
        private readonly int $latestYears = 2,
        private readonly ?int $from = null,
        private readonly ?int $to = null,
    ) {
        if ($latestYears < 1) {
            throw new \InvalidArgumentException('latestYears must be at least 1.');
        }
    }

    /**
     * @param list<int> $yearsInJournal Every issue year found in the journal.
     * @return list<int> The years to harvest.
     */
    public function selectYears(array $yearsInJournal): array
    {
        $years = array_values(array_unique(array_filter($yearsInJournal, static fn (int $y): bool => $y > 0)));
        if ($years === []) {
            return [];
        }

        if ($this->from !== null || $this->to !== null) {
            $from = $this->from ?? min($years);
            $to = $this->to ?? max($years);

            return array_values(array_filter($years, static fn (int $y): bool => $y >= $from && $y <= $to));
        }

        $latest = max($years);

        return array_values(array_filter($years, fn (int $y): bool => $y > $latest - $this->latestYears));
    }
}
