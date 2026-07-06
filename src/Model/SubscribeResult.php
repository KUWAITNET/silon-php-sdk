<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `POST /api/v1/subscribe/android|ios/`.
 */
final class SubscribeResult extends Model
{
    public int $success = 0;

    protected static function schema(): array
    {
        return ['success' => 'int'];
    }
}
