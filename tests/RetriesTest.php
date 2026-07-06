<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ApiConnectionException;
use Silon\Exception\ApiTimeoutException;
use Silon\Exception\AuthenticationException;
use Silon\Exception\InternalServerException;

final class RetriesTest extends TestCase
{
    /** @var list<float> */
    private array $sleeps = [];

    private function retryingClient(int $maxRetries, MockHttpClient $http): \Silon\Client
    {
        $this->sleeps = [];

        return $this->makeClient($http, [
            'maxRetries' => $maxRetries,
            'sleeper' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ]);
    }

    public function testGetRetriedOn500ThenSucceeds(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(500, '');
        $http->pushJson(200, ['email' => 'a@b.c']);
        $client = $this->retryingClient(2, $http);

        $profile = $client->profile->retrieve();
        $this->assertSame('a@b.c', $profile->email);
        $this->assertSame(2, $http->callCount());
        $this->assertCount(1, $this->sleeps);
    }

    public function testRetryHonoursRetryAfter(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(429, '', ['Retry-After' => '3']);
        $http->pushJson(200, []);
        $client = $this->retryingClient(2, $http);

        $client->profile->retrieve();
        $this->assertSame(2, $http->callCount());
        $this->assertGreaterThanOrEqual(3.0, $this->sleeps[0]);
    }

    public function testConnectionErrorRetried(): void
    {
        $http = new MockHttpClient();
        $http->pushException(false, 'boom');
        $http->pushJson(200, []);
        $client = $this->retryingClient(2, $http);

        $client->profile->retrieve();
        $this->assertSame(2, $http->callCount());
    }

    public function testRetriesExhaustedRaisesLastError(): void
    {
        $http = new MockHttpClient();
        $http->setHandler(fn () => MockHttpClient::jsonResponse(500, ''));
        $client = $this->retryingClient(1, $http);

        $this->expectException(InternalServerException::class);
        try {
            $client->profile->retrieve();
        } finally {
            $this->assertSame(2, $http->callCount());
        }
    }

    public function testConnectionErrorsExhausted(): void
    {
        $http = new MockHttpClient();
        $http->setHandler(fn () => new \Silon\Http\TransportException('down', false));
        $client = $this->retryingClient(1, $http);

        $this->expectException(ApiConnectionException::class);
        try {
            $client->profile->retrieve();
        } finally {
            $this->assertSame(2, $http->callCount());
        }
    }

    public function testTimeoutWithoutRetries(): void
    {
        $http = new MockHttpClient();
        $http->pushException(true, 'timed out');
        $client = $this->retryingClient(0, $http);

        $this->expectException(ApiTimeoutException::class);
        $client->profile->retrieve();
    }

    public function testMaxRetriesZeroNeverRetries(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(500, '');
        $client = $this->retryingClient(0, $http);

        try {
            $client->profile->retrieve();
            $this->fail('expected InternalServerException');
        } catch (InternalServerException) {
            $this->assertSame(1, $http->callCount());
            $this->assertSame([], $this->sleeps);
        }
    }

    public function testPostWithIdempotencyKeyIsRetried(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(503, '');
        $http->pushJson(202, ['id' => 'm1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued']);
        $client = $this->retryingClient(2, $http);

        $sent = $client->messages->send([
            'channel' => 'sms',
            'to' => ['phone_number' => '+1'],
            'content' => ['body' => 'hi'],
        ]);
        $this->assertSame('m1', $sent->id);
        $this->assertSame(2, $http->callCount());
        // The same Idempotency-Key must be replayed so the send cannot double-fire.
        $keys = array_unique(array_map(
            fn ($r) => $r->getHeaderLine('Idempotency-Key'),
            $http->requests,
        ));
        $this->assertCount(1, $keys);
        $this->assertNotSame('', $keys[array_key_first($keys)]);
    }

    public function testPlainPostNeverRetried(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(500, '');
        $client = $this->retryingClient(2, $http);

        try {
            $client->clients->create(['client_id' => 'c1']);
            $this->fail('expected InternalServerException');
        } catch (InternalServerException) {
            $this->assertSame(1, $http->callCount());
            $this->assertSame([], $this->sleeps);
        }
    }

    public function testNonRetryableStatusNotRetried(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(401, []);
        $client = $this->retryingClient(2, $http);

        try {
            $client->profile->retrieve();
            $this->fail('expected AuthenticationException');
        } catch (AuthenticationException) {
            $this->assertSame(1, $http->callCount());
        }
    }

    public function testBackoffGrowsWithAttempts(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(500, '');
        $http->pushJson(500, '');
        $http->pushJson(200, []);
        $client = $this->retryingClient(2, $http);

        $client->profile->retrieve();
        $this->assertCount(2, $this->sleeps);
        $this->assertGreaterThan($this->sleeps[0] - 0.25, $this->sleeps[1]);
    }
}
