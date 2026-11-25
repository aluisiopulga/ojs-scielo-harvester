<?php

declare(strict_types=1);

namespace JournalHarvester\Ojs;

use JournalHarvester\Http\HttpClient;
use JournalHarvester\Http\HttpResponse;

/**
 * Minimal client for the OJS 3.3+ REST API (issues, submissions, publications).
 *
 * OJS caps every page at 100 items whatever "count" says, so lists are paginated
 * with offset until itemsMax is reached.
 */
final class OjsClient
{
    private const PAGE_SIZE = 100;
    private const STATUS_PUBLISHED = 3;

    /**
     * OJS answers 403 both for a dead token and for a user without a role in the
     * journal. Only the "error" key in the response body tells them apart.
     */
    private const ERROR_MESSAGES = [
        'api.400.invalidApiToken' => 'Token revoked or regenerated (signature mismatch)',
        'api.400.tokenCouldNotBeDecoded' => 'Malformed token (not a readable JWT)',
        'api.401.invalidToken' => 'Invalid or expired token',
        'api.403.unauthorized' => 'User has no editor/manager role in this journal',
        'api.500.apiSecretKeyMissing' => 'Server has no api_key_secret in config.inc.php',
    ];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly HttpClient $http,
        private readonly OjsJournal $journal,
    ) {
    }

    /**
     * Published issues only: an issue without datePublished (e.g. a future volume)
     * must not define the "latest year".
     *
     * @return list<array<string, mixed>>
     */
    public function publishedIssues(): array
    {
        $issues = $this->paginate('/issues');

        return array_values(array_filter($issues, static fn (array $issue): bool => !empty($issue['datePublished'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(int $issueId): array
    {
        return $this->request("/issues/{$issueId}") ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publishedSubmissionsInIssue(int $issueId): array
    {
        return $this->paginate('/submissions', ['issueIds[]' => $issueId, 'status[]' => self::STATUS_PUBLISHED]);
    }

    /**
     * @return array<string, mixed>
     */
    public function publication(int $submissionId, int $publicationId): array
    {
        return $this->request("/submissions/{$submissionId}/publications/{$publicationId}") ?? [];
    }

    /**
     * Checks that the token works AND can see published content. Connecting is not
     * enough: without an editor role the API answers 200 with an empty list.
     *
     * @return array{ok: bool, detail: string}
     */
    public function checkAccess(): array
    {
        $response = $this->send('/submissions', ['count' => 1, 'status[]' => self::STATUS_PUBLISHED]);
        $data = $response->json();

        if ($response->status === 0) {
            return ['ok' => false, 'detail' => 'Network/DNS error: ' . ($response->error ?: 'timeout')];
        }

        if ($response->status === 200) {
            if ($data === null || !(isset($data['items']) || isset($data['itemsMax']))) {
                return ['ok' => false, 'detail' => 'HTTP 200 but the response is not the expected JSON'];
            }
            $total = (int) ($data['itemsMax'] ?? count($data['items'] ?? []));

            return $total > 0
                ? ['ok' => true, 'detail' => "HTTP 200 - {$total} published items"]
                : ['ok' => false, 'detail' => 'Connected, but no published items are visible'];
        }

        $apiError = (string) ($data['error'] ?? '');
        if ($apiError !== '') {
            return ['ok' => false, 'detail' => "HTTP {$response->status} - " . (self::ERROR_MESSAGES[$apiError] ?? $apiError)];
        }

        return ['ok' => false, 'detail' => "HTTP {$response->status} - Request failed"];
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param array<string, scalar> $query
     * @return list<array<string, mixed>>
     */
    private function paginate(string $path, array $query = []): array
    {
        $items = [];
        $offset = 0;
        do {
            $page = $this->request($path, $query + ['count' => self::PAGE_SIZE, 'offset' => $offset]);
            if (empty($page['items'])) {
                break;
            }
            array_push($items, ...$page['items']);
            $offset += self::PAGE_SIZE;
        } while ($offset < (int) ($page['itemsMax'] ?? 0));

        return $items;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>|null
     */
    private function request(string $path, array $query = []): ?array
    {
        $response = $this->send($path, $query);
        $data = $response->json();
        if ($response->status !== 200 || $data === null) {
            // Report the path only: never print the token.
            $this->warnings[] = "HTTP {$response->status} on {$this->journal->code}{$path}";

            return null;
        }

        return $data;
    }

    /**
     * @param array<string, scalar> $query
     */
    private function send(string $path, array $query): HttpResponse
    {
        if ($this->journal->tokenInQuery) {
            $query['apiToken'] = $this->journal->token;
        }
        $url = $this->journal->apiUrl . $path . ($query ? '?' . http_build_query($query) : '');
        // http_build_query encodes "[]" as "%5B%5D"; OJS accepts both, keep it readable.
        $url = str_replace(['%5B', '%5D'], ['[', ']'], $url);

        return $this->http->get($url, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->journal->token,
        ]);
    }
}
