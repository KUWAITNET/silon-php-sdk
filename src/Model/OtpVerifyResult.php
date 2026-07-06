<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * 200 body of `POST /api/v1/otp/verify/`.
 *
 * A wrong code raises {@see \Silon\Exception\BadRequestException} whose
 * `body` carries `{"verified": false, "remaining_attempts": N}`.
 */
final class OtpVerifyResult extends Model
{
    public bool $verified = false;
    public string $purpose = '';
    public ?DateTimeImmutable $verified_at = null;

    /** `false` when the OTP was issued by a test-mode (`sk_test_`) request. */
    public bool $livemode = true;

    protected static function schema(): array
    {
        return [
            'verified' => 'bool',
            'purpose' => 'string',
            'verified_at' => 'datetime',
            'livemode' => 'bool',
        ];
    }
}
