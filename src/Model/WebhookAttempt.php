<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One delivery-attempt ledger row (`/attempts`).
 */
final class WebhookAttempt extends Model
{
    /** Opaque attempt id, `wha_` prefixed. */
    public string $id = '';

    /** Always `webhook_attempt`. */
    public string $object = 'webhook_attempt';

    /** The delivered event's id. */
    public string $event_id = '';

    /** The event type (e.g. `message.delivered`). */
    public string $event_type = '';

    /** How many delivery attempts have been made so far. */
    public int $attempts = 0;

    /** The endpoint's last HTTP status, or `null` if no response came back. */
    public ?int $response_status = null;

    /** `true` once a delivery attempt succeeded (endpoint answered 2xx). */
    public bool $ok = false;

    /** Last failure reason, or `null`. */
    public ?string $error = null;

    public ?DateTimeImmutable $last_attempt_at = null;

    /** When the next retry is scheduled, or `null` when done retrying. */
    public ?DateTimeImmutable $next_attempt_at = null;

    /** When the delivery was first enqueued. */
    public ?DateTimeImmutable $created = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'event_id' => 'string',
            'event_type' => 'string',
            'attempts' => 'int',
            'response_status' => 'int',
            'ok' => 'bool',
            'error' => 'string',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'created' => 'datetime',
        ];
    }
}
