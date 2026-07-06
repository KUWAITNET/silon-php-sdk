<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * One per-row message envelope inside a batch-create response.
 */
final class BatchMessage extends Model
{
    /** Tracking id for this row — poll it at `GET /api/v1/messages/{id}/`. */
    public string $id = '';

    /** Always `message`. */
    public string $object = '';

    public string $channel = '';

    public string $status = '';

    protected static function schema(): array
    {
        return ['id' => 'string', 'object' => 'string', 'channel' => 'string', 'status' => 'string'];
    }
}
