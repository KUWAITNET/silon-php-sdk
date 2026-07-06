<?php

declare(strict_types=1);

namespace Silon\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Silon\Exception\WebhookSignatureVerificationException;
use Silon\Model\Event;
use Silon\Webhooks;

final class WebhooksTest extends BaseTestCase
{
    private const SECRET = 'whsec_test_secret';

    private function payload(): string
    {
        return (string) json_encode([
            'id' => 'evt_01J1',
            'object' => 'event',
            'type' => 'broadcast.completed',
            'api_version' => '2026-06-28',
            'created' => '2026-07-02T10:00:00Z',
            'data' => ['id' => 'br_1', 'object' => 'broadcast', 'target_count' => 100, 'sent' => 97, 'failed' => 3],
        ]);
    }

    public function testSignVerifyRoundtrip(): void
    {
        $payload = $this->payload();
        $header = Webhooks::sign(self::SECRET, $payload);
        $this->assertTrue(Webhooks::verifySignature($payload, $header, self::SECRET));
    }

    public function testHeaderFormat(): void
    {
        $header = Webhooks::sign(self::SECRET, 'x', 1_700_000_000);
        $this->assertStringStartsWith('t=1700000000,v1=', $header);
    }

    public function testWrongSecretRejected(): void
    {
        $header = Webhooks::sign('whsec_right', 'x');
        $this->assertFalse(Webhooks::verifySignature('x', $header, 'whsec_wrong'));
    }

    public function testTamperedPayloadRejected(): void
    {
        $payload = $this->payload();
        $header = Webhooks::sign(self::SECRET, $payload);
        $tampered = str_replace('"sent":97', '"sent":100', $payload);
        $this->assertNotSame($payload, $tampered);
        $this->assertFalse(Webhooks::verifySignature($tampered, $header, self::SECRET));
    }

    public function testStaleTimestampRejected(): void
    {
        $stale = time() - 3600;
        $header = Webhooks::sign(self::SECRET, $this->payload(), $stale);
        $this->assertFalse(Webhooks::verifySignature($this->payload(), $header, self::SECRET));
    }

    public function testToleranceZeroSkipsFreshnessCheck(): void
    {
        $stale = time() - 3600;
        $header = Webhooks::sign(self::SECRET, $this->payload(), $stale);
        $this->assertTrue(Webhooks::verifySignature($this->payload(), $header, self::SECRET, 0));
    }

    /**
     * @return list<array{string}>
     */
    public static function malformedHeaders(): array
    {
        return [[''], ['garbage'], ['t=notdigits,v1=aa'], ['t=123'], ['v1=aa']];
    }

    #[DataProvider('malformedHeaders')]
    public function testMalformedHeadersRejected(string $header): void
    {
        $this->assertFalse(Webhooks::verifySignature($this->payload(), $header, self::SECRET));
    }

    public function testConstructEventReturnsTypedEvent(): void
    {
        $header = Webhooks::sign(self::SECRET, $this->payload());
        $event = Webhooks::constructEvent($this->payload(), $header, self::SECRET);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('broadcast.completed', $event->type);
        $this->assertSame(97, $event->data->sent);
        $this->assertSame(3, $event->data->failed);
    }

    public function testConstructEventRaisesOnBadSignature(): void
    {
        $header = Webhooks::sign('whsec_other', $this->payload());
        $this->expectException(WebhookSignatureVerificationException::class);
        $this->expectExceptionMessage('verification failed');
        Webhooks::constructEvent($this->payload(), $header, self::SECRET);
    }

    public function testSignatureHeaderConstant(): void
    {
        $this->assertSame('Silon-Signature', Webhooks::SIGNATURE_HEADER);
    }
}
