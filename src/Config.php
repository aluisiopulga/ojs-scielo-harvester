<?php

declare(strict_types=1);

namespace JournalHarvester;

use JournalHarvester\Http\HttpClient;
use JournalHarvester\Ojs\OjsJournal;
use JournalHarvester\Scielo\ScieloJournal;

/**
 * Loads config/journals.php (a plain PHP file returning an array).
 *
 * Keep real tokens out of version control: config/journals.php is git-ignored,
 * and the example reads tokens from environment variables.
 */
final class Config
{
    /**
     * @param list<OjsJournal>     $ojs
     * @param list<ScieloJournal>  $scielo
     * @param array<string, mixed> $http
     */
    private function __construct(
        public readonly array $ojs,
        public readonly array $scielo,
        private readonly array $http,
    ) {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: {$path} (copy config/journals.example.php to get started)");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }

        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ojs: array_map([OjsJournal::class, 'fromArray'], array_values($data['ojs'] ?? [])),
            scielo: array_map([ScieloJournal::class, 'fromArray'], array_values($data['scielo'] ?? [])),
            http: (array) ($data['http'] ?? []),
        );
    }

    public function httpClient(?string $cookieJar = null): HttpClient
    {
        $h = $this->http;
        $named = array_filter([
            'userAgent' => isset($h['user_agent']) ? (string) $h['user_agent'] : null,
            'delaySeconds' => isset($h['delay_seconds']) ? (float) $h['delay_seconds'] : null,
            'maxRetries' => isset($h['max_retries']) ? (int) $h['max_retries'] : null,
            'timeout' => isset($h['timeout']) ? (int) $h['timeout'] : null,
            'verifyTls' => isset($h['verify_tls']) ? (bool) $h['verify_tls'] : null,
            'caBundle' => isset($h['ca_bundle']) ? (string) $h['ca_bundle'] : null,
            'cookieJar' => $cookieJar,
        ], static fn (mixed $v): bool => $v !== null);

        return new HttpClient(...$named);
    }
}
