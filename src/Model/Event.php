<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * An event envelope — the exact JSON returned by the Events API and POSTed to
 * subscribed webhook endpoints.
 */
final class Event extends Model
{
    /** Opaque event id, `evt_` prefixed. */
    public string $id = '';

    public string $object = 'event';

    /** `message.delivered` / `message.failed` / `broadcast.completed`. */
    public string $type = '';

    public string $api_version = '';
    public ?DateTimeImmutable $created = null;

    /** `false` when the event was produced by a test-mode send. */
    public bool $livemode = true;

    public ?EventData $data = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'type' => 'string',
            'api_version' => 'string',
            'created' => 'datetime',
            'livemode' => 'bool',
            'data' => EventData::class,
        ];
    }
}
