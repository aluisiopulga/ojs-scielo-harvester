<?php

declare(strict_types=1);

namespace JournalHarvester\Tests\Unit;

use JournalHarvester\Output\Article;
use JournalHarvester\Output\CsvWriter;
use PHPUnit\Framework\TestCase;

final class CsvWriterTest extends TestCase
{
    public function testWritesHeaderAndSkipsDuplicates(): void
    {
        $path = sys_get_temp_dir() . '/harvester-test-' . uniqid() . '/out.csv';
        $writer = new CsvWriter($path);

        self::assertTrue($writer->write(self::article('abc-1', 'First, with comma')));
        self::assertFalse($writer->write(self::article('abc-1', 'Duplicate')));
        self::assertTrue($writer->write(self::article('abc-2', 'Second "quoted"')));
        $writer->close();

        $rows = array_map(static fn (string $l): array => str_getcsv($l, ',', '"', '\\'), file($path, FILE_IGNORE_NEW_LINES) ?: []);

        self::assertSame(Article::COLUMNS, $rows[0]);
        self::assertCount(3, $rows);
        self::assertSame('First, with comma', $rows[1][5]);
        self::assertSame('Second "quoted"', $rows[2][5]);
        self::assertSame('Ana; Bruno', $rows[1][8]);
        self::assertSame(2, $writer->count());

        unlink($path);
        rmdir(dirname($path));
    }

    private static function article(string $id, string $title): Article
    {
        return new Article(
            journal: 'Example', uniqueId: $id, issueId: '1', articleId: $id, publicationId: $id,
            title: $title, abstract: '', keywords: ['k'], authors: ['Ana', 'Bruno'],
            publishedAt: '2025-01-01 00:00:00', doi: '', articleUrl: '', pdfUrl: '', references: '',
            publisher: '', issueTitle: '', issueIdentification: '', issueYear: '2025', issueUrl: '',
        );
    }
}
