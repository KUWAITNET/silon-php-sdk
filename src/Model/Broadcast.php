<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * Aggregate counts from `GET /api/v1/broadcasts/{broadcast_id}/`.
 */
final class Broadcast extends Model
{
    public string $id = '';
    public string $channel = '';

    /** `false` when the broadcast ran in test mode — statuses are simulated. */
    public bool $livemode = true;

    /** May be `null` while `scheduled` (audience resolves at dispatch). */
    public ?int $target_count = null;

    public int $queued = 0;
    public int $sent = 0;
    public int $failed = 0;
    public ?DateTimeImmutable $started_at = null;
    public ?DateTimeImmutable $completed_at = null;

    /**
     * `scheduled` before a `send_at` broadcast dispatches, `canceled` after a
     * successful cancel; otherwise `in_progress` until nothing is queued, then
     * `completed` (or `failed`).
     */
    public string $status = '';

    /** Scheduled broadcasts only: when the broadcast will dispatch. */
    public ?DateTimeImmutable $send_at = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'channel' => 'string',
            'livemode' => 'bool',
            'target_count' => 'int',
            'queued' => 'int',
            'sent' => 'int',
            'failed' => 'int',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => 'string',
            'send_at' => 'datetime',
        ];
    }
}
