<?php

declare(strict_types=1);

namespace Silon\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Silon\Exception\SilonException;

final class PushTest extends TestCase
{
    public function testSubscribeAndroid(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['success' => 1]);
        $result = $this->makeClient($http)->push->subscribeAndroid(['slug' => 'consumer-app', 'token' => 'fcm-token']);
        $this->assertSame(1, $result->success);
        $this->assertEquals(['slug' => 'consumer-app', 'token' => 'fcm-token'], $this->body($http->last()));
        $this->assertSame('/api/v1/subscribe/android/', $this->path($http->last()));
    }

    public function testSubscribeIosWithEnvironment(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['success' => 1]);
        $this->makeClient($http)->push->subscribeIos(['slug' => 'consumer-app', 'token' => 'apns-token', 'environment' => 'prod']);
        $this->assertSame('prod', $this->body($http->last())['environment']);
        $this->assertSame('/api/v1/subscribe/ios/', $this->path($http->last()));
    }

    public function testUpsertDevices(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['client_id' => 'cust_001', 'slug' => 'consumer-app', 'device_id' => 'd1', 'device_type' => 'android', 'keep_devices' => false]);
        $result = $this->makeClient($http)->push->upsertDevices(['client_id' => 'cust_001', 'slug' => 'consumer-app', 'device_type' => 'android', 'device_id' => 'd1', 'keep_devices' => false]);
        $this->assertSame('cust_001', $result->client_id);
        $this->assertFalse($this->body($http->last())['keep_devices']);
    }

    public function testMarkRead(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['affected_rows' => 2]);
        $this->assertSame(2, $this->makeClient($http)->push->markRead(['slug' => 'consumer-app'])->affected_rows);
    }

    /**
     * @return list<array{string|null,string}>
     */
    public static function platformPaths(): array
    {
        return [
            ['android', '/api/v1/push/android/consumer-app/'],
            ['ios', '/api/v1/push/ios/consumer-app/'],
            [null, '/api/v1/push/list/consumer-app/'],
        ];
    }

    #[DataProvider('platformPaths')]
    public function testListNotificationsPathsAndDeprecation(?string $platform, string $expectedPath): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [['message' => 'Hello', 'subject' => 'Hi', 'date' => '2026-07-01']]);
        $notifications = null;
        $deprecations = $this->captureDeprecations(function () use ($http, $platform, &$notifications) {
            $notifications = $this->makeClient($http)->push->listNotifications('consumer-app', $platform);
        });
        $this->assertNotEmpty($deprecations);
        $this->assertSame(1, $http->callCount());
        $this->assertSame($expectedPath, $this->path($http->last()));
        $this->assertSame('Hi', $notifications[0]->subject);
    }

    public function testListNotificationsRejectsUnknownPlatform(): void
    {
        $client = $this->makeClient(new MockHttpClient());
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('platform');
        $client->push->listNotifications('consumer-app', 'windows');
    }

    public function testSubscribeWebIsDeprecated(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['client_id' => 'cust_001', 'slug' => 'widget', 'subscription_info' => '{}']);
        $result = null;
        $deprecations = $this->captureDeprecations(function () use ($http, &$result) {
            $result = $this->makeClient($http)->push->subscribeWeb(['client_id' => 'cust_001', 'slug' => 'widget', 'subscription_info' => '{}']);
        });
        $this->assertNotEmpty($deprecations);
        $this->assertSame('widget', $result->slug);
    }
}
