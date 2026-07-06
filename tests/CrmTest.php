<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Http\Request;
use Silon\Model\ClientGroup;
use Silon\Model\ClientProfile;

final class CrmTest extends TestCase
{
    private const CLIENTS = '/api/v1/crm/clients/';
    private const GROUPS = '/api/v1/crm/groups/';

    private const CLIENT = [
        'client_id' => 'cust_001', 'first_name' => 'Sara', 'last_name' => 'Ahmad',
        'email' => 'sara@example.com', 'phone_number' => '+96512345678', 'civil_id' => null,
        'notes' => '', 'default_language' => 'en', 'default_channel' => 'whatsapp',
    ];

    private const GROUP = ['id' => 7, 'name' => 'VIP', 'slug' => 'vip', 'is_active' => true, 'clients' => [self::CLIENT]];

    private static function page(array $results, ?string $next = null): array
    {
        return ['results' => $results, 'next' => $next, 'previous' => null];
    }

    public function testClientsListReturnsCursorPage(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::page([self::CLIENT, [...self::CLIENT, 'client_id' => 'cust_002']]));
        $page = $this->makeClient($http)->clients->list(['limit' => 50]);
        $this->assertCount(2, $page);
        $this->assertInstanceOf(ClientProfile::class, $page[0]);
        $this->assertSame('cust_002', $page[1]->client_id);
        $this->assertFalse($page->hasNextPage());
        $this->assertSame('50', $this->query($http->last())['limit']);
    }

    public function testClientsListCallSitesStillWork(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::page([self::CLIENT, [...self::CLIENT, 'client_id' => 'cust_002']]));
        $result = $this->makeClient($http)->clients->list();
        $ids = [];
        foreach ($result as $c) {
            $ids[] = $c->client_id;
        }
        $this->assertSame(['cust_001', 'cust_002'], $ids);
        $this->assertSame('cust_001', $result[0]->client_id);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(ClientProfile::class, $result[0]);
    }

    public function testClientsAutoPaging(): void
    {
        $http = new MockHttpClient();
        $pages = [
            '' => self::page([self::CLIENT, [...self::CLIENT, 'client_id' => 'cust_002']], self::BASE_URL . self::CLIENTS . '?cursor=pg2&limit=2'),
            'pg2' => self::page([[...self::CLIENT, 'client_id' => 'cust_003']]),
        ];
        $http->setHandler(static function (Request $r) use ($pages) {
            $q = [];
            parse_str((string) parse_url($r->url, PHP_URL_QUERY), $q);

            return MockHttpClient::jsonResponse(200, $pages[$q['cursor'] ?? '']);
        });
        $ids = [];
        foreach ($this->makeClient($http)->clients->list(['limit' => 2])->autoPaging() as $c) {
            $ids[] = $c->client_id;
        }
        $this->assertSame(['cust_001', 'cust_002', 'cust_003'], $ids);
        $this->assertSame(2, $http->callCount());
        $this->assertSame('pg2', $this->query($http->last())['cursor']);
    }

    public function testClientCreateDropsOmittedFields(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::CLIENT);
        $created = $this->makeClient($http)->clients->create(['client_id' => 'cust_001', 'first_name' => 'Sara', 'phone_number' => '+96512345678']);
        $this->assertSame('cust_001', $created->client_id);
        $this->assertEquals(['client_id' => 'cust_001', 'first_name' => 'Sara', 'phone_number' => '+96512345678'], $this->body($http->last()));
    }

    public function testClientRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::CLIENT);
        $this->assertSame('sara@example.com', $this->makeClient($http)->clients->retrieve('cust_001')->email);
    }

    public function testClientUpdateUsesPatch(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::CLIENT, 'notes' => 'vip']);
        $updated = $this->makeClient($http)->clients->update('cust_001', ['notes' => 'vip']);
        $this->assertSame('vip', $updated->notes);
        $this->assertSame('PATCH', $http->last()->method);
        $this->assertEquals(['notes' => 'vip'], $this->body($http->last()));
    }

    public function testClientReplaceUsesPut(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::CLIENT);
        $this->makeClient($http)->clients->replace('cust_001', ['first_name' => 'Sara', 'last_name' => 'Ahmad', 'email' => 'sara@example.com', 'phone_number' => '+96512345678']);
        $this->assertSame('PUT', $http->last()->method);
    }

    public function testClientDelete(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(204, '');
        $this->assertNull($this->makeClient($http)->clients->delete('cust_001'));
        $this->assertSame(1, $http->callCount());
    }

    public function testGroupsListReturnsCursorPage(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::page([self::GROUP]));
        $page = $this->makeClient($http)->clientGroups->list();
        $this->assertInstanceOf(ClientGroup::class, $page[0]);
        $this->assertSame('cust_001', $page[0]->clients[0]->client_id);
        $this->assertFalse($page->hasNextPage());
    }

    public function testGroupCreateWithMembership(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::GROUP);
        $group = $this->makeClient($http)->clientGroups->create(['name' => 'VIP', 'slug' => 'vip', 'client_ids' => ['cust_001', 'cust_002']]);
        $this->assertSame('vip', $group->slug);
        $this->assertEquals(['name' => 'VIP', 'slug' => 'vip', 'client_ids' => ['cust_001', 'cust_002']], $this->body($http->last()));
    }

    public function testGroupUpdateMembership(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::GROUP);
        $this->makeClient($http)->clientGroups->update(7, ['client_ids' => ['cust_003'], 'is_active' => false]);
        $this->assertEquals(['client_ids' => ['cust_003'], 'is_active' => false], $this->body($http->last()));
        $this->assertSame('/api/v1/crm/groups/7/', $this->path($http->last()));
    }

    public function testGroupReplaceAndDelete(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::GROUP);
        $http->pushJson(204, '');
        $client = $this->makeClient($http);
        $client->clientGroups->replace(7, ['name' => 'VIP', 'slug' => 'vip', 'client_ids' => []]);
        $this->assertSame('PUT', $http->requests[0]->method);
        $this->assertNull($client->clientGroups->delete(7));
    }
}
