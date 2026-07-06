<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `GET /api/v1/reports/balance/{slug}/`.
 */
final class ProviderBalance extends Model
{
    /**
     * Upstream provider balance (provider-specific format; may be a number).
     * Empty string when the account has no balance lookup.
     */
    public string $balance = '';

    protected static function schema(): array
    {
        return ['balance' => 'string'];
    }
}
