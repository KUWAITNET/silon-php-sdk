<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Per-recipient row embedded in a bulk batch detail.
 */
final class BulkRecipient extends Model
{
    public int $id = 0;
    public string $client_id = '';
    public string $phone_number = '';
    public string $email = '';
    public string $status = '';
    public string $error = '';

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'client_id' => 'string',
            'phone_number' => 'string',
            'email' => 'string',
            'status' => 'string',
            'error' => 'string',
        ];
    }
}
