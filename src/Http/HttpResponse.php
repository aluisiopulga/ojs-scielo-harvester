<?php

declare(strict_types=1);

namespace JournalHarvester\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $error = '',
    ) {
    }

    public function ok(): bool
    {
        return $this->status === 200 && $this->body !== '';
    }

    public function isRetryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }

    /**
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }
}
