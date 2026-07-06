<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Success body of `POST /api/v1/bulk/send/`.
 */
final class BulkSendResult extends Model
{
    public int $ok = 0;
    public string $message = '';
    public int $bulk_id = 0;
    public int $queued = 0;
    public int $failed = 0;
    public string $filename = '';

    protected static function schema(): array
    {
        return [
            'ok' => 'int',
            'message' => 'string',
            'bulk_id' => 'int',
            'queued' => 'int',
            'failed' => 'int',
            'filename' => 'string',
        ];
    }
}
