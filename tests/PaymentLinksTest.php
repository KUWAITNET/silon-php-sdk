<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\ConflictException;
use Silon\Model\PaymentLink;

final class PaymentLinksTest extends TestCase
{
    private const LINKS = '/api/v1/payment_links';
    private const LINK_ID = 'pl_ef54c50946d7a22870eacc9a2649f428';

    private const LINK = [
        'id' => self::LINK_ID, 'object' => 'payment_link', 'status' => 'pending',
        'livemode' => true, 'amount' => '10.500', 'tax_amount' => '1.575',
        'total' => '12.075', 'tax_rate' => '15.00', 'currency' => 'KWD',
        'description' => 'Order #1042',
        'url' => 'https://acme.silon.tech/pay/' . self::LINK_ID . '/',
        'expires_at' => '2026-07-29T15:40:00Z', 'created' => '2026-07-26T15:40:00Z',
    ];

    public function testCreate(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::LINK);
        $link = $this->makeClient($http)->paymentLinks->create([
            'amount' => '10.500', 'currency' => 'KWD', 'tax_rate' => '15',
            'description' => 'Order #1042',
        ]);

        $this->assertInstanceOf(PaymentLink::class, $link);
        $this->assertSame(self::LINK_ID, $link->id);
        $this->assertStringContainsString('/pay/' . self::LINK_ID . '/', $link->url);
        $this->assertSame(self::LINKS, $this->path($http->last()));
        $this->assertEquals(
            ['amount' => '10.500', 'currency' => 'KWD', 'tax_rate' => '15',
             'description' => 'Order #1042'],
            $this->body($http->last()),
        );
    }

    public function testMoneyIsAStringSoItAddsUpExactly(): void
    {
        // The reason the wire format is a string: bcadd on the strings is
        // exact, where (float) '10.500' + (float) '1.575' is not.
        $http = new MockHttpClient();
        $http->pushJson(201, self::LINK);
        $link = $this->makeClient($http)->paymentLinks->create(['amount' => '10.500']);

        $this->assertIsString($link->amount);
        $this->assertSame($link->total, bcadd($link->amount, $link->tax_amount, 3));
    }

    public function testCreateOmitsUnsetFields(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::LINK);
        $this->makeClient($http)->paymentLinks->create(['amount' => '5']);
        $this->assertEquals(['amount' => '5'], $this->body($http->last()));
    }

    public function testNeverExpiresIsSentExplicitly(): void
    {
        // 0 means "never"; dropping it would silently apply the 72h default.
        $http = new MockHttpClient();
        $http->pushJson(201, self::LINK);
        $this->makeClient($http)->paymentLinks->create(['amount' => '5', 'expires_in_hours' => 0]);
        $this->assertSame(0, $this->body($http->last())['expires_in_hours']);
    }

    public function testRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::LINK);
        $link = $this->makeClient($http)->paymentLinks->retrieve(self::LINK_ID);

        $this->assertSame('pending', $link->status);
        $this->assertSame(self::LINKS . '/' . self::LINK_ID, $this->path($http->last()));
        $this->assertNotNull($link->expires_at);
    }

    public function testCancel(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::LINK, 'status' => 'cancelled',
                              'cancelled_at' => '2026-07-26T16:00:00Z']);
        $link = $this->makeClient($http)->paymentLinks->cancel(self::LINK_ID);

        $this->assertSame('cancelled', $link->status);
        $this->assertNotNull($link->cancelled_at);
        $this->assertSame(self::LINKS . '/' . self::LINK_ID . '/cancel', $this->path($http->last()));
    }

    public function testCancellingAPaidLinkThrowsConflict(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(409, ['title' => 'Already paid', 'status' => 409,
                              'detail' => 'Refund it in the gateway.']);
        $this->expectException(ConflictException::class);
        $this->makeClient($http)->paymentLinks->cancel(self::LINK_ID);
    }

    public function testListSendsFilters(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['results' => [self::LINK], 'next' => null, 'previous' => null]);
        $page = $this->makeClient($http)->paymentLinks->list(['status' => 'pending', 'limit' => 10]);

        $rows = iterator_to_array($page);
        $this->assertCount(1, $rows);
        $this->assertSame('12.075', $rows[0]->total);
        $query = $this->query($http->last());
        $this->assertSame('pending', $query['status']);
        $this->assertSame('10', $query['limit']);
    }
}
