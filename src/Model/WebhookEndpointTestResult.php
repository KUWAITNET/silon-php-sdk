<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Result of `client.webhookEndpoints.test(id)`.
 *
 * A failing sink is NOT an HTTP error — the call returns 200 with
 * `delivered=false` and the reason in `error`.
 */
final class WebhookEndpointTestResult extends Model
{
    /** `true` when the endpoint answered a 2xx to the ping. */
    public bool $delivered = false;

    /** The endpoint's HTTP status, or `null` when no response came back. */
    public ?int $response_status = null;

    /** Round-trip time to the endpoint, in milliseconds. */
    public int $latency_ms = 0;

    /** Failure reason when `delivered` is `false`; `null` on success. */
    public ?string $error = null;

    protected static function schema(): array
    {
        return [
            'delivered' => 'bool',
            'response_status' => 'int',
            'latency_ms' => 'int',
            'error' => 'string',
        ];
    }
}
