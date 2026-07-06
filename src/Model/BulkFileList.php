<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `GET /api/v1/bulk/files/` — the `{count, results}` list shape.
 */
final class BulkFileList extends Model
{
    public int $count = 0;

    /** @var list<BulkFile> */
    public array $results = [];

    protected static function schema(): array
    {
        return ['count' => 'int', 'results' => BulkFile::class . '[]'];
    }
}
