<?php

declare(strict_types=1);

namespace JournalHarvester\Tests\Unit;

use JournalHarvester\Scielo\ScieloJournal;
use JournalHarvester\Scielo\ScieloParser;
use PHPUnit\Framework\TestCase;

final class ScieloParserTest extends TestCase
{
    private ScieloJournal $journal;

    protected function setUp(): void
    {
        $this->journal = new ScieloJournal('abc', 'Example Journal', 'Example Society');
    }

    public function testIssueLinksAreAbsoluteAndKeepOnlyThisJournal(): void
    {
        $issues = ScieloParser::issueLinks(self::fixture('scielo-grid.html'), $this->journal);

        self::assertSame([
            'https://www.scielo.br/j/abc/i/2025.v31n2/' => 2025,
            'https://www.scielo.br/j/abc/i/2025.v31n1/' => 2025,
            'https://www.scielo.br/j/abc/i/2025.v31suppl1/' => 2025,
            'https://www.scielo.br/j/abc/i/2024.v30n4/' => 2024,
            'https://www.scielo.br/j/abc/i/2023.v29n1/' => 2023,
        ], $issues);
    }

    public function testIssueIdentification(): void
    {
        self::assertSame(['year' => '2025', 'identification' => 'v. 31 n. 2 (2025)'], ScieloParser::issueIdentification('https://www.scielo.br/j/abc/i/2025.v31n2/'));
        self::assertSame(['year' => '2025', 'identification' => 'v. 31 suppl. 1 (2025)'], ScieloParser::issueIdentification('https://www.scielo.br/j/abc/i/2025.v31suppl1/'));
        self::assertSame(['year' => '2024', 'identification' => 'v. 30 n. 2 suppl. 1 (2024)'], ScieloParser::issueIdentification('https://www.scielo.br/j/abc/i/2024.v30n2suppl1/'));
        self::assertSame(['year' => '2024', 'identification' => 'v. 30 n. spe (2024)'], ScieloParser::issueIdentification('https://www.scielo.br/j/abc/i/2024.v30nspe/'));
        self::assertSame(['year' => '2023', 'identification' => 'v. 29 (2023)'], ScieloParser::issueIdentification('https://www.scielo.br/j/abc/i/2023.v29/'));
        self::assertSame(['year' => '', 'identification' => ''], ScieloParser::issueIdentification('https://www.scielo.br/about/'));
    }

    public function testArticleLinksAreGroupedByPidAndPreferPortuguese(): void
    {
        $links = ScieloParser::articleLinks(self::fixture('scielo-issue.html'), $this->journal);

        self::assertSame(['Xk9pQrT3mN', 'Bb7yZwQ1kL'], array_keys($links), 'PDF links ignored, one entry per article');
        self::assertStringContainsString('lang=en', $links['Xk9pQrT3mN']['url'], 'first link is the one recorded');
        self::assertStringContainsString('lang=pt', $links['Xk9pQrT3mN']['read_from'], 'metadata read from the pt version');
        self::assertSame($links['Bb7yZwQ1kL']['url'], $links['Bb7yZwQ1kL']['read_from'], '"ilang=pt_BR" alone does not mean Portuguese');
    }

    public function testArticleMetadata(): void
    {
        $meta = ScieloParser::articleMetadata(self::fixture('scielo-article.html'));

        self::assertNotNull($meta);
        self::assertSame('Saúde digital em comunidades rurais: um estudo de caso', $meta['title']);
        self::assertSame('10.1590/0000-0000.2025.0001', $meta['doi']);
        self::assertSame(['Silva, Ana', 'Souza, Bruno'], $meta['authors']);
        self::assertSame(['saúde digital', 'telemedicina'], $meta['keywords']);
        self::assertStringStartsWith('Resumo Este estudo', $meta['abstract']);
    }

    public function testErrorPageHasNoMetadata(): void
    {
        self::assertNull(ScieloParser::articleMetadata(self::fixture('scielo-error.html')));
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . "/../fixtures/{$name}");
    }
}
