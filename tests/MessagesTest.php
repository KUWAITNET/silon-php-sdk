<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ConflictException;
use Silon\Exception\SilonException;
use Silon\Exception\UnprocessableEntityException;
use Silon\Model\BatchAccepted;
use Silon\Model\MessageAccepted;
use Silon\Model\MessageStatus;
use Silon\Model\MessageTimelineEntry;

final class MessagesTest extends TestCase
{
    private const MESSAGES = '/api/v1/messages/';
    private const BATCH = '/api/v1/messages/batch/';

    private const ACCEPTED_MESSAGE = [
        'id' => '9f3e8a82-1c5a-4b1f-9d4c-7b5d2c8f3e9a',
        'object' => 'message',
        'channel' => 'whatsapp',
        'status' => 'queued',
    ];

    private const UUID_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const BATCH_ROWS = [
        ['to' => ['phone_number' => '+96550001234'], 'content' => ['body' => 'Sara, table for 2 at 7pm.']],
        ['channel' => 'email', 'to' => ['email' => 'omar@example.com'], 'content' => ['subject' => 'Hi', 'body' => '<p>Omar.</p>']],
    ];

    private const ACCEPTED_BATCH = [
        'id' => 'batch_01J2',
        'object' => 'batch',
        'messages' => [
            ['id' => 'msg_01', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued'],
            ['id' => 'msg_02', 'object' => 'message', 'channel' => 'email', 'status' => 'queued'],
        ],
    ];

    public function testSendMinimal(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $sent = $this->makeClient($http)->messages->send([
            'channel' => 'whatsapp',
            'to' => ['client_id' => 'cust_001'],
            'content' => ['body' => 'Your order has shipped'],
        ]);
        $this->assertInstanceOf(MessageAccepted::class, $sent);
        $this->assertSame('message', $sent->object);
        $this->assertSame('queued', $sent->status);
        $this->assertEquals([
            'channel' => 'whatsapp',
            'to' => ['client_id' => 'cust_001'],
            'content' => ['body' => 'Your order has shipped'],
        ], $this->body($http->last()));
    }

    public function testSendAutoGeneratesIdempotencyKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send(['channel' => 'sms', 'to' => ['phone_number' => '+1'], 'content' => ['body' => 'x']]);
        $this->assertMatchesRegularExpression(self::UUID_RE, $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testSendExplicitIdempotencyKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send([
            'channel' => 'sms',
            'to' => ['phone_number' => '+1'],
            'content' => ['body' => 'x'],
            'idempotency_key' => 'my-key-1',
        ]);
        $this->assertSame('my-key-1', $http->last()->getHeaderLine('Idempotency-Key'));
        $this->assertArrayNotHasKey('idempotency_key', $this->body($http->last()));
    }

    public function testSendRequiresExactlyOneTarget(): void
    {
        $client = $this->makeClient(new MockHttpClient());
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('exactly one');
        $client->messages->send(['channel' => 'sms', 'content' => ['body' => 'x']]);
    }

    public function testSendRejectsBothTargets(): void
    {
        $client = $this->makeClient(new MockHttpClient());
        $this->expectException(SilonException::class);
        $client->messages->send([
            'channel' => 'sms',
            'to' => ['client_id' => 'a'],
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['body' => 'x'],
        ]);
    }

    public function testSendBroadcast(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'br_01J1', 'object' => 'broadcast', 'channel' => 'email',
            'status' => 'queued', 'target_count' => 240, 'skipped_count' => 3,
        ]);
        $sent = $this->makeClient($http)->messages->send([
            'channel' => 'email',
            'audience' => ['type' => 'client_group', 'slug' => 'vip'],
            'content' => ['subject' => 'Hi', 'body' => '<h1>Hello</h1>'],
        ]);
        $this->assertSame('broadcast', $sent->object);
        $this->assertSame(240, $sent->target_count);
        $this->assertSame(3, $sent->skipped_count);
        $body = $this->body($http->last());
        $this->assertSame(['type' => 'client_group', 'slug' => 'vip'], $body['audience']);
        $this->assertArrayNotHasKey('to', $body);
    }

    public function testSendChannelSpecificFields(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send([
            'channel' => 'push',
            'to' => ['client_id' => 'cust_001'],
            'content' => ['body' => 'New episode'],
            'application' => 'consumer-app',
            'priority' => 'high',
            'ttl' => 3600,
            'provider' => 'fcm',
            'sender' => 'acme',
        ]);
        $body = $this->body($http->last());
        $this->assertSame('consumer-app', $body['application']);
        $this->assertSame('high', $body['priority']);
        $this->assertSame(3600, $body['ttl']);
        $this->assertSame('fcm', $body['provider']);
        $this->assertSame('acme', $body['sender']);
    }

    public function testSendWhatsappTemplateBlock(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $template = ['name' => 'order_confirmed', 'language' => 'en', 'variables' => ['body_1' => 'Sara']];
        $this->makeClient($http)->messages->send([
            'channel' => 'whatsapp',
            'to' => ['phone_number' => '+12025550123'],
            'whatsapp_template' => $template,
            'provider' => 'meta_cloud',
        ]);
        $body = $this->body($http->last());
        $this->assertEquals($template, $body['whatsapp_template']);
        $this->assertArrayNotHasKey('content', $body);
    }

    public function testSendExtraBodyPassthrough(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send([
            'channel' => 'web_push',
            'to' => ['client_id' => 'cust_001'],
            'content' => ['body' => 'hi'],
            'extra_body' => ['widget_key' => 'wk_1', 'future_field' => ['nested' => true]],
        ]);
        $body = $this->body($http->last());
        $this->assertSame('wk_1', $body['widget_key']);
        $this->assertSame(['nested' => true], $body['future_field']);
        $this->assertArrayNotHasKey('extra_body', $body);
    }

    public function testUnknownResponseFieldsPreserved(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [...self::ACCEPTED_MESSAGE, 'brand_new_field' => '42']);
        $sent = $this->makeClient($http)->messages->send(['channel' => 'sms', 'to' => ['phone_number' => '+1'], 'content' => ['body' => 'x']]);
        $this->assertSame('42', $sent->brand_new_field);
    }

    // -- batch -------------------------------------------------------------

    public function testSendBatchMinimal(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_BATCH);
        $batch = $this->makeClient($http)->messages->sendBatch(['messages' => self::BATCH_ROWS, 'channel' => 'sms']);
        $this->assertInstanceOf(BatchAccepted::class, $batch);
        $this->assertSame('batch', $batch->object);
        $this->assertSame(['msg_01', 'msg_02'], array_map(fn ($r) => $r->id, $batch->messages));
        $this->assertSame(['sms', 'email'], array_map(fn ($r) => $r->channel, $batch->messages));
        $this->assertEquals(['messages' => self::BATCH_ROWS, 'channel' => 'sms'], $this->body($http->last()));
    }

    public function testSendBatchAutoIdempotencyKey(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_BATCH);
        $this->makeClient($http)->messages->sendBatch(['messages' => self::BATCH_ROWS, 'channel' => 'sms']);
        $this->assertMatchesRegularExpression(self::UUID_RE, $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testSendBatchExtraBodyPassthrough(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_BATCH);
        $this->makeClient($http)->messages->sendBatch([
            'messages' => self::BATCH_ROWS,
            'channel' => 'sms',
            'extra_body' => ['future_field' => ['nested' => true]],
        ]);
        $this->assertSame(['nested' => true], $this->body($http->last())['future_field']);
    }

    public function testSendBatchUnknownResponseFieldsPreserved(): void
    {
        $http = new MockHttpClient();
        $rows = self::ACCEPTED_BATCH;
        $rows['brand_new_field'] = '42';
        $rows['messages'][0]['row_extra'] = 7;
        $http->pushJson(202, $rows);
        $batch = $this->makeClient($http)->messages->sendBatch(['messages' => self::BATCH_ROWS, 'channel' => 'sms']);
        $this->assertSame('42', $batch->brand_new_field);
        $this->assertSame(7, $batch->messages[0]->row_extra);
    }

    public function testSendBatchFileForm(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => '17', 'object' => 'batch', 'status' => 'queued', 'row_count' => 1200]);
        $batch = $this->makeClient($http)->messages->sendBatch([
            'file' => '0f1c9d4e8a7b4c2da1.csv',
            'channel' => 'sms',
            'content' => ['body' => 'Hello {{name}}'],
        ]);
        $this->assertSame('batch', $batch->object);
        $this->assertSame('queued', $batch->status);
        $this->assertSame(1200, $batch->row_count);
        $this->assertNull($batch->messages);
        $this->assertEquals(['file' => '0f1c9d4e8a7b4c2da1.csv', 'channel' => 'sms', 'content' => ['body' => 'Hello {{name}}']], $this->body($http->last()));
    }

    public function testSendBatchFileFormMinimal(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => '18', 'object' => 'batch', 'status' => 'queued']);
        $batch = $this->makeClient($http)->messages->sendBatch(['file' => 'rows.csv']);
        $this->assertSame('queued', $batch->status);
        $this->assertNull($batch->row_count);
        $this->assertNull($batch->messages);
        $this->assertEquals(['file' => 'rows.csv'], $this->body($http->last()));
    }

    public function testSendBatchRequiresExactlyOneSource(): void
    {
        $client = $this->makeClient(new MockHttpClient());
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('exactly one');
        $client->messages->sendBatch(['channel' => 'sms']);
    }

    public function testSendBatchInlineDefaultsSerialized(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_BATCH);
        $this->makeClient($http)->messages->sendBatch([
            'messages' => self::BATCH_ROWS,
            'channel' => 'push',
            'content' => ['body' => 'fallback'],
            'template' => ['slug' => 'welcome'],
            'provider' => 'fcm',
            'sender' => 'acme',
            'application' => 'consumer-app',
            'widget_key' => 'wk_1',
            'priority' => 'high',
            'ttl' => 3600,
            'whatsapp' => ['preview_url' => true],
            'whatsapp_template' => ['name' => 'order_confirmed', 'language' => 'en'],
        ]);
        $this->assertEquals([
            'messages' => self::BATCH_ROWS,
            'channel' => 'push',
            'content' => ['body' => 'fallback'],
            'template' => ['slug' => 'welcome'],
            'provider' => 'fcm',
            'sender' => 'acme',
            'application' => 'consumer-app',
            'widget_key' => 'wk_1',
            'priority' => 'high',
            'ttl' => 3600,
            'whatsapp' => ['preview_url' => true],
            'whatsapp_template' => ['name' => 'order_confirmed', 'language' => 'en'],
        ], $this->body($http->last()));
    }

    public function testSendBatch422CarriesPerIndexField(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(422, [
            'type' => 'https://acme.silon.tech/docs/errors/validation-failed',
            'title' => 'Invalid batch',
            'status' => 422,
            'detail' => 'Enter a valid E.164 phone number.',
            'field' => 'messages[3].to.phone_number',
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->messages->sendBatch([
                'messages' => [['to' => ['phone_number' => 'nope'], 'content' => ['body' => 'x']]],
                'channel' => 'sms',
            ]);
            $this->fail('expected UnprocessableEntityException');
        } catch (UnprocessableEntityException $err) {
            $this->assertSame(422, $err->statusCode);
            $this->assertSame('validation-failed', $err->errors[0]->code);
            $this->assertSame('messages[3].to.phone_number', $err->errors[0]->attr);
        }
    }

    // -- retrieve ----------------------------------------------------------

    public function testRetrieveStatusModernShape(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'evt-1', 'object' => 'message', 'channel' => 'sms', 'livemode' => true, 'status' => 'sent',
            'timeline' => [
                ['status' => 'queued', 'at' => '2026-07-01T10:00:00+00:00'],
                ['status' => 'sent', 'at' => '2026-07-01T10:00:01+00:00', 'provider' => 'twilio'],
                ['status' => 'delivered', 'at' => '2026-07-01T10:00:05+00:00', 'provider' => 'twilio'],
            ],
            'event_id' => 'evt-1', 'is_sent' => true, 'messages' => [],
        ]);
        $status = $this->makeClient($http)->messages->retrieve('evt-1');
        $this->assertInstanceOf(MessageStatus::class, $status);
        $this->assertSame('evt-1', $status->id);
        $this->assertSame('message', $status->object);
        $this->assertSame('sms', $status->channel);
        $this->assertSame('sent', $status->status);
        $this->assertSame('/api/v1/messages/evt-1/', $this->path($http->last()));
    }

    public function testRetrieveTimelineParsedTyped(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 'evt-1', 'object' => 'message', 'channel' => 'whatsapp', 'status' => 'sent',
            'timeline' => [
                ['status' => 'queued', 'at' => '2026-07-01T10:00:00+00:00'],
                ['status' => 'sent', 'at' => '2026-07-01T10:00:01+00:00', 'provider' => 'meta_cloud'],
                ['status' => 'delivered', 'at' => '2026-07-01T10:00:05+00:00', 'provider' => 'meta_cloud'],
            ],
        ]);
        $status = $this->makeClient($http)->messages->retrieve('evt-1');
        $this->assertInstanceOf(MessageTimelineEntry::class, $status->timeline[0]);
        $this->assertSame(['queued', 'sent', 'delivered'], array_map(fn ($e) => $e->status, $status->timeline));
        $this->assertSame('2026-07-01T10:00:00+00:00', $status->timeline[0]->at->format('Y-m-d\TH:i:sP'));
        $this->assertNull($status->timeline[0]->provider);
        $this->assertSame('meta_cloud', $status->timeline[1]->provider);
        $this->assertNotSame('delivered', $status->status);
    }

    public function testRetrieveChannelNullWhenRowsDisagree(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['id' => 'evt-2', 'object' => 'message', 'channel' => null, 'status' => 'sent', 'timeline' => []]);
        $status = $this->makeClient($http)->messages->retrieve('evt-2');
        $this->assertNull($status->channel);
    }

    // -- scheduling --------------------------------------------------------

    public function testSendAtDatetimeSerializesIso8601(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => 'sched-01', 'object' => 'message', 'channel' => 'sms', 'status' => 'scheduled']);
        $sent = $this->makeClient($http)->messages->send([
            'channel' => 'sms',
            'to' => ['phone_number' => '+1'],
            'content' => ['body' => 'x'],
            'send_at' => new \DateTimeImmutable('2026-08-01T09:30:00', new \DateTimeZone('+00:00')),
        ]);
        $this->assertSame('scheduled', $sent->status);
        $this->assertSame('2026-08-01T09:30:00+00:00', $this->body($http->last())['send_at']);
        $this->assertNotSame('', $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testSendAtDatetimeKeepsUtcOffset(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => 'sched-01', 'object' => 'message', 'channel' => 'sms', 'status' => 'scheduled']);
        $this->makeClient($http)->messages->send([
            'channel' => 'sms',
            'to' => ['phone_number' => '+1'],
            'content' => ['body' => 'x'],
            'send_at' => new \DateTimeImmutable('2026-08-01T12:00:00', new \DateTimeZone('+03:00')),
        ]);
        $this->assertSame('2026-08-01T12:00:00+03:00', $this->body($http->last())['send_at']);
    }

    public function testSendAtStringPassesThroughVerbatim(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => 'sched-01', 'object' => 'message', 'channel' => 'sms', 'status' => 'scheduled']);
        $this->makeClient($http)->messages->send([
            'channel' => 'sms',
            'to' => ['phone_number' => '+1'],
            'content' => ['body' => 'x'],
            'send_at' => '2026-08-01T09:00:00+03:00',
        ]);
        $this->assertSame('2026-08-01T09:00:00+03:00', $this->body($http->last())['send_at']);
    }

    public function testSendBatchFileFormSendAt(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => '19', 'object' => 'batch', 'status' => 'scheduled', 'row_count' => 1200]);
        $batch = $this->makeClient($http)->messages->sendBatch([
            'file' => 'rows.csv',
            'channel' => 'sms',
            'send_at' => new \DateTimeImmutable('2026-09-01T08:00:00', new \DateTimeZone('+00:00')),
        ]);
        $this->assertSame('scheduled', $batch->status);
        $this->assertSame('2026-09-01T08:00:00+00:00', $this->body($http->last())['send_at']);
    }

    // -- cancellation ------------------------------------------------------

    public function testCancelMessage(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['id' => 'sched-01', 'object' => 'message', 'channel' => 'sms', 'status' => 'canceled']);
        $canceled = $this->makeClient($http)->messages->cancel('sched-01');
        $this->assertInstanceOf(MessageAccepted::class, $canceled);
        $this->assertSame('canceled', $canceled->status);
        $request = $http->last();
        $this->assertSame('POST', $request->method);
        $this->assertSame('/api/v1/messages/sched-01/cancel/', $this->path($request));
        $this->assertNull($request->body);
        $this->assertSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testCancelMessageRepeatReturns200Canceled(): void
    {
        $http = new MockHttpClient();
        $http->setHandler(fn () => MockHttpClient::jsonResponse(200, ['id' => 'sched-01', 'object' => 'message', 'channel' => 'sms', 'status' => 'canceled']));
        $client = $this->makeClient($http);
        $first = $client->messages->cancel('sched-01');
        $second = $client->messages->cancel('sched-01');
        $this->assertSame('canceled', $first->status);
        $this->assertSame('canceled', $second->status);
        $this->assertSame(2, $http->callCount());
    }

    public function testCancelMessageNotCancellable409(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(409, [
            'type' => 'https://acme.silon.tech/docs/errors/not-cancellable',
            'title' => 'Not cancellable', 'status' => 409,
            'detail' => 'The message has already dispatched.',
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->messages->cancel('evt-sent');
            $this->fail('expected ConflictException');
        } catch (ConflictException $err) {
            $this->assertSame(409, $err->statusCode);
            $this->assertSame('not-cancellable', $err->errors[0]->code);
        }
    }

    // -- template pinning --------------------------------------------------

    public function testSendTemplatePinsVersion(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send([
            'channel' => 'email',
            'to' => ['email' => 'sara@example.com'],
            'template' => ['slug' => 'order-shipped', 'version' => 2, 'variables' => ['name' => 'Sara']],
        ]);
        $this->assertEquals(['slug' => 'order-shipped', 'version' => 2, 'variables' => ['name' => 'Sara']], $this->body($http->last())['template']);
    }

    public function testSendTemplateLatestHasNoVersion(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::ACCEPTED_MESSAGE);
        $this->makeClient($http)->messages->send([
            'channel' => 'email',
            'to' => ['email' => 'sara@example.com'],
            'template' => ['slug' => 'welcome'],
        ]);
        $template = $this->body($http->last())['template'];
        $this->assertSame(['slug' => 'welcome'], $template);
        $this->assertArrayNotHasKey('version', $template);
    }
}
