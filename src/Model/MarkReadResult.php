<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `POST /api/v1/push/read/`.
 */
final class MarkReadResult extends Model
{
    public int $affected_rows = 0;

    protected static function schema(): array
    {
        return ['affected_rows' => 'int'];
    }
}
