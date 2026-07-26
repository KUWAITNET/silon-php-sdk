<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/** One message inside a conversation. */
class ConversationMessage extends Model
{
    public int $id = 0;
    public string $object = 'message';
    public string $conversation_id = '';
    public string $body = '';

    /** `text`, `image`, `system`, … */
    public string $type = 'text';

    /** `inbound` from the customer, `outbound` from your team or the bot. */
    public string $direction = '';

    /** `customer`, `operator` or `bot`. */
    public string $author = '';

    /** An internal note — recorded on the thread, never delivered. */
    public bool $internal = false;

    public string $media_url = '';
    public string $delivery_status = '';
    public ?DateTimeImmutable $created = null;

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'object' => 'string',
            'conversation_id' => 'string',
            'body' => 'string',
            'type' => 'string',
            'direction' => 'string',
            'author' => 'string',
            'internal' => 'bool',
            'media_url' => 'string',
            'delivery_status' => 'string',
            'created' => 'datetime',
        ];
    }
}
