<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * 202 body of `POST /api/v1/otp/send/`.
 */
final class OtpSendResult extends Model
{
    /** Opaque id for this OTP; pass it back to `otp.verify`. */
    public string $otp_id = '';

    /** Verifying after this raises {@see \Silon\Exception\GoneException} (410). */
    public ?DateTimeImmutable $expires_at = null;

    /** Channel the code was dispatched over (decided by the purpose). */
    public string $channel = '';

    /**
     * `false` when the OTP was issued by a test-mode (`sk_test_`) request —
     * never dispatched; the magic code `000000` verifies.
     */
    public bool $livemode = true;

    /** @var list<string> */
    public array $task_ids = [];

    protected static function schema(): array
    {
        return [
            'otp_id' => 'string',
            'expires_at' => 'datetime',
            'channel' => 'string',
            'livemode' => 'bool',
            'task_ids' => 'mixed',
        ];
    }
}
