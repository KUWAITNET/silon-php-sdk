<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/** One thread in the shared inbox. */
class Conversation extends Model
{
    /** Conversation id (a UUID). */
    public string $id = '';

    public string $object = 'conversation';

    /** Channel the thread runs on — `whatsapp`, `silon_chat`, `email`, … */
    public string $channel = '';

    /** Lifecycle state: `open`, `pending`, `resolved` or `snoozed`. */
    public string $status = '';

    /** `urgent` / `high` / `medium` / `low`, or null when unset. */
    public ?string $priority = null;

    /** Display name of the customer side of the thread. */
    public string $subject = '';

    /** The remote party's identifier on the channel. */
    public string $external_id = '';

    /** Linked contact id, when matched to a CRM contact. */
    public ?string $client_id = null;

    public ?int $assignee_id = null;
    public ?int $team_id = null;

    /** @var list<string> Label slugs on the conversation. */
    public array $labels = [];

    /** Inbound messages not yet read by an operator. */
    public int $unread = 0;

    public bool $archived = false;
    public ?DateTimeImmutable $snoozed_until = null;
    public ?DateTimeImmutable $resolved_at = null;
    public ?DateTimeImmutable $created = null;
    public ?DateTimeImmutable $updated = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'channel' => 'string',
            'status' => 'string',
            'priority' => 'string',
            'subject' => 'string',
            'external_id' => 'string',
            'client_id' => 'string',
            'assignee_id' => 'int',
            'team_id' => 'int',
            'labels' => 'mixed',
            'unread' => 'int',
            'archived' => 'bool',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'created' => 'datetime',
            'updated' => 'datetime',
        ];
    }
}
