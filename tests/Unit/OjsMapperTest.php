<?php

declare(strict_types=1);

namespace JournalHarvester\Tests\Unit;

use JournalHarvester\Ojs\OjsJournal;
use JournalHarvester\Ojs\OjsMapper;
use PHPUnit\Framework\TestCase;

final class OjsMapperTest extends TestCase
{
    private OjsJournal $journal;

    protected function setUp(): void
    {
        $this->journal = OjsJournal::fromArray([
            'code' => 'jhs',
            'name' => 'Journal of Health Sciences',
            'publisher' => 'Example University Press',
            'api_url' => 'https://journals.example.org/index.php/jhs/api/v1/',
            'token' => 'dummy',
        ]);
    }

    public function testMapsOjs34Publication(): void
    {
        $publication = self::fixture('ojs-publication-3.4.json');
        $submission = ['id' => 77, 'currentPublicationId' => 501, 'urlPublished' => 'https://journals.example.org/index.php/jhs/article/view/77'];
        $issue = ['id' => 12, 'title' => ['en' => 'Regular issue'], 'identification' => 'v. 8 n. 1 (2025)', 'year' => 2025, 'publishedUrl' => 'https://journals.example.org/index.php/jhs/issue/view/12'];

        $row = array_combine(
            \JournalHarvester\Output\Article::COLUMNS,
            OjsMapper::toArticle($this->journal, $submission, $publication, $issue)->toRow(),
        );

        self::assertSame('jhs-art_12_77_501', $row['unique_id']);
        self::assertSame('Open data in public health research', $row['title'], 'empty pt_BR title falls back to en');
        self::assertSame('We describe an open data workflow.', $row['abstract'], '"<p></p>" is not a real abstract');
        self::assertSame('open data; repositories', $row['keywords'], 'OJS 3.4 keyword objects, empty ones dropped');
        self::assertSame('Maria Oliveira; João Pereira', $row['authors']);
        self::assertSame('2025-03-10 00:00:00', $row['published_at']);
        self::assertSame('10.0000/example.2025.501', $row['doi']);
        self::assertStringEndsWith('/77/121', $row['pdf_url'], 'first galley labelled PDF');
        self::assertSame('<span>Doe J. Example reference. 2020.</span>', $row['references'], 'inline styles removed');
        self::assertSame('Regular issue', $row['issue_title']);
        self::assertSame('2025', $row['issue_year']);
    }

    public function testMapsOjs33Publication(): void
    {
        $publication = self::fixture('ojs-publication-3.3.json');
        $submission = ['id' => 40, 'currentPublicationId' => 300];
        $issue = ['id' => 9, 'year' => 2024];

        $row = array_combine(
            \JournalHarvester\Output\Article::COLUMNS,
            OjsMapper::toArticle($this->journal, $submission, $publication, $issue)->toRow(),
        );

        self::assertSame('Educação a distância no ensino técnico', $row['title']);
        self::assertSame('educação; ensino técnico', $row['keywords'], 'plain string keywords, pt_BR first');
        self::assertSame('10.0000/example.2024.300', $row['doi'], 'legacy pub-id::doi');
        self::assertSame("Ref one.\nRef two.", $row['references']);
        self::assertSame('', $row['pdf_url']);
        self::assertSame('Untitled issue', $row['issue_title']);
    }

    public function testJournalConfigRequiresToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OjsJournal::fromArray(['code' => 'x', 'name' => 'X', 'api_url' => 'https://x.example.org/api/v1', 'token' => '']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__ . "/../fixtures/{$name}"), true, flags: JSON_THROW_ON_ERROR);
    }
}
