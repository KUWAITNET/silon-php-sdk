<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One saved CSV listed by `GET /api/v1/bulk/files/`.
 */
final class BulkFile extends Model
{
    /** Saved filename — pass as `bulk_file` to `bulk.send`. */
    public string $name = '';

    public int $size = 0;
    public ?DateTimeImmutable $modified_at = null;

    protected static function schema(): array
    {
        return ['name' => 'string', 'size' => 'int', 'modified_at' => 'datetime'];
    }
}
