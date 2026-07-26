<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * The `data` payload carried inside an event envelope.
 *
 * The shape varies by the envelope's `type`:
 *
 * - `message.delivered` / `message.failed` — a settled-message snapshot.
 * - `broadcast.completed` — aggregate counts.
 * - `message.received` — the inbound message, with the whole thread under
 *   `conversation` so no follow-up read is needed.
 * - `conversation.created` / `.status_changed` / `.assigned` — the
 *   conversation itself, as `$client->conversations->retrieve()` returns it.
 *
 * A flattened union of all of those, so every field is optional; branch on the
 * parent envelope's `type`. `id` is a string on every type.
 */
final class EventData extends Model
{
    public ?string $id = null;
    public ?string $object = null;

    /** `false` when the underlying send ran in test mode. */
    public ?bool $livemode = null;

    public ?string $channel = null;
    public ?string $recipient = null;
    public ?string $client_id = null;
    public ?string $status = null;
    public ?string $error = null;
    public ?string $broadcast_id = null;
    public ?string $provider = null;
    public ?string $external_id = null;
    public ?DateTimeImmutable $sent_at = null;
    public ?DateTimeImmutable $created_at = null;

    // broadcast.completed only
    public ?int $target_count = null;
    public ?int $sent = null;
    public ?int $failed = null;

    // message.received only. `direction` is always `inbound` and `author`
    // always `customer` — the event fires for customer messages exclusively.
    public ?string $body = null;
    public ?string $conversation_id = null;
    public ?string $direction = null;
    public ?string $author = null;

    /** The thread the message landed in. */
    public ?Conversation $conversation = null;

    // conversation.* only. `channel` / `client_id` / `external_id` / `status`
    // above carry the conversation's meaning on these types.
    public ?string $priority = null;
    public ?string $subject = null;

    /** @var list<string>|null Label slugs on the thread. */
    public $labels = null;

    public ?int $unread = null;
    public ?bool $archived = null;
    public ?int $assignee_id = null;
    public ?int $team_id = null;
    public ?DateTimeImmutable $snoozed_until = null;
    public ?DateTimeImmutable $resolved_at = null;
    public ?DateTimeImmutable $created = null;
    public ?DateTimeImmutable $updated = null;

    /** `conversation.status_changed` — the status it moved from. */
    public ?string $previous_status = null;

    /** `conversation.assigned` — `claim` / `transfer` / `escalate` / … */
    public ?string $reason = null;

    /** `conversation.assigned` — the operator it was assigned to before. */
    public ?int $previous_assignee_id = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'livemode' => 'bool',
            'channel' => 'string',
            'recipient' => 'string',
            'client_id' => 'string',
            'status' => 'string',
            'error' => 'string',
            'broadcast_id' => 'string',
            'provider' => 'string',
            'external_id' => 'string',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'target_count' => 'int',
            'sent' => 'int',
            'failed' => 'int',
            'body' => 'string',
            'conversation_id' => 'string',
            'direction' => 'string',
            'author' => 'string',
            'conversation' => Conversation::class,
            'priority' => 'string',
            'subject' => 'string',
            'labels' => 'mixed',
            'unread' => 'int',
            'archived' => 'bool',
            'assignee_id' => 'int',
            'team_id' => 'int',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'created' => 'datetime',
            'updated' => 'datetime',
            'previous_status' => 'string',
            'reason' => 'string',
            'previous_assignee_id' => 'int',
        ];
    }
}
