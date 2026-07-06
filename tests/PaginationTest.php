<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\SilonException;
use Silon\Http\Request;

final class PaginationTest extends TestCase
{
    private const EVENTS_PATH = '/api/v1/events';

    private static function event(int $n): array
    {
        return [
            'id' => "evt_{$n}",
            'object' => 'event',
            'type' => 'message.delivered',
            'api_version' => '2026-06-28',
            'created' => '2026-07-01T10:00:00Z',
            'data' => ['id' => "msg_{$n}", 'object' => 'message', 'status' => 'sent'],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $pages keyed by cursor param
     */
    private function pager(array $pages): callable
    {
        return static function (Request $request) use ($pages) {
            $params = [];
            $query = parse_url($request->url, PHP_URL_QUERY);
            if (is_string($query)) {
                parse_str($query, $params);
            }
            $cursor = $params['cursor'] ?? '';

            return MockHttpClient::jsonResponse(200, $pages[$cursor]);
        };
    }

    private function twoPages(): array
    {
        return [
            '' => [
                'results' => [self::event(1), self::event(2)],
                'next' => self::BASE_URL . self::EVENTS_PATH . '?cursor=abc&limit=2',
                'previous' => null,
            ],
            'abc' => [
                'results' => [self::event(3)],
                'next' => null,
                'previous' => self::BASE_URL . self::EVENTS_PATH . '?limit=2',
            ],
        ];
    }

    public function testSinglePageIteration(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::event(1), self::event(2)], 'next' => null, 'previous' => null]);
        $client = $this->makeClient($http);

        $page = $client->events->list();
        $this->assertCount(2, $page);
        $this->assertSame(['evt_1', 'evt_2'], array_map(fn ($e) => $e->id, $page->results));
        $this->assertSame('sent', $page[0]->data->status);
        $this->assertFalse($page->hasNextPage());
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('no next page');
        $page->nextPage();
    }

    public function testManualNextPage(): void
    {
        $http = new MockHttpClient();
        $http->setHandler($this->pager($this->twoPages()));
        $client = $this->makeClient($http);

        $page = $client->events->list(['limit' => 2]);
        $this->assertTrue($page->hasNextPage());
        $page2 = $page->nextPage();
        $this->assertSame(['evt_3'], array_map(fn ($e) => $e->id, $page2->results));
        $this->assertFalse($page2->hasNextPage());
        $params = $this->query($http->last());
        $this->assertSame('abc', $params['cursor']);
        $this->assertSame('2', $params['limit']);
    }

    public function testAutoPagingWalksAllPages(): void
    {
        $http = new MockHttpClient();
        $http->setHandler($this->pager($this->twoPages()));
        $client = $this->makeClient($http);

        $ids = [];
        foreach ($client->events->list(['limit' => 2])->autoPaging() as $event) {
            $ids[] = $event->id;
        }
        $this->assertSame(['evt_1', 'evt_2', 'evt_3'], $ids);
        $this->assertSame(2, $http->callCount());
    }

    public function testAutoPagingIsLazy(): void
    {
        $http = new MockHttpClient();
        $http->setHandler($this->pager($this->twoPages()));
        $client = $this->makeClient($http);

        $generator = $client->events->list(['limit' => 2])->autoPaging();
        $this->assertSame('evt_1', $generator->current()->id);
        $this->assertSame(1, $http->callCount()); // second page not fetched yet
    }

    public function testTypeFilterForwarded(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [], 'next' => null, 'previous' => null]);
        $client = $this->makeClient($http);

        $client->events->list(['type' => 'message.failed', 'limit' => 10]);
        $params = $this->query($http->last());
        $this->assertSame('message.failed', $params['type']);
        $this->assertSame('10', $params['limit']);
    }

    public function testNextUrlWithForeignHostStaysOnBaseUrl(): void
    {
        $http = new MockHttpClient();
        $http->setHandler($this->pager([
            '' => [
                'results' => [self::event(1)],
                'next' => 'https://internal-proxy.local/api/v1/events?cursor=abc',
                'previous' => null,
            ],
            'abc' => ['results' => [self::event(2)], 'next' => null, 'previous' => null],
        ]));
        $client = $this->makeClient($http);

        $ids = [];
        foreach ($client->events->list()->autoPaging() as $event) {
            $ids[] = $event->id;
        }
        $this->assertSame(['evt_1', 'evt_2'], $ids);
        $this->assertSame('acme.silon.tech', parse_url($http->last()->url, PHP_URL_HOST));
        $this->assertSame('abc', $this->query($http->last())['cursor']);
    }

    public function testBroadcastDeliveriesPaginated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'results' => [
                ['id' => 'd1', 'client_id' => 'c1', 'status' => 'sent', 'sent_at' => null, 'error' => null],
            ],
            'next' => null,
            'previous' => null,
        ]);
        $client = $this->makeClient($http);

        $page = $client->broadcasts->deliveries('br_1');
        $this->assertSame('sent', $page[0]->status);
        $this->assertFalse($page->hasNextPage());
    }
}
