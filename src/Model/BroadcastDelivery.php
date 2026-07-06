<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * One per-recipient delivery row for a broadcast.
 */
final class BroadcastDelivery extends Model
{
    public string $id = '';
    public string $client_id = '';
    public string $status = '';
    public ?DateTimeImmutable $sent_at = null;
    public ?string $error = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'client_id' => 'string',
            'status' => 'string',
            'sent_at' => 'datetime',
            'error' => 'string',
        ];
    }
}
