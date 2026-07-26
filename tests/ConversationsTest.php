<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ConflictException;
use Silon\Model\Conversation;
use Silon\Model\ConversationMessage;

final class ConversationsTest extends TestCase
{
    private const CONVERSATIONS = '/api/v1/conversations';
    private const CONV_ID = '263b2c1b-b5a7-414e-accc-a4fe6ea4541e';

    private const CONVERSATION = [
        'id' => self::CONV_ID, 'object' => 'conversation', 'channel' => 'whatsapp',
        'status' => 'open', 'priority' => null, 'subject' => 'Dana K',
        'external_id' => '96551230000', 'client_id' => 'CUST-3A', 'labels' => ['vip'],
        'unread' => 1, 'archived' => false,
        'created' => '2026-07-26T09:28:01Z', 'updated' => '2026-07-26T09:28:05Z',
    ];

    private const MESSAGE = [
        'id' => 1613, 'object' => 'message', 'conversation_id' => self::CONV_ID,
        'body' => 'Your order ships today.', 'type' => 'text', 'direction' => 'outbound',
        'author' => 'operator', 'internal' => false, 'delivery_status' => 'sent',
        'created' => '2026-07-26T09:30:04Z',
    ];

    public function testListSendsFilters(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::CONVERSATION], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->conversations->list([
            'status' => 'open', 'channel' => 'whatsapp', 'assignee' => 'none', 'limit' => 25,
        ]);
        $rows = iterator_to_array($page);
        $this->assertCount(1, $rows);
        $this->assertInstanceOf(Conversation::class, $rows[0]);
        $this->assertSame(1, $rows[0]->unread);
        $this->assertSame(['vip'], $rows[0]->labels);
        $query = $this->query($http->last());
        $this->assertSame('open', $query['status']);
        $this->assertSame('none', $query['assignee']);
        $this->assertSame('25', $query['limit']);
    }

    public function testListOmitsUnsetFilters(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [], 'next' => null, 'previous' => null]);
        $this->makeClient($http)->conversations->list();
        $this->assertSame([], $this->query($http->last()));
    }

    public function testRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::CONVERSATION);
        $conv = $this->makeClient($http)->conversations->retrieve(self::CONV_ID);
        $this->assertSame(self::CONV_ID, $conv->id);
        $this->assertSame('whatsapp', $conv->channel);
        $this->assertSame(self::CONVERSATIONS . '/' . self::CONV_ID, $this->path($http->last()));
    }

    public function testUpdateSendsOnlySuppliedFields(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::CONVERSATION, 'status' => 'resolved']);
        $conv = $this->makeClient($http)->conversations->update(self::CONV_ID, [
            'status' => 'resolved', 'priority' => 'high',
        ]);
        $this->assertSame('resolved', $conv->status);
        $this->assertEquals(['status' => 'resolved', 'priority' => 'high'], $this->body($http->last()));
    }

    public function testUpdateReplacesLabels(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::CONVERSATION);
        $this->makeClient($http)->conversations->update(self::CONV_ID, ['labels' => ['vip', 'bug']]);
        $this->assertEquals(['labels' => ['vip', 'bug']], $this->body($http->last()));
    }

    public function testListMessages(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::MESSAGE], 'next' => null, 'previous' => null]);
        $rows = iterator_to_array($this->makeClient($http)->conversations->listMessages(self::CONV_ID));
        $this->assertInstanceOf(ConversationMessage::class, $rows[0]);
        $this->assertSame('outbound', $rows[0]->direction);
        $this->assertSame('operator', $rows[0]->author);
    }

    public function testReply(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::MESSAGE);
        $msg = $this->makeClient($http)->conversations->reply(self::CONV_ID, [
            'body' => 'Your order ships today.',
        ]);
        $this->assertSame('sent', $msg->delivery_status);
        $this->assertEquals(['body' => 'Your order ships today.'], $this->body($http->last()));
    }

    public function testReplyInternalNote(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::MESSAGE, 'internal' => true]);
        $msg = $this->makeClient($http)->conversations->reply(self::CONV_ID, [
            'body' => 'Checking.', 'internal' => true,
        ]);
        $this->assertTrue($msg->internal);
        $this->assertEquals(['body' => 'Checking.', 'internal' => true], $this->body($http->last()));
    }

    public function testReplyClosedWindowThrowsConflict(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(409, [
            'type' => 'https://acme.silon.tech/docs/errors/messaging-window-closed',
            'title' => 'Messaging window closed', 'status' => 409,
            'detail' => 'Messaging window has expired.',
        ]);
        $this->expectException(ConflictException::class);
        $this->makeClient($http)->conversations->reply(self::CONV_ID, ['body' => 'late']);
    }
}
