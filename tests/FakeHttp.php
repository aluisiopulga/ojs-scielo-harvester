<?php

declare(strict_types=1);

namespace JournalHarvester\Tests;

use JournalHarvester\Http\HttpClient;
use JournalHarvester\Http\HttpResponse;

/**
 * Returns canned responses keyed by path+query (or "*" for any request).
 */
final class FakeHttp extends HttpClient
{
    /** @var list<string> */
    public array $requests = [];

    /** @var list<array<string, string>> */
    public array $headers = [];

    /**
     * @param array<string, HttpResponse> $responses
     */
    public function __construct(private readonly array $responses)
    {
        parent::__construct(delaySeconds: 0, maxRetries: 0);
    }

    protected function send(string $url, array $headers): HttpResponse
    {
        $this->requests[] = $url;
        $this->headers[] = $headers;
        $key = (string) preg_replace('#^https://journals\.example\.org/index\.php/jhs/api/v1#', '', $url);

        return $this->responses[$key] ?? $this->responses['*'] ?? new HttpResponse(404, '');
    }
}
