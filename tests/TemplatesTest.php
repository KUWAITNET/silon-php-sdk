<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ConflictException;
use Silon\Exception\NotFoundException;
use Silon\Model\Template;
use Silon\Model\TemplateDetail;

final class TemplatesTest extends TestCase
{
    private const TEMPLATES = '/api/v1/templates/';

    private const ROW = [
        'slug' => 'order-shipped', 'object' => 'template', 'channel' => 'sms',
        'subject' => 'Your order is on its way', 'version' => 3,
        'created' => '2026-06-01T10:00:00+00:00', 'updated' => '2026-07-01T09:30:00+00:00',
    ];

    private static function detail(): array
    {
        return [
            ...self::ROW,
            'body' => '<p>Hi {{ client_name }}, order {{ order_id }} has shipped.</p>',
            'body_md' => 'Hi {{ client_name }}, order **{{ order_id }}** has shipped.',
            'versions' => [1, 2, 3],
        ];
    }

    public function testListPaginated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::ROW], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->templates->list(['channel' => 'sms', 'q' => 'order', 'limit' => 10]);
        $row = $page[0];
        $this->assertInstanceOf(Template::class, $row);
        $this->assertSame('order-shipped', $row->slug);
        $this->assertSame(3, $row->version);
        $this->assertSame('sms', $row->channel);
        $this->assertEquals(['channel' => 'sms', 'q' => 'order', 'limit' => '10'], $this->query($http->last()));
    }

    public function testCreateReturnsDetailAtVersion1(): void
    {
        $http = new MockHttpClient();
        $created = [
            'slug' => 'welcome', 'object' => 'template', 'channel' => null, 'subject' => 'Welcome aboard',
            'version' => 1, 'created' => '2026-07-05T10:00:00+00:00', 'updated' => '2026-07-05T10:00:00+00:00',
            'body' => '<p>Welcome, {{ client_name }}!</p>', 'body_md' => 'Welcome, {{ client_name }}!', 'versions' => [1],
        ];
        $http->pushJson(201, $created);
        $tmpl = $this->makeClient($http)->templates->create([
            'slug' => 'welcome', 'subject' => 'Welcome aboard',
            'body' => '<p>Welcome, {{ client_name }}!</p>', 'body_md' => 'Welcome, {{ client_name }}!',
        ]);
        $this->assertInstanceOf(TemplateDetail::class, $tmpl);
        $this->assertSame(1, $tmpl->version);
        $this->assertSame([1], $tmpl->versions);
        $this->assertNull($tmpl->channel);
        $this->assertEquals([
            'slug' => 'welcome', 'subject' => 'Welcome aboard',
            'body' => '<p>Welcome, {{ client_name }}!</p>', 'body_md' => 'Welcome, {{ client_name }}!',
        ], $this->body($http->last()));
    }

    public function testCreateDuplicateSlugConflicts(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(409, [
            'type' => 'https://acme.silon.tech/docs/errors/template-exists',
            'title' => 'Template already exists', 'status' => 409,
            'detail' => "A template with slug='welcome' already exists.", 'field' => 'slug', 'retryable' => false,
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->templates->create(['slug' => 'welcome']);
            $this->fail('expected ConflictException');
        } catch (ConflictException $err) {
            $this->assertSame('template-exists', $err->errors[0]->code);
            $this->assertFalse($err->retryable);
        }
    }

    public function testRetrieveDetailHasVersions(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::detail());
        $tmpl = $this->makeClient($http)->templates->retrieve('order-shipped');
        $this->assertInstanceOf(TemplateDetail::class, $tmpl);
        $this->assertSame([1, 2, 3], $tmpl->versions);
        $this->assertSame(3, $tmpl->version);
        $this->assertStringStartsWith('Hi {{ client_name }}', $tmpl->body_md);
        $this->assertSame('2026-06-01T10:00:00+00:00', $tmpl->created->format('Y-m-d\TH:i:sP'));
        $this->assertSame('/api/v1/templates/order-shipped/', $this->path($http->last()));
    }

    public function testUpdateMintsNewVersion(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::detail(), 'version' => 4, 'versions' => [1, 2, 3, 4]]);
        $tmpl = $this->makeClient($http)->templates->update('order-shipped', ['body_md' => 'Hi {{ client_name }} — your order shipped today.']);
        $this->assertSame(4, $tmpl->version);
        $this->assertSame([1, 2, 3, 4], $tmpl->versions);
        $this->assertEquals(['body_md' => 'Hi {{ client_name }} — your order shipped today.'], $this->body($http->last()));
    }

    public function testUpdateChannelOnlyNoVersionBump(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::detail(), 'channel' => 'whatsapp']);
        $tmpl = $this->makeClient($http)->templates->update('order-shipped', ['channel' => 'whatsapp']);
        $this->assertSame('whatsapp', $tmpl->channel);
        $this->assertSame(3, $tmpl->version);
        $this->assertEquals(['channel' => 'whatsapp'], $this->body($http->last()));
    }

    public function testDeleteArchives(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(204, '');
        $this->assertNull($this->makeClient($http)->templates->delete('order-shipped'));
    }

    public function testRetrieveUnknownSlug404(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(404, [
            'type' => 'https://acme.silon.tech/docs/errors/template-not-found',
            'title' => 'Template not found', 'status' => 404, 'detail' => "No template with slug='nope'.", 'retryable' => false,
        ], ['Content-Type' => 'application/problem+json']);
        try {
            $this->makeClient($http)->templates->retrieve('nope');
            $this->fail('expected NotFoundException');
        } catch (NotFoundException $err) {
            $this->assertSame('template-not-found', $err->errors[0]->code);
        }
    }
}
