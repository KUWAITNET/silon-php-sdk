<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\NotFoundException;
use Silon\Model\WebhookAttempt;
use Silon\Model\WebhookEndpoint;
use Silon\Model\WebhookEndpointTestResult;
use Silon\Model\WebhookEndpointWithSecret;

final class WebhookEndpointsTest extends TestCase
{
    private const ENDPOINTS = '/api/v1/webhook_endpoints';

    private const ENDPOINT = [
        'id' => 'we_01J1ABC', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/hooks/silon',
        'description' => 'prod', 'enabled_events' => ['message.failed'], 'status' => 'enabled', 'created_at' => '2026-07-01T00:00:00Z',
    ];

    public function testCreateReturnsOneTimeSecret(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::ENDPOINT, 'secret' => 'whsec_3sXqf9b2e1Tn']);
        $created = $this->makeClient($http)->webhookEndpoints->create([
            'url' => 'https://example.com/hooks/silon', 'description' => 'prod', 'enabled_events' => ['message.failed'],
        ]);
        $this->assertInstanceOf(WebhookEndpointWithSecret::class, $created);
        $this->assertStringStartsWith('whsec_', $created->secret);
        $this->assertEquals([
            'url' => 'https://example.com/hooks/silon', 'description' => 'prod', 'enabled_events' => ['message.failed'],
        ], $this->body($http->last()));
    }

    public function testCreateDefaultsToAllEvents(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::ENDPOINT, 'enabled_events' => ['*'], 'secret' => 'whsec_x']);
        $created = $this->makeClient($http)->webhookEndpoints->create(['url' => 'https://example.com/h']);
        $this->assertSame(['*'], $created->enabled_events);
        $this->assertEquals(['url' => 'https://example.com/h'], $this->body($http->last()));
    }

    public function testListPaginated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::ENDPOINT], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->webhookEndpoints->list(['limit' => 10]);
        $this->assertInstanceOf(WebhookEndpoint::class, $page[0]);
    }

    public function testRetrieveHasNoTrailingSlash(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::ENDPOINT);
        $this->makeClient($http)->webhookEndpoints->retrieve('we_01J1ABC');
        $this->assertSame('/api/v1/webhook_endpoints/we_01J1ABC', $this->path($http->last()));
    }

    public function testDisableEndpoint(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::ENDPOINT, 'status' => 'disabled']);
        $updated = $this->makeClient($http)->webhookEndpoints->update('we_01J1ABC', ['status' => 'disabled']);
        $this->assertSame('disabled', $updated->status);
        $this->assertEquals(['status' => 'disabled'], $this->body($http->last()));
    }

    public function testChangeSubscriptions(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::ENDPOINT);
        $this->makeClient($http)->webhookEndpoints->update('we_01J1ABC', ['enabled_events' => ['message.delivered', 'broadcast.completed']]);
        $this->assertEquals(['enabled_events' => ['message.delivered', 'broadcast.completed']], $this->body($http->last()));
    }

    public function testDelete(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(204, '');
        $this->assertNull($this->makeClient($http)->webhookEndpoints->delete('we_01J1ABC'));
    }

    public function testTestEndpointDelivered(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['delivered' => true, 'response_status' => 200, 'latency_ms' => 184, 'error' => null]);
        $result = $this->makeClient($http)->webhookEndpoints->test('we_01J1ABC');
        $this->assertInstanceOf(WebhookEndpointTestResult::class, $result);
        $this->assertTrue($result->delivered);
        $this->assertSame(200, $result->response_status);
        $this->assertSame(184, $result->latency_ms);
        $this->assertNull($result->error);
        $request = $http->last();
        $this->assertSame('POST', $request->method);
        $this->assertSame('/api/v1/webhook_endpoints/we_01J1ABC/test', $this->path($request));
        $this->assertNull($request->body);
    }

    public function testTestEndpointFailingSinkIsNotAnError(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['delivered' => false, 'response_status' => 500, 'latency_ms' => 92, 'error' => 'HTTP 500']);
        $result = $this->makeClient($http)->webhookEndpoints->test('we_01J1ABC');
        $this->assertFalse($result->delivered);
        $this->assertSame('HTTP 500', $result->error);
    }

    public function testTestEndpointUnknownId404(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(404, [
            'type' => 'https://acme.silon.tech/docs/errors/resource-not-found',
            'title' => 'Not found', 'status' => 404, 'detail' => 'No such webhook endpoint.', 'retryable' => false,
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->webhookEndpoints->test('we_nope');
            $this->fail('expected NotFoundException');
        } catch (NotFoundException $err) {
            $this->assertSame('resource-not-found', $err->errors[0]->code);
        }
    }

    public function testListAttemptsPaginated(): void
    {
        $http = new MockHttpClient();
        $attempt = [
            'id' => 'wha_1042', 'object' => 'webhook_attempt', 'event_id' => 'evt_01J2', 'event_type' => 'message.delivered',
            'attempts' => 3, 'response_status' => 503, 'ok' => false, 'error' => 'HTTP 503',
            'last_attempt_at' => '2026-06-28T12:36:10+00:00', 'next_attempt_at' => '2026-06-28T12:36:50+00:00', 'created' => '2026-06-28T12:30:00+00:00',
        ];
        $http->pushJson(200, ['results' => [$attempt], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->webhookEndpoints->listAttempts('we_01J1ABC', ['limit' => 20]);
        $row = $page[0];
        $this->assertInstanceOf(WebhookAttempt::class, $row);
        $this->assertSame('wha_1042', $row->id);
        $this->assertSame('message.delivered', $row->event_type);
        $this->assertSame(3, $row->attempts);
        $this->assertFalse($row->ok);
        $this->assertSame(503, $row->response_status);
        $this->assertSame('2026-06-28T12:36:10+00:00', $row->last_attempt_at->format('Y-m-d\TH:i:sP'));
        $this->assertSame('/api/v1/webhook_endpoints/we_01J1ABC/attempts', $this->path($http->last()));
        $this->assertSame('20', $this->query($http->last())['limit']);
    }

    public function testListAttemptsUnknownId404(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(404, [
            'type' => 'https://acme.silon.tech/docs/errors/resource-not-found',
            'title' => 'Not found', 'status' => 404, 'detail' => 'No such webhook endpoint.', 'retryable' => false,
        ], ['Content-Type' => 'application/problem+json']);
        $this->expectException(NotFoundException::class);
        $this->makeClient($http)->webhookEndpoints->listAttempts('we_nope');
    }
}
