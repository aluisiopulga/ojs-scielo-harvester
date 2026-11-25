<?php

declare(strict_types=1);

namespace JournalHarvester\Scielo;

/**
 * Settings for one journal hosted on a SciELO site (new "/j/{acronym}/" layout).
 */
final class ScieloJournal
{
    public function __construct(
        public readonly string $acronym,
        public readonly string $name,
        public readonly string $publisher,
        public readonly string $baseUrl = 'https://www.scielo.br',
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['acronym', 'name'] as $key) {
            if (empty($config[$key]) || !is_string($config[$key])) {
                throw new \InvalidArgumentException("SciELO journal config is missing \"{$key}\".");
            }
        }

        return new self(
            acronym: $config['acronym'],
            name: $config['name'],
            publisher: (string) ($config['publisher'] ?? ''),
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://www.scielo.br'), '/'),
        );
    }

    public function gridUrl(): string
    {
        return "{$this->baseUrl}/j/{$this->acronym}/grid";
    }

    public function issuePathPrefix(): string
    {
        return "/j/{$this->acronym}/i/";
    }

    public function articlePathPrefix(): string
    {
        return "/j/{$this->acronym}/a/";
    }
}
