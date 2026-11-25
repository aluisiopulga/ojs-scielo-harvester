<?php

declare(strict_types=1);

namespace JournalHarvester\Http;

/**
 * Thin cURL wrapper with TLS verification, a polite delay between requests
 * and retries with exponential backoff on network errors, 429 and 5xx.
 */
class HttpClient
{
    private float $lastRequestAt = 0.0;

    /**
     * @param float       $delaySeconds Minimum pause between two requests.
     * @param int         $maxRetries   Extra attempts after the first failure.
     * @param string|null $caBundle     Optional CA bundle path (useful on Windows/WampServer).
     */
    public function __construct(
        private readonly string $userAgent = 'ojs-scielo-harvester/1.0 (+https://github.com/aluisiopulga/ojs-scielo-harvester)',
        private readonly float $delaySeconds = 0.5,
        private readonly int $maxRetries = 2,
        private readonly int $timeout = 60,
        private readonly bool $verifyTls = true,
        private readonly ?string $caBundle = null,
        private readonly ?string $cookieJar = null,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        $attempt = 0;
        do {
            $this->throttle();
            $response = $this->send($url, $headers);
            if (!$response->isRetryable() || $attempt >= $this->maxRetries) {
                return $response;
            }
            $attempt++;
            usleep((int) (2 ** $attempt * 500_000));
        } while (true);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function send(string $url, array $headers): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '',
            // Some hosts have broken IPv6 routes that only show up as long timeouts.
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ];
        if ($this->caBundle !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        if ($this->cookieJar !== null) {
            $options[CURLOPT_COOKIEJAR] = $this->cookieJar;
            $options[CURLOPT_COOKIEFILE] = $this->cookieJar;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return new HttpResponse($status, is_string($body) ? $body : '', $error);
    }

    private function throttle(): void
    {
        $elapsed = microtime(true) - $this->lastRequestAt;
        if ($this->lastRequestAt > 0 && $elapsed < $this->delaySeconds) {
            usleep((int) (($this->delaySeconds - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }
}
