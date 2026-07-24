<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Model\ConversationsReport;
use Silon\Model\ProviderBalance;
use Silon\Model\Report;

final class ReportsTest extends TestCase
{
    private const REPORTS = '/api/v1/reports';

    private const RESPONSE = [
        'report_data' => [['client_id' => 'cust_001', 'status' => 'sent', 'retries' => 0]],
        'total_items' => 1, 'total_pages' => 1, 'page' => 1, 'report_type' => 'phone',
    ];

    public function testMessagesReport(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::RESPONSE);
        $report = $this->makeClient($http)->reports->messages(['report_type' => 'phone', 'date_from' => '2026-06-01', 'status' => ['sent', 'read']]);
        $this->assertInstanceOf(Report::class, $report);
        $this->assertSame(1, $report->total_items);
        $this->assertSame('cust_001', $report->report_data[0]['client_id']);
        $this->assertEquals(['report_type' => 'phone', 'date_from' => '2026-06-01', 'status' => ['sent', 'read']], $this->body($http->last()));
        $this->assertSame('/api/v1/reports/messages/', $this->path($http->last()));
    }

    public function testChannelsReport(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::RESPONSE, 'report_type' => null]);
        $this->makeClient($http)->reports->channels(['channel_name' => ['whatsapp']]);
        $this->assertEquals(['channel_name' => ['whatsapp']], $this->body($http->last()));
    }

    public function testSpecificBulksReport(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::RESPONSE);
        $this->makeClient($http)->reports->specificBulks(['bulks_file' => 12, 'status' => ['SENT']]);
        $this->assertEquals(['bulks_file' => 12, 'status' => ['SENT']], $this->body($http->last()));
    }

    public function testSubscriptionsReport(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::RESPONSE);
        $this->makeClient($http)->reports->subscriptions(['report_type' => 'mobile', 'device_type' => ['android']]);
        $this->assertEquals(['report_type' => 'mobile', 'device_type' => ['android']], $this->body($http->last()));
    }

    public function testAwsUsagePageParam(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::RESPONSE);
        $this->makeClient($http)->reports->awsUsage(['page' => 3]);
        $this->assertSame('3', $this->query($http->last())['page']);
        $this->assertSame('/api/v1/reports/aws-usage-statistics/', $this->path($http->last()));
    }

    public function testBalance(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['balance' => '12.50']);
        $balance = $this->makeClient($http)->reports->balance('twilio-main');
        $this->assertInstanceOf(ProviderBalance::class, $balance);
        $this->assertSame('12.50', $balance->balance);
        $this->assertSame('/api/v1/reports/balance/twilio-main/', $this->path($http->last()));
    }

    public function testConversationsReport(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'group_by' => 'agent',
            'business_hours' => false,
            'totals' => ['resolutions_count' => 3, 'csat_average' => 4.5],
            'rows' => [['key' => 1, 'name' => 'Sara', 'resolutions_count' => 3]],
        ]);
        $report = $this->makeClient($http)->reports->conversations(['group_by' => 'agent', 'compare' => true]);
        $this->assertInstanceOf(ConversationsReport::class, $report);
        $this->assertSame(3, $report->totals['resolutions_count']);
        $this->assertSame('Sara', $report->rows[0]['name']);
        $this->assertSame('/api/v1/reports/conversations/', $this->path($http->last()));
        $query = $this->query($http->last());
        $this->assertSame('agent', $query['group_by']);
        $this->assertSame('1', $query['compare']);
    }

    public function testUsersAndBulks(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::RESPONSE);
        $http->pushJson(200, self::RESPONSE);
        $client = $this->makeClient($http);
        $client->reports->users(['search' => 'sara']);
        $this->assertEquals(['search' => 'sara'], $this->body($http->requests[0]));
        $client->reports->bulks(['date_from' => '2026-06-01', 'date_to' => '2026-06-30']);
        $this->assertEquals(['date_from' => '2026-06-01', 'date_to' => '2026-06-30'], $this->body($http->requests[1]));
    }
}
