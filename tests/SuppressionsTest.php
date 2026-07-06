<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\NotFoundException;
use Silon\Exception\PermissionDeniedException;
use Silon\Exception\UnprocessableEntityException;
use Silon\Http\Request;
use Silon\Model\BatchAccepted;
use Silon\Model\BroadcastAccepted;
use Silon\Model\MessageAccepted;
use Silon\Model\Suppression;

final class SuppressionsTest extends TestCase
{
    private const SUPPRESSIONS = '/api/v1/suppressions/';
    private const MESSAGES = '/api/v1/messages/';

    private static function suppression(int $n, array $overrides = []): array
    {
        return array_merge([
            'id' => 'sup_' . str_pad((string) $n, 32, '0', STR_PAD_LEFT),
            'object' => 'suppression',
            'address' => '+9655000123' . $n,
            'channel' => 'sms',
            'reason' => 'manual',
            'livemode' => true,
            'created' => '2026-07-01T10:00:00Z',
        ], $overrides);
    }

    public function testCreateMinimal(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::suppression(1));
        $sup = $this->makeClient($http)->suppressions->create(['address' => '+96550001231']);
        $this->assertInstanceOf(Suppression::class, $sup);
        $this->assertSame('suppression', $sup->object);
        $this->assertSame('+96550001231', $sup->address);
        $this->assertSame('manual', $sup->reason);
        $this->assertSame('2026-07-01T10:00:00+00:00', $sup->created->format('Y-m-d\TH:i:sP'));
        $request = $http->last();
        $this->assertSame('/api/v1/suppressions/', $this->path($request));
        $this->assertEquals(['address' => '+96550001231'], $this->body($request));
        $this->assertSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testCreateChannelAndReasonSerialize(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::suppression(2, ['address' => 'sara@example.com', 'channel' => 'email', 'reason' => 'unsubscribe']));
        $sup = $this->makeClient($http)->suppressions->create(['address' => 'sara@example.com', 'channel' => 'email', 'reason' => 'unsubscribe']);
        $this->assertSame('email', $sup->channel);
        $this->assertSame('unsubscribe', $sup->reason);
        $this->assertEquals(['address' => 'sara@example.com', 'channel' => 'email', 'reason' => 'unsubscribe'], $this->body($http->last()));
    }

    public function testCreateDuplicateReturns200WithExistingObject(): void
    {
        $http = new MockHttpClient();
        $existing = self::suppression(3, ['created' => '2026-06-01T00:00:00Z']);
        $http->pushJson(200, $existing);
        $sup = $this->makeClient($http)->suppressions->create(['address' => '+96550001233', 'channel' => 'sms']);
        $this->assertSame($existing['id'], $sup->id);
        $this->assertSame('2026-06-01T00:00:00+00:00', $sup->created->format('Y-m-d\TH:i:sP'));
    }

    public function testCreateAllChannelRowHasNullChannel(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::suppression(4, ['channel' => null]));
        $sup = $this->makeClient($http)->suppressions->create(['address' => '+96550001234']);
        $this->assertNull($sup->channel);
    }

    public function testUnknownFieldsTolerated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::suppression(5, ['brand_new_field' => '42']));
        $sup = $this->makeClient($http)->suppressions->create(['address' => '+96550001235']);
        $this->assertSame('42', $sup->brand_new_field);
    }

    public function testTestModeSuppressionLivemodeFalse(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::suppression(6, ['livemode' => false]));
        $sup = $this->makeClient($http)->suppressions->create(['address' => '+96550001236']);
        $this->assertFalse($sup->livemode);
    }

    public function testListSinglePage(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::suppression(1), self::suppression(2)], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->suppressions->list();
        $this->assertCount(2, $page);
        $this->assertInstanceOf(Suppression::class, $page[0]);
        $this->assertSame('+96550001231', $page[0]->address);
        $this->assertFalse($page->hasNextPage());
    }

    public function testListFiltersForwardedAsQueryParams(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [], 'next' => null, 'previous' => null]);
        $this->makeClient($http)->suppressions->list(['address' => '+96550001231', 'channel' => 'sms', 'reason' => 'stop', 'limit' => 10]);
        $params = $this->query($http->last());
        $this->assertSame('+96550001231', $params['address']);
        $this->assertSame('sms', $params['channel']);
        $this->assertSame('stop', $params['reason']);
        $this->assertSame('10', $params['limit']);
    }

    public function testListAutoPagingWalksAllPages(): void
    {
        $http = new MockHttpClient();
        $pages = [
            '' => [
                'results' => [self::suppression(1), self::suppression(2)],
                'next' => self::BASE_URL . self::SUPPRESSIONS . '?cursor=abc&limit=2',
                'previous' => null,
            ],
            'abc' => ['results' => [self::suppression(3)], 'next' => null, 'previous' => null],
        ];
        $http->setHandler(static function (Request $r) use ($pages) {
            $q = [];
            parse_str((string) parse_url($r->url, PHP_URL_QUERY), $q);

            return MockHttpClient::jsonResponse(200, $pages[$q['cursor'] ?? '']);
        });
        $addresses = [];
        foreach ($this->makeClient($http)->suppressions->list(['limit' => 2])->autoPaging() as $sup) {
            $addresses[] = $sup->address;
        }
        $this->assertSame(['+96550001231', '+96550001232', '+96550001233'], $addresses);
        $this->assertSame(2, $http->callCount());
    }

    public function testDeleteReturnsNull(): void
    {
        $http = new MockHttpClient();
        $supId = self::suppression(1)['id'];
        $http->pushJson(204, '');
        $this->assertNull($this->makeClient($http)->suppressions->delete($supId));
        $request = $http->last();
        $this->assertSame('DELETE', $request->method);
        $this->assertSame('/api/v1/suppressions/' . $supId . '/', $this->path($request));
    }

    public function testDeleteUnknownId404(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(404, [
            'type' => 'https://acme.silon.tech/docs/errors/not-found',
            'title' => 'Not found', 'status' => 404, 'detail' => 'No suppression matches the given id.',
        ], ['Content-Type' => 'application/problem+json']);
        $this->expectException(NotFoundException::class);
        $this->makeClient($http)->suppressions->delete('sup_nope');
    }

    public function testSendRecipientSuppressed422(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(422, [
            'type' => 'https://acme.silon.tech/docs/errors/recipient-suppressed',
            'title' => 'Recipient suppressed', 'status' => 422,
            'detail' => 'The recipient is on the suppression list.',
            'field' => 'to.phone_number',
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->messages->send(['channel' => 'sms', 'to' => ['phone_number' => '+96550001231'], 'content' => ['body' => 'x']]);
            $this->fail('expected UnprocessableEntityException');
        } catch (UnprocessableEntityException $err) {
            $this->assertSame('recipient-suppressed', $err->errors[0]->code);
            $this->assertSame('to.phone_number', $err->errors[0]->attr);
        }
    }

    public function testSendOverrideSuppressionSerializes(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => 'msg_1', 'object' => 'message', 'channel' => 'email', 'status' => 'queued']);
        $this->makeClient($http)->messages->send([
            'channel' => 'email',
            'to' => ['email' => 'sara@example.com'],
            'content' => ['subject' => 'Your receipt', 'body' => '...'],
            'override_suppression' => true,
        ]);
        $this->assertTrue($this->body($http->last())['override_suppression']);
    }

    public function testSendOverrideSuppressionOmittedByDefault(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, ['id' => 'msg_1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued']);
        $this->makeClient($http)->messages->send(['channel' => 'sms', 'to' => ['phone_number' => '+1'], 'content' => ['body' => 'x']]);
        $this->assertArrayNotHasKey('override_suppression', $this->body($http->last()));
    }

    public function testSendOverrideWithoutScope403(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(403, [
            'type' => 'https://acme.silon.tech/docs/errors/missing-scope',
            'title' => 'Missing scope', 'status' => 403,
            'detail' => 'override_suppression requires the suppressions:override scope.',
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->messages->send([
                'channel' => 'sms', 'to' => ['phone_number' => '+1'], 'content' => ['body' => 'x'], 'override_suppression' => true,
            ]);
            $this->fail('expected PermissionDeniedException');
        } catch (PermissionDeniedException $err) {
            $this->assertSame('missing-scope', $err->errors[0]->code);
        }
    }

    public function testBroadcastAcceptedSkippedBreakdownDecodes(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'br_01J1', 'object' => 'broadcast', 'channel' => 'sms', 'status' => 'queued',
            'target_count' => 94, 'skipped_count' => 6,
            'skipped' => ['suppressed' => 3, 'wrong_channel' => 2, 'duplicate' => 1],
        ]);
        $created = $this->makeClient($http)->broadcasts->create([
            'channel' => 'sms', 'audience' => ['type' => 'client_group', 'slug' => 'vip'], 'content' => ['body' => 'x'],
        ]);
        $this->assertInstanceOf(BroadcastAccepted::class, $created);
        $this->assertNotNull($created->skipped);
        $this->assertSame(3, $created->skipped->suppressed);
        $this->assertSame(2, $created->skipped->wrong_channel);
        $this->assertSame(1, $created->skipped->duplicate);
        $this->assertSame(6, $created->skipped_count);
    }

    public function testBroadcastAcceptedAbsentBreakdownTolerated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'br_01J1', 'object' => 'broadcast', 'channel' => 'sms', 'status' => 'queued',
            'target_count' => 2, 'skipped_count' => 0,
        ]);
        $created = $this->makeClient($http)->broadcasts->create([
            'channel' => 'sms', 'audience' => ['type' => 'client_group', 'slug' => 'vip'], 'content' => ['body' => 'x'],
        ]);
        $this->assertNull($created->skipped);
        $this->assertSame(0, $created->skipped_count);
    }

    public function testMessageAcceptedBroadcastModeBreakdownDecodes(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'br_01J1', 'object' => 'broadcast', 'channel' => 'email', 'status' => 'queued',
            'target_count' => 240, 'skipped_count' => 4,
            'skipped' => ['suppressed' => 4, 'wrong_channel' => 0, 'duplicate' => 0],
        ]);
        $sent = $this->makeClient($http)->messages->send([
            'channel' => 'email', 'audience' => ['type' => 'client_group', 'slug' => 'vip'], 'content' => ['subject' => 'Hi', 'body' => 'x'],
        ]);
        $this->assertInstanceOf(MessageAccepted::class, $sent);
        $this->assertNotNull($sent->skipped);
        $this->assertSame(4, $sent->skipped->suppressed);
        $this->assertSame(0, $sent->skipped->wrong_channel);
    }

    public function testBatchAcceptedInlineBreakdownDecodes(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'batch_1', 'object' => 'batch',
            'messages' => [['id' => 'm1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued']],
            'skipped_count' => 1,
            'skipped' => ['suppressed' => 1, 'wrong_channel' => 0, 'duplicate' => 0],
        ]);
        $batch = $this->makeClient($http)->messages->sendBatch([
            'channel' => 'sms',
            'messages' => [
                ['to' => ['phone_number' => '+96550001234'], 'content' => ['body' => 'hi']],
                ['to' => ['phone_number' => '+15005550009'], 'content' => ['body' => 'hi']],
            ],
        ]);
        $this->assertInstanceOf(BatchAccepted::class, $batch);
        $this->assertCount(1, $batch->messages);
        $this->assertSame(1, $batch->skipped_count);
        $this->assertSame(1, $batch->skipped->suppressed);
    }

    public function testBatchAcceptedAbsentBreakdownTolerated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [
            'id' => 'batch_1', 'object' => 'batch',
            'messages' => [['id' => 'm1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued']],
        ]);
        $batch = $this->makeClient($http)->messages->sendBatch([
            'channel' => 'sms',
            'messages' => [['to' => ['phone_number' => '+96550001234'], 'content' => ['body' => 'hi']]],
        ]);
        $this->assertNull($batch->skipped_count);
        $this->assertNull($batch->skipped);
    }
}
