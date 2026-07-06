<?php

declare(strict_types=1);

namespace Silon\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Silon\Model\BatchAccepted;
use Silon\Model\Broadcast;
use Silon\Model\BroadcastAccepted;
use Silon\Model\Event;
use Silon\Model\MessageAccepted;
use Silon\Model\MessageStatus;
use Silon\Model\OtpSendResult;
use Silon\Model\OtpVerifyResult;
use Silon\Model\WebhookEndpoint;
use Silon\Model\WebhookEndpointWithSecret;

final class LivemodeTest extends TestCase
{
    private const MESSAGE_ACCEPTED = ['id' => 'm1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued'];
    private const MESSAGE_STATUS = ['event_id' => 'evt-1', 'is_sent' => true, 'messages' => []];
    private const BATCH_ACCEPTED = ['id' => 'b1', 'object' => 'batch', 'messages' => [['id' => 'm1', 'object' => 'message', 'channel' => 'sms', 'status' => 'queued']]];
    private const BROADCAST_ACCEPTED = ['id' => 'br1', 'object' => 'broadcast', 'channel' => 'email', 'status' => 'queued', 'target_count' => 240, 'skipped_count' => 3];
    private const BROADCAST_DETAIL = ['id' => 'br1', 'channel' => 'email', 'target_count' => 240, 'queued' => 0, 'sent' => 238, 'failed' => 2, 'status' => 'completed'];
    private const OTP_SEND = ['otp_id' => 'otp1', 'expires_at' => '2026-07-04T00:05:00Z', 'channel' => 'sms'];
    private const OTP_VERIFY = ['verified' => true, 'purpose' => 'login', 'verified_at' => '2026-07-04T00:01:00Z'];
    private const EVENT = ['id' => 'evt1', 'object' => 'event', 'type' => 'message.delivered', 'api_version' => 'v1', 'created' => '2026-07-04T00:00:03Z', 'data' => ['id' => 'm1', 'object' => 'message', 'status' => 'delivered']];
    private const WEBHOOK_ENDPOINT = ['id' => 'we1', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/hooks/silon', 'description' => '', 'enabled_events' => ['*'], 'status' => 'enabled'];

    /**
     * @return array<string,array{class-string,array<string,mixed>}>
     */
    public static function fixtures(): array
    {
        return [
            'MessageAccepted' => [MessageAccepted::class, self::MESSAGE_ACCEPTED],
            'MessageStatus' => [MessageStatus::class, self::MESSAGE_STATUS],
            'BatchAccepted' => [BatchAccepted::class, self::BATCH_ACCEPTED],
            'BroadcastAccepted' => [BroadcastAccepted::class, self::BROADCAST_ACCEPTED],
            'Broadcast' => [Broadcast::class, self::BROADCAST_DETAIL],
            'OtpSendResult' => [OtpSendResult::class, self::OTP_SEND],
            'OtpVerifyResult' => [OtpVerifyResult::class, self::OTP_VERIFY],
            'Event' => [Event::class, self::EVENT],
            'WebhookEndpoint' => [WebhookEndpoint::class, self::WEBHOOK_ENDPOINT],
        ];
    }

    /**
     * @param class-string $modelClass
     * @param array<string,mixed> $fixture
     */
    #[DataProvider('fixtures')]
    public function testLivemodeDecodesTypedTrue(string $modelClass, array $fixture): void
    {
        $decoded = new $modelClass([...$fixture, 'livemode' => true]);
        $this->assertTrue($decoded->livemode);
    }

    /**
     * @param class-string $modelClass
     * @param array<string,mixed> $fixture
     */
    #[DataProvider('fixtures')]
    public function testLivemodeDecodesTypedFalse(string $modelClass, array $fixture): void
    {
        $decoded = new $modelClass([...$fixture, 'livemode' => false]);
        $this->assertFalse($decoded->livemode);
    }

    /**
     * @param class-string $modelClass
     * @param array<string,mixed> $fixture
     */
    #[DataProvider('fixtures')]
    public function testLivemodeDefaultsTrueWhenAbsent(string $modelClass, array $fixture): void
    {
        $decoded = new $modelClass($fixture);
        $this->assertTrue($decoded->livemode);
    }

    public function testEventDataLivemodeDecodes(): void
    {
        $event = new Event([...self::EVENT, 'livemode' => false, 'data' => [...self::EVENT['data'], 'livemode' => false]]);
        $this->assertFalse($event->livemode);
        $this->assertFalse($event->data->livemode);
        // Optional on the data payload — absent stays null, not defaulted.
        $this->assertNull((new Event(self::EVENT))->data->livemode);
    }

    public function testSendTestModeResponseLivemodeFalse(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, [...self::MESSAGE_ACCEPTED, 'livemode' => false]);
        $sent = $this->makeClient($http)->messages->send(['channel' => 'sms', 'to' => ['phone_number' => '+15005550001'], 'content' => ['body' => 'ping']]);
        $this->assertFalse($sent->livemode);
    }

    public function testWebhookCreateSerializesLivemodeFalse(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::WEBHOOK_ENDPOINT, 'livemode' => false, 'secret' => 'whsec_test1']);
        $created = $this->makeClient($http)->webhookEndpoints->create(['url' => 'https://example.com/hooks/silon', 'livemode' => false]);
        $this->assertInstanceOf(WebhookEndpointWithSecret::class, $created);
        $this->assertFalse($created->livemode);
        $this->assertEquals(['url' => 'https://example.com/hooks/silon', 'livemode' => false], $this->body($http->last()));
    }

    public function testWebhookCreateSerializesLivemodeTrue(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::WEBHOOK_ENDPOINT, 'livemode' => true, 'secret' => 'whsec_test2']);
        $created = $this->makeClient($http)->webhookEndpoints->create(['url' => 'https://example.com/hooks/silon', 'livemode' => true]);
        $this->assertTrue($created->livemode);
        $this->assertEquals(['url' => 'https://example.com/hooks/silon', 'livemode' => true], $this->body($http->last()));
    }

    public function testWebhookCreateOmitsLivemodeByDefault(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, [...self::WEBHOOK_ENDPOINT, 'livemode' => true, 'secret' => 'whsec_test3']);
        $this->makeClient($http)->webhookEndpoints->create(['url' => 'https://example.com/hooks/silon']);
        $this->assertArrayNotHasKey('livemode', $this->body($http->last()));
    }
}
