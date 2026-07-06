<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * 202 envelope from `POST /api/v1/broadcasts/`.
 */
final class BroadcastAccepted extends Model
{
    public string $id = '';

    /** Always `broadcast`. */
    public string $object = '';

    public string $channel = '';

    /**
     * `queued` on an immediate broadcast, `scheduled` when the request carried
     * `send_at`, `canceled` from a cancel call. Lifecycle:
     * `scheduled|in_progress|completed|failed|canceled`.
     */
    public string $status = '';

    /** `false` when the broadcast ran in test mode (an `sk_test_` key). */
    public bool $livemode = true;

    /** Recipients targeted. May be `null` on a scheduled envelope. */
    public ?int $target_count = null;

    /** Recipients skipped — the sum of `skipped`. May be `null` when scheduled. */
    public ?int $skipped_count = null;

    /**
     * Per-reason skip counters. `null` exactly when `target_count` is `null`
     * (a scheduled audience resolving at dispatch) and from older servers.
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
