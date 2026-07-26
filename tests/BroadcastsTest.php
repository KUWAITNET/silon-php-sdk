<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ConflictException;
use Silon\Exception\UnprocessableEntityException;
use Silon\Model\Broadcast;
use Silon\Model\BroadcastAccepted;
use Silon\Model\Conversation;
use Silon\Model\Event;

final class BroadcastsTest extends TestCase
{
    private const BROADCASTS = '/api/v1/broadcasts/';

    private const ACCEPTED = [
        'id' => 'br_01J1', 'object' => 'broadcast', 'channel' => 'sms',
        'status' => 'queued', 'target_count' => 2, 'skipped_count' => 0,
    ];

    private const SCHEDULED = [
        'id' => 'br_02S', 'object' => 'broadcast', 'channel' => 'email',
        'status' => 'scheduled', 'target_count' => null, 'skipped_count' => null,
    ];

    private const CANCELED = [
        'id' => 'br_02S', 'object' => 'broadcast', 'channel' => 'email',
        'status' => 'canceled', 'target_count' => null, 'skipped_count' => null,
    ];

    public function testCreateMinimal(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED);
        $created = $this->makeClient($http)->broadcasts->create([
            'channel' => 'sms',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['body' => 'Flash sale ends tonight'],
        ]);
        $this->assertInstanceOf(BroadcastAccepted::class, $created);
        $this->assertSame('broadcast', $created->object);
        $this->assertSame('queued', $created->status);
        $this->assertSame(2, $created->target_count);
        $this->assertSame(0, $created->skipped_count);
        $this->assertEquals([
            'channel' => 'sms',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['body' => 'Flash sale ends tonight'],
        ], $this->body($http->last()));
    }

    public function testCreateRecipientsAudiencePassedVerbatim(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED);
        $audience = [
            'type' => 'recipients',
            'recipients' => [
                ['phone_number' => '+96550001234'],
                ['phone_number' => '+96550001235'],
                ['client_id' => 'cust_001'],
            ],
        ];
        $this->makeClient($http)->broadcasts->create(['channel' => 'sms', 'audience' => $audience, 'content' => ['body' => 'hi']]);
        $this->assertEquals($audience, $this->body($http->last())['audience']);
    }

    public function testCreateAutoGeneratesIdempotencyKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED);
        $this->makeClient($http)->broadcasts->create([
            'channel' => 'sms',
            'audience' => ['type' => 'client_ids', 'client_ids' => ['c1']],
            'content' => ['body' => 'x'],
        ]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testCreateExplicitIdempotencyKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED);
        $this->makeClient($http)->broadcasts->create([
            'channel' => 'sms',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['body' => 'x'],
            'idempotency_key' => 'my-key-1',
        ]);
        $this->assertSame('my-key-1', $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testCreateRetriedOn500WithSameKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(500, '');
        $http->pushJson(202, self::ACCEPTED);
        $client = $this->makeClient($http, ['maxRetries' => 2, 'sleeper' => fn () => null]);
        $created = $client->broadcasts->create([
            'channel' => 'sms',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['body' => 'x'],
        ]);
        $this->assertSame('br_01J1', $created->id);
        $this->assertSame(2, $http->callCount());
        $keys = array_unique(array_map(fn ($r) => $r->getHeaderLine('Idempotency-Key'), $http->requests));
        $this->assertCount(1, $keys);
    }

    public function testCreateExtraBodyPassthrough(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED);
        $this->makeClient($http)->broadcasts->create([
            'channel' => 'email',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['subject' => 'Hi', 'body' => '<h1>Hello</h1>'],
            'extra_body' => ['future_field' => ['nested' => true]],
        ]);
        $this->assertSame(['nested' => true], $this->body($http->last())['future_field']);
    }

    public function testCreate422MalformedRecipientRow(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(422, [
            'type' => 'https://acme.silon.tech/docs/errors/audience-invalid',
            'title' => 'Invalid audience', 'status' => 422,
            'detail' => 'Enter a valid E.164 phone number.',
            'field' => 'audience.recipients[3].phone_number',
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->broadcasts->create([
                'channel' => 'sms',
                'audience' => ['type' => 'recipients', 'recipients' => [['phone_number' => 'nope']]],
                'content' => ['body' => 'x'],
            ]);
            $this->fail('expected UnprocessableEntityException');
        } catch (UnprocessableEntityException $err) {
            $this->assertSame('audience-invalid', $err->errors[0]->code);
            $this->assertSame('audience.recipients[3].phone_number', $err->errors[0]->attr);
        }
    }

    public function testBroadcastRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'br_01J1', 'channel' => 'email', 'target_count' => 100,
            'queued' => 0, 'sent' => 97, 'failed' => 3,
            'started_at' => '2026-07-01T10:00:00Z', 'completed_at' => '2026-07-01T10:05:00Z',
            'status' => 'completed',
        ]);
        $broadcast = $this->makeClient($http)->broadcasts->retrieve('br_01J1');
        $this->assertInstanceOf(Broadcast::class, $broadcast);
        $this->assertSame('completed', $broadcast->status);
        $this->assertSame(97, $broadcast->sent);
        $this->assertInstanceOf(\DateTimeImmutable::class, $broadcast->completed_at);
    }

    public function testInProgressBroadcastHasNullCompletedAt(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'br_01J1', 'channel' => 'email', 'target_count' => 100,
            'queued' => 40, 'sent' => 57, 'failed' => 3,
            'started_at' => '2026-07-01T10:00:00Z', 'completed_at' => null, 'status' => 'in_progress',
        ]);
        $broadcast = $this->makeClient($http)->broadcasts->retrieve('br_01J1');
        $this->assertNull($broadcast->completed_at);
        $this->assertSame('in_progress', $broadcast->status);
    }

    public function testCreateSendAtScheduled(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::SCHEDULED);
        $result = $this->makeClient($http)->broadcasts->create([
            'channel' => 'email',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['subject' => 'Hi', 'body' => '<h1>Hello</h1>'],
            'send_at' => new \DateTimeImmutable('2026-08-01T09:00:00', new \DateTimeZone('+03:00')),
        ]);
        $this->assertSame('scheduled', $result->status);
        $this->assertNull($result->target_count);
        $this->assertNull($result->skipped_count);
        $this->assertSame('2026-08-01T09:00:00+03:00', $this->body($http->last())['send_at']);
    }

    public function testRetrieveScheduledBroadcast(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'br_02S', 'channel' => 'email', 'target_count' => null,
            'queued' => 0, 'sent' => 0, 'failed' => 0, 'started_at' => null, 'completed_at' => null,
            'status' => 'scheduled', 'send_at' => '2026-08-01T06:00:00Z',
        ]);
        $broadcast = $this->makeClient($http)->broadcasts->retrieve('br_02S');
        $this->assertSame('scheduled', $broadcast->status);
        $this->assertNull($broadcast->target_count);
        $this->assertSame('2026-08-01T06:00:00+00:00', $broadcast->send_at->format('Y-m-d\TH:i:sP'));
    }

    public function testCancelBroadcast(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::CANCELED);
        $canceled = $this->makeClient($http)->broadcasts->cancel('br_02S');
        $this->assertSame('canceled', $canceled->status);
        $request = $http->last();
        $this->assertSame('POST', $request->method);
        $this->assertSame('/api/v1/broadcasts/br_02S/cancel/', $this->path($request));
        $this->assertNull($request->body);
        $this->assertSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testCancelBroadcastNotCancellable409(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(409, [
            'type' => 'https://acme.silon.tech/docs/errors/not-cancellable',
            'title' => 'Not cancellable', 'status' => 409,
            'detail' => 'The broadcast has already dispatched.',
        ], ['Content-Type' => 'application/problem+json']);
        $this->expectException(ConflictException::class);
        $this->makeClient($http)->broadcasts->cancel('br_01J1');
    }

    public function testEventRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'evt_01J1', 'object' => 'event', 'type' => 'message.failed',
            'api_version' => '2026-06-28', 'created' => '2026-07-01T10:00:00Z',
            'data' => [
                'id' => 'msg_1', 'object' => 'message', 'channel' => 'sms', 'recipient' => '+1',
                'client_id' => 'cust_001', 'status' => 'failed', 'error' => 'Unreachable',
                'broadcast_id' => 'br_01J1', 'provider' => 'twilio',
            ],
        ]);
        $event = $this->makeClient($http)->events->retrieve('evt_01J1');
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('message.failed', $event->type);
        $this->assertSame('Unreachable', $event->data->error);
        $this->assertSame('br_01J1', $event->data->broadcast_id);
        $this->assertSame('/api/v1/events/evt_01J1', $this->path($http->last()));
    }

    public function testMessageReceivedCarriesTheMessageAndItsThread(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'evt_inbox1', 'object' => 'event', 'type' => 'message.received',
            'livemode' => true,
            'data' => [
                'id' => '1615', 'object' => 'message',
                'conversation_id' => '6f1c9d2e-0000-4000-8000-000000000001',
                'body' => 'do you deliver to Salmiya?',
                'direction' => 'inbound', 'author' => 'customer',
                'conversation' => [
                    'id' => '6f1c9d2e-0000-4000-8000-000000000001',
                    'object' => 'conversation', 'channel' => 'whatsapp',
                    'status' => 'open', 'labels' => ['vip'], 'unread' => 1,
                ],
            ],
        ]);
        $data = $this->makeClient($http)->events->retrieve('evt_inbox1')->data;

        // A string on every event type, even though the Conversations API
        // types a message id as an integer.
        $this->assertSame('1615', $data->id);
        $this->assertSame('do you deliver to Salmiya?', $data->body);
        $this->assertSame('6f1c9d2e-0000-4000-8000-000000000001', $data->conversation_id);
        $this->assertSame('inbound', $data->direction);
        $this->assertSame('customer', $data->author);
        // The thread rides along so a consumer needs no follow-up read.
        $this->assertInstanceOf(Conversation::class, $data->conversation);
        $this->assertSame('open', $data->conversation->status);
    }

    public function testConversationAssignedCarriesBothSidesOfTheHandoff(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'evt_conv1', 'object' => 'event', 'type' => 'conversation.assigned',
            'livemode' => true,
            'data' => [
                'id' => '6f1c9d2e-0000-4000-8000-000000000001', 'object' => 'conversation',
                'channel' => 'whatsapp', 'status' => 'open', 'priority' => 'high',
                'subject' => 'Ada', 'labels' => ['vip', 'refund'], 'unread' => 0,
                'archived' => false, 'assignee_id' => 7, 'team_id' => null,
                'created' => '2026-07-26T09:00:00Z', 'updated' => '2026-07-26T10:05:00Z',
                'previous_status' => 'pending', 'reason' => 'transfer',
                'previous_assignee_id' => 3,
            ],
        ]);
        $data = $this->makeClient($http)->events->retrieve('evt_conv1')->data;

        $this->assertSame('transfer', $data->reason);
        $this->assertSame(3, $data->previous_assignee_id);
        $this->assertSame(7, $data->assignee_id);
        $this->assertSame('pending', $data->previous_status);
        $this->assertSame('high', $data->priority);
        $this->assertSame(['vip', 'refund'], $data->labels);
        $this->assertFalse($data->archived);
        $this->assertSame('2026', $data->updated->format('Y'));
        $this->assertNull($data->team_id);
    }
}
