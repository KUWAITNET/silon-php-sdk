<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * 202 envelope from `POST /api/v1/messages/batch/`.
 */
final class BatchAccepted extends Model
{
    /**
     * The batch id. Inline batches have no GET endpoint — poll the per-row
     * ids instead. For the file form this is the bulk batch id: per-row status
     * reads via `GET /api/v1/bulk/{id}/` and the bulk reports.
     */
    public string $id = '';

    /** Always `batch`. */
    public string $object = '';

    /** `false` when the batch ran in test mode (an `sk_test_` key). */
    public bool $livemode = true;

    /**
     * File form only: aggregate batch status — `queued`, or `scheduled` when
     * the request carried `send_at`.
     */
    public ?string $status = null;

    /** File form only: the CSV's data-row count, present when cheaply known. */
    public ?int $row_count = null;

    /**
     * Inline form only: per-row message envelopes, in request order —
     * suppressed rows are omitted. `null` on the file form (rows expand
     * asynchronously, so there are no per-row envelopes).
     *
     * @var list<BatchMessage>|null
     */
    public ?array $messages = null;

    /** Inline form only: rows skipped instead of queued — the sum of `skipped`. */
    public ?int $skipped_count = null;

    /**
     * Inline form only: per-reason skip counters. `null` on the file form (its
     * breakdown surfaces on the bulk read side) and from older servers.
     */
    public ?SkippedBreakdown $skipped = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'livemode' => 'bool',
            'status' => 'string',
            'row_count' => 'int',
            'messages' => BatchMessage::class . '[]',
            'skipped_count' => 'int',
            'skipped' => SkippedBreakdown::class,
        ];
    }
}
