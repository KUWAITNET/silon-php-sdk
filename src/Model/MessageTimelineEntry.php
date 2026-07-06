<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One attested status transition in a message's `timeline`.
 *
 * Entries are ordered ascending by `at`. The timeline status vocabulary adds
 * `delivered` (`queued|sent|delivered|failed|canceled`) — `delivered` is
 * per-recipient granularity that appears ONLY here (never as the top-level
 * `status`), and only on single-recipient sends whose channel reports receipts.
 */
final class MessageTimelineEntry extends Model
{
    /** The transition's status (`queued|sent|delivered|failed|canceled`). */
    public string $status = '';

    /** When the transition was attested. */
    public ?DateTimeImmutable $at = null;

    /** The provider that reported the transition, when known. */
    public ?string $provider = null;

    protected static function schema(): array
    {
        return ['status' => 'string', 'at' => 'datetime', 'provider' => 'string'];
    }
}
