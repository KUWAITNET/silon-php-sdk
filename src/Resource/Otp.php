<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\OtpSendResult;
use Silon\Model\OtpVerifyResult;

/**
 * `$client->otp` — dispatch and verify one-time passwords.
 */
final class Otp extends Resource
{
    private const SEND_PATH = '/api/v1/otp/send/';
    private const VERIFY_PATH = '/api/v1/otp/verify/';
    private const PURPOSES_PATH = '/api/v1/otp/purposes/';

    /**
     * List the tenant's active OTP purposes (`GET /api/v1/otp/purposes/`).
     *
     * Each entry carries `name` (what `send()` accepts as `purpose`),
     * `channel` (`whatsapp` / `sms` / `email`), `description`, `code_length`,
     * and `ttl_seconds`. Requires a server with the purposes endpoint
     * (Silon ≥ 1.1.14 on-prem); older servers raise
     * {@see \Silon\Exception\NotFoundException}.
     *
     * @return list<array<string,mixed>>
     */
    public function purposes(): array
    {
        $data = $this->client->get(self::PURPOSES_PATH);
        $results = $data['results'] ?? null;

        return is_array($results) ? array_values($results) : [];
    }

    /**
     * Dispatch a one-time password (`POST /api/v1/otp/send/`).
     *
     * `to` must contain exactly one of `client_id` / `phone_number` / `email`.
     * The delivery channel is decided by the configured `purpose`. An
     * `Idempotency-Key` header is always sent (auto-generated UUIDv4 when
     * `idempotency_key` is not given).
     *
     * @param array<string,mixed> $params purpose (required), to (required),
     *   idempotency_key (optional)
     */
    public function send(array $params): OtpSendResult
    {
        $idempotencyKey = $params['idempotency_key'] ?? null;
        $body = ['purpose' => $params['purpose'], 'to' => $params['to']];
        $data = $this->client->post(self::SEND_PATH, [
            'json' => $body,
            'headers' => $this->idempotencyHeaders($idempotencyKey),
        ]);

        return new OtpSendResult($data);
    }

    /**
     * Verify a code (`POST /api/v1/otp/verify/`).
     *
     * A wrong code raises {@see \Silon\Exception\BadRequestException} whose
     * `body` carries `remaining_attempts`; an expired one raises
     * {@see \Silon\Exception\GoneException}.
     *
     * @param array<string,mixed> $params otp_id (required), code (required)
     */
    public function verify(array $params): OtpVerifyResult
    {
        $data = $this->client->post(self::VERIFY_PATH, [
            'json' => ['otp_id' => $params['otp_id'], 'code' => $params['code']],
        ]);

        return new OtpVerifyResult($data);
    }
}
