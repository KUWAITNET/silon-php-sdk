<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * Body of `GET /api/v1/messages/{event_id}/`.
 *
 * Modern shape: read `id` / `object` / `channel` / `timeline`. The `event_id`
 * / `is_sent` / `messages` keys are DEPRECATED aliases of the older
 * per-recipient shape — kept for back-compat, but prefer the modern keys
 * (`id` is the same value as `event_id`; the ordered `timeline` supersedes the
 * `messages` array).
 */
final class MessageStatus extends Model
{
    /**
     * Tracking id — the same value as the path id. Stable across the whole
     * lifecycle (minted at schedule time, threaded through dispatch).
     */
    public ?string $id = null;

    /** Always `message`. */
    public string $object = 'message';

    /** The send's channel, or `null` when the delivery rows don't agree on one. */
    public ?string $channel = null;

    /** `false` when the send ran in test mode — its transitions are simulated. */
    public bool $livemode = true;

    /**
     * Aggregate lifecycle status (`scheduled|queued|sent|failed|canceled`).
     * `delivered` is per-recipient granularity — it appears in `timeline`,
     * never here.
     */
    public ?string $status = null;

    /** Scheduled sends only: when the send will dispatch. */
    public ?DateTimeImmutable $send_at = null;

    /**
     * Ordered (ascending by `at`) list of attested status transitions. A
     * scheduled send reads `[{status: "scheduled", at}]` until dispatch.
     *
     * @var list<MessageTimelineEntry>
     */
    public array $timeline = [];

    /**
     * @deprecated Deprecated alias of {@see $id}; prefer `id`.
     */
    public ?string $event_id = null;

    /**
     * @deprecated Derive delivery from `status` / `timeline` instead.
     */
    public ?bool $is_sent = null;

    /**
     * DEPRECATED per-recipient delivery rows — empty while the send is still
     * `scheduled`. Prefer the modern `timeline`.
     *
     * @deprecated
     * @var list<MessageStatusItem>
     */
    public array $messages = [];

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'channel' => 'string',
            'livemode' => 'bool',
            'status' => 'string',
            'send_at' => 'datetime',
            'timeline' => MessageTimelineEntry::class . '[]',
            'event_id' => 'string',
            'is_sent' => 'bool',
            'messages' => MessageStatusItem::class . '[]',
        ];
    }
}
