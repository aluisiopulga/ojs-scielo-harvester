<?php

declare(strict_types=1);

namespace JournalHarvester\Scielo;

/**
 * Parses SciELO HTML pages. Pure functions over HTML strings, so every rule can be
 * tested against saved pages without touching the network.
 */
final class ScieloParser
{
    /**
     * Issue links from the journal grid page, as absolute URLs, keyed by URL with the issue year as value.
     *
     * @return array<string, int>
     */
    public static function issueLinks(string $gridHtml, ScieloJournal $journal): array
    {
        $issues = [];
        foreach (self::hrefs($gridHtml) as $href) {
            if (!str_contains($href, $journal->issuePathPrefix())) {
                continue;
            }
            $url = self::absolute($href, $journal);
            // Issue URLs look like /j/abc/i/2025.v31n2/ — the year comes first.
            $issues[$url] = preg_match('#/i/(\d{4})\.#', $url, $m) ? (int) $m[1] : 0;
        }

        return $issues;
    }

    /**
     * Builds a readable identification such as "v. 31 n. 2 (2025)" from an issue URL.
     *
     * @return array{year: string, identification: string}
     */
    public static function issueIdentification(string $issueUrl): array
    {
        // 2025.v31n2 / 2025.v31nspe / 2025.v31suppl1 / 2025.v31n2suppl1
        if (!preg_match('#/i/(\d{4})\.v(\d+)(?:n([0-9a-zA-Z.\-]+?))?(?:suppl(\d*))?/?$#', $issueUrl, $m)) {
            return ['year' => '', 'identification' => ''];
        }
        $identification = "v. {$m[2]}"
            . (($m[3] ?? '') !== '' ? " n. {$m[3]}" : '')
            . (isset($m[4]) ? ' suppl.' . ($m[4] !== '' ? " {$m[4]}" : '') : '')
            . " ({$m[1]})";

        return ['year' => $m[1], 'identification' => $identification];
    }

    /**
     * Article links found on an issue page, grouped by article PID.
     *
     * Each article appears under 2–3 links (languages, abstract only). The returned
     * map holds the URL to record and, when offered, the Portuguese version used to
     * read metadata.
     *
     * @return array<string, array{url: string, read_from: string}>
     */
    public static function articleLinks(string $issueHtml, ScieloJournal $journal, string $preferredLang = 'pt'): array
    {
        $articles = [];
        foreach (self::hrefs($issueHtml) as $href) {
            if (!str_contains($href, $journal->articlePathPrefix()) || str_contains($href, 'format=')) {
                continue;
            }
            $pid = self::pid($href);
            if ($pid === null) {
                continue;
            }
            $url = self::absolute($href, $journal);
            $articles[$pid] ??= ['url' => $url, 'read_from' => $url];
            // "[?&]" matters: every link also carries "ilang=pt_BR" (interface language).
            if (preg_match('/[?&]lang=' . preg_quote($preferredLang, '/') . '(&|$)/', $href)) {
                $articles[$pid]['read_from'] = $url;
            }
        }

        return $articles;
    }

    /**
     * Reads the Highwire-style citation_* meta tags of an article page.
     * Returns null when there is no title: SciELO error pages (e.g. HTTP 500 with
     * a 200 body) have no metadata, and writing them would wipe fields in Drupal.
     *
     * @return array{title: string, abstract: string, doi: string, pdf_url: string, authors: list<string>, keywords: list<string>}|null
     */
    public static function articleMetadata(string $articleHtml): ?array
    {
        $xpath = self::xpath($articleHtml);
        $title = self::meta($xpath, 'citation_title');
        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            // SciELO uses citation_abstract; "description" is a fallback for other layouts.
            'abstract' => self::meta($xpath, 'citation_abstract') ?: self::meta($xpath, 'description'),
            'doi' => self::meta($xpath, 'citation_doi'),
            'pdf_url' => self::meta($xpath, 'citation_pdf_url'),
            'authors' => self::metaList($xpath, 'citation_author'),
            'keywords' => self::metaList($xpath, 'citation_keywords'),
        ];
    }

    public static function pid(string $url): ?string
    {
        return preg_match('#/a/([a-zA-Z0-9]+)#', $url, $m) ? $m[1] : null;
    }

    /**
     * @return list<string>
     */
    private static function hrefs(string $html): array
    {
        $hrefs = [];
        foreach (self::xpath($html)->query('//a[@href]') ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $hrefs[] = $node->getAttribute('href');
            }
        }

        return $hrefs;
    }

    private static function absolute(string $href, ScieloJournal $journal): string
    {
        return str_starts_with($href, 'http') ? $href : $journal->baseUrl . '/' . ltrim($href, '/');
    }

    private static function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    private static function meta(\DOMXPath $xpath, string $name): string
    {
        $node = $xpath->query("//meta[@name='{$name}']")?->item(0);

        return $node instanceof \DOMElement ? trim($node->getAttribute('content')) : '';
    }

    /**
     * @return list<string>
     */
    private static function metaList(\DOMXPath $xpath, string $name): array
    {
        $values = [];
        foreach ($xpath->query("//meta[@name='{$name}']") ?: [] as $node) {
            if ($node instanceof \DOMElement && trim($node->getAttribute('content')) !== '') {
                $values[] = trim($node->getAttribute('content'));
            }
        }

        return $values;
    }
}
