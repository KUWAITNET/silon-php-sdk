<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of the deprecated `POST /api/v1/login/`.
 */
final class LoginResult extends Model
{
    /** Bearer token — prefer a scoped `sk_live_` API key instead. */
    public string $token = '';

    protected static function schema(): array
    {
        return ['token' => 'string'];
    }
}
