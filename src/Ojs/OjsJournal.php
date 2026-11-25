<?php

declare(strict_types=1);

namespace JournalHarvester\Ojs;

/**
 * Connection settings for one OJS journal.
 */
final class OjsJournal
{
    /**
     * @param string $code         Short code used to build unique IDs (e.g. "jhs").
     * @param string $apiUrl       Base REST URL, e.g. https://journal.example.org/index.php/jhs/api/v1
     * @param bool   $tokenInQuery Also send the token as ?apiToken=. Needed when the web server
     *                             strips the Authorization header (common with Apache + PHP-FPM),
     *                             but the token then shows up in server access logs.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $publisher,
        public readonly string $apiUrl,
        public readonly string $token,
        public readonly bool $tokenInQuery = false,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['code', 'name', 'api_url', 'token'] as $key) {
            if (empty($config[$key]) || !is_string($config[$key])) {
                throw new \InvalidArgumentException("OJS journal config is missing \"{$key}\".");
            }
        }

        return new self(
            code: $config['code'],
            name: $config['name'],
            publisher: (string) ($config['publisher'] ?? ''),
            apiUrl: rtrim($config['api_url'], '/'),
            token: $config['token'],
            tokenInQuery: (bool) ($config['token_in_query'] ?? false),
        );
    }
}
