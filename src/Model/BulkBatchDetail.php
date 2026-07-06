<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * Full batch detail from `GET /api/v1/bulk/{id}/`.
 */
final class BulkBatchDetail extends BulkBatch
{
    public string $provider = '';

    /** @var list<mixed> */
    public array $applications = [];

    /** @var list<mixed> */
    public array $web_applications = [];

    public string $sender = '';

    /** @var list<mixed> */
    public array $template = [];

    public string $subject = '';

    /** @var list<mixed> */
    public array $messages = [];

    public ?DateTimeImmutable $scheduled_at = null;

    /** @var list<BulkRecipient> */
    public array $recipients = [];

    protected static function schema(): array
    {
        return array_merge(parent::schema(), [
            'provider' => 'string',
            'applications' => 'mixed',
            'web_applications' => 'mixed',
            'sender' => 'string',
            'template' => 'mixed',
            'subject' => 'string',
            'messages' => 'mixed',
            'scheduled_at' => 'datetime',
            'recipients' => BulkRecipient::class . '[]',
        ]);
    }
}
