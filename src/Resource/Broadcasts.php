<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\Broadcast;
use Silon\Model\BroadcastAccepted;
use Silon\Model\BroadcastDelivery;
use Silon\Util;

/**
 * `$client->broadcasts` — fan one piece of content out to an audience.
 */
final class Broadcasts extends Resource
{
    private const PATH = '/api/v1/broadcasts/';

    /**
     * Send a broadcast — one content, an audience (`POST /api/v1/broadcasts/`).
     *
     * `audience` selects the recipients: `['type' => 'client_group', 'slug' =>
     * ...]`, `['type' => 'client_ids', 'client_ids' => [...]]`, or an inline
     * ad-hoc list `['type' => 'recipients', 'recipients' => [...]]` (max 1,000
     * rows). Suppressed recipients, duplicates, and members not reachable on
     * the channel are SKIPPED, never errors — itemised in the envelope's
     * `skipped` breakdown, with `skipped_count` as the sum.
     *
     * `send_at` schedules the broadcast (`status: "scheduled"`; `target_count`
     * / `skipped_count` may be `null` when the audience resolves at dispatch).
     * An `Idempotency-Key` header is always sent (auto-generated UUIDv4 when
     * `idempotency_key` is not given).
     *
     * @param array<string,mixed> $params channel (required), audience (required),
     *   plus optional content, template, provider, sender, application,
     *   widget_key, priority, ttl, whatsapp, whatsapp_template, send_at,
     *   idempotency_key, extra_body.
     */
    public function create(array $params): BroadcastAccepted
    {
        $idempotencyKey = $params['idempotency_key'] ?? null;
        $extraBody = $params['extra_body'] ?? null;
        unset($params['idempotency_key'], $params['extra_body']);

        if (array_key_exists('send_at', $params)) {
            $params['send_at'] = Util::isoDatetime($params['send_at']);
        }
        $body = Util::dropNull($params);
        if ($extraBody) {
            $body = array_merge($body, $extraBody);
        }

        $data = $this->client->post(self::PATH, [
            'json' => $body,
            'headers' => $this->idempotencyHeaders($idempotencyKey),
        ]);

        return new BroadcastAccepted($data);
    }

    /** Aggregate delivery counts for a broadcast. */
    public function retrieve(string $broadcastId): Broadcast
    {
        $data = $this->client->get(self::PATH . rawurlencode($broadcastId) . '/');

        return new Broadcast($data);
    }

    /**
     * Cancel a scheduled broadcast
     * (`POST /api/v1/broadcasts/{broadcast_id}/cancel/`).
     *
     * Allowed only while the broadcast is still `scheduled`: answers 200 with
     * `status: "canceled"`. Cancel is idempotent by nature — no
     * `Idempotency-Key` is sent. A dispatched broadcast raises
     * {@see \Silon\Exception\ConflictException} (409 `not-cancellable`); an
     * unknown id raises {@see \Silon\Exception\NotFoundException}.
     */
    public function cancel(string $broadcastId): BroadcastAccepted
    {
        $data = $this->client->post(self::PATH . rawurlencode($broadcastId) . '/cancel/');

        return new BroadcastAccepted($data);
    }

    /**
     * Per-recipient delivery rows for a broadcast (cursor-paginated).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<BroadcastDelivery>
     */
    public function deliveries(string $broadcastId, array $params = []): CursorPage
    {
        $path = self::PATH . rawurlencode($broadcastId) . '/deliveries/';
        $query = Util::dropNull($params);
        $data = $this->client->get($path, $query);

        return new CursorPage($this->client, $path, $query, BroadcastDelivery::class, $data);
    }
}
