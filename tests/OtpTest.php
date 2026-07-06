<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\BadRequestException;
use Silon\Exception\GoneException;

final class OtpTest extends TestCase
{
    private const SEND = '/api/v1/otp/send/';
    private const VERIFY = '/api/v1/otp/verify/';

    private const SEND_RESPONSE = ['otp_id' => 'otp-1', 'expires_at' => '2026-07-02T12:05:00Z', 'channel' => 'sms', 'task_ids' => ['t1']];

    public function testSend(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(202, self::SEND_RESPONSE);
        $result = $this->makeClient($http)->otp->send(['purpose' => 'login', 'to' => ['phone_number' => '+96512345678']]);
        $this->assertSame('otp-1', $result->otp_id);
        $this->assertSame('sms', $result->channel);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->expires_at);
        $this->assertEquals(['purpose' => 'login', 'to' => ['phone_number' => '+96512345678']], $this->body($http->last()));
        $this->assertNotSame('', $http->last()->getHeaderLine('Idempotency-Key'));
    }

    public function testVerifySuccess(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['verified' => true, 'purpose' => 'login', 'verified_at' => '2026-07-02T12:01:00Z']);
        $result = $this->makeClient($http)->otp->verify(['otp_id' => 'otp-1', 'code' => ' 424242 ']);
        $this->assertTrue($result->verified);
        $this->assertSame('login', $result->purpose);
        $this->assertEquals(['otp_id' => 'otp-1', 'code' => ' 424242 '], $this->body($http->last()));
    }

    public function testVerifyWrongCodeExposesRemainingAttempts(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(400, ['verified' => false, 'remaining_attempts' => 2]);
        try {
            $this->makeClient($http)->otp->verify(['otp_id' => 'otp-1', 'code' => '000000']);
            $this->fail('expected BadRequestException');
        } catch (BadRequestException $err) {
            $this->assertSame(['verified' => false, 'remaining_attempts' => 2], $err->body);
        }
    }

    public function testVerifyExpiredRaisesGone(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(410, [
            'type' => 'https://acme.silon.tech/docs/errors/otp-expired',
            'title' => 'Gone', 'status' => 410, 'detail' => 'This code has expired.', 'field' => null,
        ]);
        $this->expectException(GoneException::class);
        $this->expectExceptionMessage('expired');
        $this->makeClient($http)->otp->verify(['otp_id' => 'otp-1', 'code' => '424242']);
    }
}
