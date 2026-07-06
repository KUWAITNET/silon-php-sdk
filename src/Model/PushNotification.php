<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * One native push notification row (legacy list endpoints).
 */
final class PushNotification extends Model
{
    public string $message = '';
    public string $subject = '';
    public string $date = '';

    protected static function schema(): array
    {
        return ['message' => 'string', 'subject' => 'string', 'date' => 'string'];
    }
}
