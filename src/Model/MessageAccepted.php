<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * 202 envelope from `POST /api/v1/messages/`.
 */
final class MessageAccepted extends Model
{
    /** Tracking id for the message, or the broadcast id. */
    public string $id = '';

    /** `message` for a single recipient, `broadcast` for an audience fan-out. */
    public string $object = '';

    public string $channel = '';

    /**
     * `queued` on an immediate send, `scheduled` when the request carried
     * `send_at`, `canceled` from a cancel call. Lifecycle:
     * `scheduled|queued|sent|failed|canceled`.
     */
    public string $status = '';

    /**
     * `false` when the request ran in test mode (an `sk_test_` key): nothing
     * reaches a provider and nothing is billed.
     */
    public bool $livemode = true;

    /** Broadcast only: number of recipients targeted. */
    public ?int $target_count = null;

    /** Broadcast only: number of recipients skipped — the sum of `skipped`. */
    public ?int $skipped_count = null;

    /**
     * Broadcast only: per-reason skip counters. `null` on single-recipient
     * sends, on scheduled broadcasts whose audience resolves at dispatch, and
     * from servers predating the breakdown.
     */
    public ?SkippedBreakdown $skipped = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'channel' => 'string',
            'status' => 'string',
            'livemode' => 'bool',
            'target_count' => 'int',
            'skipped_count' => 'int',
            'skipped' => SkippedBreakdown::class,
        ];
    }
}
