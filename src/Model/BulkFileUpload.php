<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * 201 body of `POST /api/v1/bulk/files/`.
 */
final class BulkFileUpload extends Model
{
    /** UUID-based saved filename; pass as `file` to `messages.sendBatch`. */
    public string $name = '';

    public string $original_filename = '';
    public int $size = 0;
    public ?DateTimeImmutable $modified_at = null;

    protected static function schema(): array
    {
        return [
            'name' => 'string',
            'original_filename' => 'string',
            'size' => 'int',
            'modified_at' => 'datetime',
        ];
    }
}
