<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * One recipient row inside a message-status batch (DEPRECATED shape).
 *
 * @deprecated Part of the legacy `messages` array on {@see MessageStatus};
 *   prefer the modern `timeline`.
 */
final class MessageStatusItem extends Model
{
    public string $client_id = '';
    public string $phone_number = '';
    public string $email = '';
    public bool $is_read = false;
    public int $read_count = 0;

    protected static function schema(): array
    {
        return [
            'client_id' => 'string',
            'phone_number' => 'string',
            'email' => 'string',
            'is_read' => 'bool',
            'read_count' => 'int',
        ];
    }
}
