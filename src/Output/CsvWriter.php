<?php

declare(strict_types=1);

namespace JournalHarvester\Output;

/**
 * Writes articles to a UTF-8 CSV file and skips any unique ID already written.
 */
final class CsvWriter
{
    /** @var resource */
    private $handle;

    /** @var array<string, true> */
    private array $written = [];

    private int $count = 0;

    public function __construct(private readonly string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create output directory: {$dir}");
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open output file: {$path}");
        }
        $this->handle = $handle;
        fputcsv($this->handle, Article::COLUMNS, ',', '"', '\\');
    }

    /**
     * @return bool False when the article was skipped as a duplicate.
     */
    public function write(Article $article): bool
    {
        if (isset($this->written[$article->uniqueId])) {
            return false;
        }

        $this->written[$article->uniqueId] = true;
        fputcsv($this->handle, $article->toRow(), ',', '"', '\\');
        $this->count++;

        return true;
    }

    public function has(string $uniqueId): bool
    {
        return isset($this->written[$uniqueId]);
    }

    public function count(): int
    {
        return $this->count;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
