<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\Conversation;
use Silon\Model\ConversationMessage;
use Silon\Util;

/**
 * `$client->conversations` — read and work the shared inbox
 * (`/api/v1/conversations`).
 *
 * Reading needs the `conversations:read` scope; replying or changing a thread
 * needs `conversations:write`. A key is a workspace credential, so it sees
 * every conversation in the workspace.
 */
final class Conversations extends Resource
{
    private const PATH = '/api/v1/conversations';

    /**
     * List conversations, most recently active first (cursor-paginated).
     * Poll for changes with `updated_since`; `assignee: 'none'` is the
     * unassigned pool.
     *
     * @param array<string,mixed> $params status, channel, assignee, team, label,
     *                                    archived, updated_since, q, cursor, limit
     * @return CursorPage<Conversation>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, Conversation::class, $data);
    }

    /** Fetch one conversation by id. */
    public function retrieve(string $conversationId): Conversation
    {
        $data = $this->client->get(self::PATH . '/' . rawurlencode($conversationId));

        return new Conversation($data);
    }

    /**
     * Partial update. `labels` REPLACES the whole set; an unknown slug is a 422.
     *
     * Pass `unassign => true` to clear the assignee — `dropNull` strips nulls,
     * so `assignee_id => null` cannot be sent.
     *
     * @param array<string,mixed> $params status, priority, assignee_id, unassign,
     *                                    labels, archived, snoozed_until
     */
    public function update(string $conversationId, array $params): Conversation
    {
        $data = $this->client->patch(
            self::PATH . '/' . rawurlencode($conversationId),
            Util::dropNull($params),
        );

        return new Conversation($data);
    }

    /**
     * The thread's message history, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<ConversationMessage>
     */
    public function listMessages(string $conversationId, array $params = []): CursorPage
    {
        $path = self::PATH . '/' . rawurlencode($conversationId) . '/messages';
        $query = Util::dropNull($params);
        $data = $this->client->get($path, $query);

        return new CursorPage($this->client, $path, $query, ConversationMessage::class, $data);
    }

    /**
     * Reply to the customer on the conversation's own channel.
     *
     * Pass `internal => true` to record a team-only note instead — nothing is
     * delivered. A closed messaging window (WhatsApp's 24 hours, for example)
     * throws `Silon\Exception\ConflictException`.
     *
     * @param array<string,mixed> $params body (required), internal
     */
    public function reply(string $conversationId, array $params): ConversationMessage
    {
        $path = self::PATH . '/' . rawurlencode($conversationId) . '/messages';
        $data = $this->client->post($path, ['json' => Util::dropNull($params)]);

        return new ConversationMessage($data);
    }
}
