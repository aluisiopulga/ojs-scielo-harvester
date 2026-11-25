<?php

declare(strict_types=1);

namespace JournalHarvester\Tests\Unit;

use JournalHarvester\Support\FuzzyDate;
use JournalHarvester\Support\Localized;
use JournalHarvester\Support\Text;
use JournalHarvester\Support\YearFilter;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function testTruncateIsMultibyteSafe(): void
    {
        $text = str_repeat('ção ', 100);
        $result = Text::truncate($text, 20);

        self::assertSame(20, mb_strlen($result));
        self::assertStringEndsWith('...', $result);
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testCleanStripsTagsAndEntities(): void
    {
        self::assertSame('Saúde & Dados', Text::clean('<p>Sa&uacute;de &amp; <b>Dados</b></p>'));
    }

    public function testHasContentIgnoresPlaceholders(): void
    {
        self::assertFalse(Text::hasContent('.'));
        self::assertFalse(Text::hasContent('<p></p>'));
        self::assertFalse(Text::hasContent([]));
        self::assertTrue(Text::hasContent('a'));
        self::assertTrue(Text::hasContent(['x']));
    }

    public function testLocalizedPrefersPortugueseThenEnglish(): void
    {
        self::assertSame('Olá', Localized::pick(['en' => 'Hi', 'pt_BR' => 'Olá']));
        self::assertSame('Hi', Localized::pick(['pt_BR' => '<p></p>', 'en' => 'Hi']));
        self::assertSame('Hi', Localized::pick(['pt_BR' => '', 'en_US' => 'Hi']), 'OJS 3.3 locale keys');
    }

    public function testLocalizedFallsBackToAnyFilledLocale(): void
    {
        self::assertSame('Bonjour', Localized::pick(['pt_BR' => '', 'fr_CA' => 'Bonjour']));
        self::assertSame('default', Localized::pick(['pt_BR' => '.', 'en' => ''], 'default'));
    }

    public function testLocalizedKeepsPlainValuesAndLists(): void
    {
        self::assertSame('Plain', Localized::pick('Plain'));
        self::assertSame(['a', 'b'], Localized::pick(['a', 'b']));
    }

    public function testFuzzyDatePrefersEpubMention(): void
    {
        $html = '<p>Recebido em 2 de janeiro de 2025.</p><p>Epub 14 Mar 2025.</p>';
        self::assertSame('2025-03-14 00:00:00', FuzzyDate::find($html, 2025));
    }

    public function testFuzzyDateSkipsDatesFarFromIssueYear(): void
    {
        $html = 'Fundado em 12 de maio de 1918. Publicado em 3 de setembro de 2024.';
        self::assertSame('2024-09-03 00:00:00', FuzzyDate::find($html, 2025));
    }

    public function testFuzzyDateUnderstandsSpanishAndEnglish(): void
    {
        self::assertSame('2025-12-01 00:00:00', FuzzyDate::find('1 de diciembre de 2025', 2025));
        self::assertSame('2025-08-07 00:00:00', FuzzyDate::find('7 August 2025', 2025));
    }

    public function testFuzzyDateReturnsEmptyWhenNothingFits(): void
    {
        self::assertSame('', FuzzyDate::find('No dates here.', 2025));
        self::assertSame('', FuzzyDate::find('40 mar 2025', 2025));
    }

    public function testYearFilterKeepsLatestYearsByDefault(): void
    {
        $filter = new YearFilter();
        self::assertSame([2025, 2024], $filter->selectYears([2023, 2025, 2024, 2025, 0]));
    }

    public function testYearFilterSupportsExplicitRange(): void
    {
        $filter = new YearFilter(from: 2021, to: 2023);
        self::assertSame([2021, 2022, 2023], $filter->selectYears([2020, 2021, 2022, 2023, 2024]));
    }

    public function testYearFilterRejectsInvalidWindow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new YearFilter(latestYears: 0);
    }
}
