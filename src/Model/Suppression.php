<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One do-not-contact row (`/api/v1/suppressions/`).
 */
final class Suppression extends Model
{
    /** Opaque suppression id, `sup_` prefixed. */
    public string $id = '';

    /** Always `suppression`. */
    public string $object = 'suppression';

    /** The suppressed address, stored normalized (compact E.164 / lowercase). */
    public string $address = '';

    /** Channel scoped to (e.g. `sms`); `null` = suppressed on ALL channels. */
    public ?string $channel = null;

    /** `manual` | `unsubscribe` | `hard_bounce` | `stop`. */
    public string $reason = 'manual';

    /**
     * What this suppression blocks: `all` (every message) or `marketing`
     * (only sends declared `category: "marketing"`).
     */
    public string $scope = 'all';

    /** `false` when created by an `sk_test_` key — gates test sends only. */
    public bool $livemode = true;

    public ?DateTimeImmutable $created = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'address' => 'string',
            'channel' => 'string',
            'scope' => 'string',
            'reason' => 'string',
            'livemode' => 'bool',
            'created' => 'datetime',
        ];
    }
}
