<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * Body of `GET /api/v1/bulk/recipient/{id}/`.
 */
final class BulkRecipientDetail extends Model
{
    public int $id = 0;
    public string $file_name = '';
    public string $status = '';
    public string $channel = '';
    public string $provider = '';
    public string $application = '';
    public string $web_app = '';
    public string $sender = '';
    public string $template = '';
    public string $subject = '';
    public string $messages = '';
    public ?DateTimeImmutable $created_at = null;
    public ?DateTimeImmutable $scheduled_at = null;
    public ?DateTimeImmutable $sent_at = null;

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'file_name' => 'string',
            'status' => 'string',
            'channel' => 'string',
            'provider' => 'string',
            'application' => 'string',
            'web_app' => 'string',
            'sender' => 'string',
            'template' => 'string',
            'subject' => 'string',
            'messages' => 'string',
            'created_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
