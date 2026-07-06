<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * The `data` payload carried inside an event envelope.
 *
 * The shape varies by the envelope's `type`, so every field is optional;
 * branch on the parent envelope's `type`.
 */
final class EventData extends Model
{
    public ?string $id = null;
    public ?string $object = null;

    /** `false` when the underlying send ran in test mode. */
    public ?bool $livemode = null;

    public ?string $channel = null;
    public ?string $recipient = null;
    public ?string $client_id = null;
    public ?string $status = null;
    public ?string $error = null;
    public ?string $broadcast_id = null;
    public ?string $provider = null;
    public ?string $external_id = null;
    public ?DateTimeImmutable $sent_at = null;
    public ?DateTimeImmutable $created_at = null;

    // broadcast.completed only
    public ?int $target_count = null;
    public ?int $sent = null;
    public ?int $failed = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'livemode' => 'bool',
            'channel' => 'string',
            'recipient' => 'string',
            'client_id' => 'string',
            'status' => 'string',
            'error' => 'string',
            'broadcast_id' => 'string',
            'provider' => 'string',
            'external_id' => 'string',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'target_count' => 'int',
            'sent' => 'int',
            'failed' => 'int',
        ];
    }
}
