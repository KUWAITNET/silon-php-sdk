<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One row from `GET /api/v1/bulk/`.
 */
class BulkBatch extends Model
{
    public int $id = 0;
    public string $filename = '';

    /** Recipients in this batch that were sent/read. */
    public int $success = 0;

    public int $total = 0;

    /** @var list<mixed> */
    public array $channels = [];

    public ?DateTimeImmutable $created_at = null;
    public ?DateTimeImmutable $sent_at = null;
    public string $timezone = '';

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'filename' => 'string',
            'success' => 'int',
            'total' => 'int',
            'channels' => 'mixed',
            'created_at' => 'datetime',
            'sent_at' => 'datetime',
            'timezone' => 'string',
        ];
    }
}
