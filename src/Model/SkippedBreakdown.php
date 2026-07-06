<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Per-reason skip counters on fan-out envelopes.
 *
 * The server always sends every key (0 when nothing was skipped for that
 * reason); `skipped_count` on the parent envelope is their sum.
 */
final class SkippedBreakdown extends Model
{
    /** Recipients on the suppression list (do-not-contact). */
    public int $suppressed = 0;

    /** Group members not reachable on the fan-out's channel. */
    public int $wrong_channel = 0;

    /** Duplicate rows deduped out of an inline recipients list. */
    public int $duplicate = 0;

    protected static function schema(): array
    {
        return ['suppressed' => 'int', 'wrong_channel' => 'int', 'duplicate' => 'int'];
    }
}
