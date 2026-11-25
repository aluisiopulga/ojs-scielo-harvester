<?php

declare(strict_types=1);

namespace JournalHarvester\Tests\Unit;

use JournalHarvester\Http\HttpResponse;
use JournalHarvester\Ojs\OjsClient;
use JournalHarvester\Ojs\OjsJournal;
use JournalHarvester\Tests\FakeHttp;
use PHPUnit\Framework\TestCase;

/**
 * Exercises pagination, filtering and diagnostics against a fake HTTP layer.
 */
final class OjsClientTest extends TestCase
{
    public function testPaginatesUntilItemsMaxAndSkipsUnpublishedIssues(): void
    {
        $http = new FakeHttp([
            '/issues?count=100&offset=0' => new HttpResponse(200, json_encode([
                'itemsMax' => 101,
                'items' => array_merge(
                    array_map(static fn (int $i): array => ['id' => $i, 'year' => 2024, 'datePublished' => '2024-01-01'], range(1, 99)),
                    [['id' => 100, 'year' => 2027, 'datePublished' => null]],
                ),
            ])),
            '/issues?count=100&offset=100' => new HttpResponse(200, json_encode([
                'itemsMax' => 101,
                'items' => [['id' => 101, 'year' => 2025, 'datePublished' => '2025-01-01']],
            ])),
        ]);

        $issues = (new OjsClient($http, self::journal()))->publishedIssues();

        self::assertCount(100, $issues, 'issue without datePublished is dropped');
        self::assertSame(101, end($issues)['id']);
        self::assertCount(2, $http->requests);
    }

    public function testTokenGoesInHeaderAndOptionallyInQuery(): void
    {
        $http = new FakeHttp(['*' => new HttpResponse(200, '{"items":[],"itemsMax":0}')]);
        (new OjsClient($http, self::journal(tokenInQuery: true)))->publishedIssues();

        self::assertSame('Bearer secret-token', $http->headers[0]['Authorization']);
        self::assertStringContainsString('apiToken=secret-token', $http->requests[0]);
    }

    public function testCheckAccessExplainsOjsErrorCodes(): void
    {
        $http = new FakeHttp(['*' => new HttpResponse(403, '{"error":"api.403.unauthorized"}')]);
        $result = (new OjsClient($http, self::journal()))->checkAccess();

        self::assertFalse($result['ok']);
        self::assertSame('HTTP 403 - User has no editor/manager role in this journal', $result['detail']);
    }

    public function testCheckAccessFlagsEmptyResults(): void
    {
        $http = new FakeHttp(['*' => new HttpResponse(200, '{"items":[],"itemsMax":0}')]);
        $result = (new OjsClient($http, self::journal()))->checkAccess();

        self::assertFalse($result['ok']);
        self::assertStringContainsString('no published items', $result['detail']);
    }

    public function testWarningsNeverContainTheToken(): void
    {
        $http = new FakeHttp(['*' => new HttpResponse(500, 'boom')]);
        $client = new OjsClient($http, self::journal(tokenInQuery: true));
        $client->publishedIssues();

        self::assertNotEmpty($client->warnings());
        self::assertStringNotContainsString('secret-token', implode(' ', $client->warnings()));
    }

    private static function journal(bool $tokenInQuery = false): OjsJournal
    {
        return new OjsJournal('jhs', 'Journal', 'Press', 'https://journals.example.org/index.php/jhs/api/v1', 'secret-token', $tokenInQuery);
    }
}
