<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Exception\SilonException;
use Silon\Model\BatchAccepted;
use Silon\Model\MessageAccepted;
use Silon\Model\MessageStatus;
use Silon\Util;

/**
 * `$client->messages` — the unified send across every channel, plus batch
 * sends and delivery-status lookups.
 */
final class Messages extends Resource
{
    private const PATH = '/api/v1/messages/';
    private const BATCH_PATH = '/api/v1/messages/batch/';

    /**
     * Send a message on any channel (`POST /api/v1/messages/`).
     *
     * Exactly one of `to` (single recipient, e.g. `['client_id' => ...]`,
     * `['phone_number' => ...]`, `['email' => ...]`, `['device_token' => ...]`)
     * or `audience` (broadcast selector, e.g.
     * `['type' => 'client_group', 'slug' => ...]`) is required — enforced
     * client-side.
     *
     * `send_at` (a `DateTimeInterface` with a UTC offset, or an ISO-8601 string;
     * strictly in the future, at most 90 days ahead, else `422
     * send-at-invalid`) schedules the send: the envelope answers
     * `status: "scheduled"` and its `id` tracks it through dispatch; cancel
     * while still scheduled via {@see cancel()}.
     *
     * Sending to a suppressed address rejects `422 recipient-suppressed`.
     * Transactional/legal sends may pass `override_suppression: true` to bypass
     * it — single-recipient sends only, and the key must carry the
     * `suppressions:override` scope (else `403 missing-scope`).
     *
     * An `Idempotency-Key` header is always sent (auto-generated UUIDv4 when
     * `idempotency_key` is not given). Channel-specific fields not covered by
     * the documented keys can be passed through `extra_body`, merged last.
     *
     * @param array<string,mixed> $params channel (required), exactly one of
     *   to / audience, plus optional content, template, provider, sender,
     *   application, widget_key, priority, ttl, whatsapp, whatsapp_template,
     *   send_at, override_suppression, category, idempotency_key, extra_body.
     *   `category` is `marketing` or `transactional` (server default
     *   `transactional`): declare `marketing` on promotional sends or
     *   List-Unsubscribe opt-outs are bypassed.
     * @throws SilonException when neither or both of `to` / `audience` are given.
     */
    public function send(array $params): MessageAccepted
    {
        $idempotencyKey = $params['idempotency_key'] ?? null;
        $extraBody = $params['extra_body'] ?? null;
        unset($params['idempotency_key'], $params['extra_body']);

        if ((($params['to'] ?? null) === null) === (($params['audience'] ?? null) === null)) {
            throw new SilonException(
                "Provide exactly one of 'to' (single recipient) or 'audience' (broadcast)."
            );
        }

        $body = $this->buildBody($params, $extraBody);
        $data = $this->client->post(self::PATH, [
            'json' => $body,
            'headers' => $this->idempotencyHeaders($idempotencyKey),
        ]);

        return new MessageAccepted($data);
    }

    /**
     * Send a batch of independent, personalised messages in one call
     * (`POST /api/v1/messages/batch/`).
     *
     * Exactly one of `messages` (up to 500 inline rows, each the same shape as
     * a {@see send()} body minus `audience`) or `file` (the saved name of a CSV
     * uploaded via `$client->bulk->files->upload()`) is required — enforced
     * client-side. Request-level fields act as row defaults on both forms; a
     * row's own field (or CSV column) always wins.
     *
     * INLINE form: all-or-nothing validation — any invalid row fails the whole
     * batch (`422`, `attr` like `messages[3].to.phone_number`) and nothing is
     * queued. The 202 carries per-row envelopes in request order, each
     * individually pollable via {@see retrieve()}.
     *
     * FILE form: rows expand asynchronously; the 202 is the aggregate batch
     * object (no per-row `messages`), and the returned `id` is the bulk batch
     * id (`$client->bulk->retrieve($id)`). Accepts `send_at` to schedule; with
     * inline `messages`, `send_at` is rejected `422 batch-invalid`.
     *
     * @param array<string,mixed> $params exactly one of messages / file, plus
     *   optional row-default fields, send_at (file form only), idempotency_key,
     *   extra_body.
     * @throws SilonException when neither or both of `messages` / `file` are given.
     */
    public function sendBatch(array $params): BatchAccepted
    {
        $idempotencyKey = $params['idempotency_key'] ?? null;
        $extraBody = $params['extra_body'] ?? null;
        unset($params['idempotency_key'], $params['extra_body']);

        if ((($params['messages'] ?? null) === null) === (($params['file'] ?? null) === null)) {
            throw new SilonException(
                "Provide exactly one of 'messages' (inline rows) or 'file' (a saved CSV name)."
            );
        }

        $body = $this->buildBody($params, $extraBody);
        $data = $this->client->post(self::BATCH_PATH, [
            'json' => $body,
            'headers' => $this->idempotencyHeaders($idempotencyKey),
        ]);

        return new BatchAccepted($data);
    }

    /**
     * Look up a queued/sent message by its tracking id
     * (`GET /api/v1/messages/{event_id}/`). A scheduled send answers
     * `status: "scheduled"` until dispatch; the id is stable across the
     * lifecycle.
     */
    public function retrieve(string $eventId): MessageStatus
    {
        $data = $this->client->get(self::PATH . rawurlencode($eventId) . '/');

        return new MessageStatus($data);
    }

    /**
     * Cancel a scheduled message
     * (`POST /api/v1/messages/{event_id}/cancel/`).
     *
     * Allowed only while the send is still `scheduled`: answers 200 with the
     * envelope showing `status: "canceled"`, and the send never dispatches.
     * Cancel is idempotent by nature — repeating it answers 200 with the
     * canceled envelope again — so no `Idempotency-Key` is sent. A dispatched
     * send (or an immediate send's id) raises
     * {@see \Silon\Exception\ConflictException} (409 `not-cancellable`); an
     * unknown id raises {@see \Silon\Exception\NotFoundException}.
     */
    public function cancel(string $eventId): MessageAccepted
    {
        $data = $this->client->post(self::PATH . rawurlencode($eventId) . '/cancel/');

        return new MessageAccepted($data);
    }

    /**
     * Serialize `send_at`, drop null fields, and merge `extra_body` last.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed>|null $extraBody
     * @return array<string,mixed>
     */
    private function buildBody(array $params, ?array $extraBody): array
    {
        if (array_key_exists('send_at', $params)) {
            $params['send_at'] = Util::isoDatetime($params['send_at']);
        }
        $body = Util::dropNull($params);
        if ($extraBody) {
            $body = array_merge($body, $extraBody);
        }

        return $body;
    }
}
