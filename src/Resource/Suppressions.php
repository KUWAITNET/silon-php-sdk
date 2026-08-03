<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\Suppression;
use Silon\Util;

/**
 * `$client->suppressions` — the workspace's do-not-contact list.
 *
 * Suppressed addresses are enforced on every send path: a single-recipient
 * send to one raises {@see \Silon\Exception\UnprocessableEntityException}
 * (422 `recipient-suppressed`), while fan-outs skip them into
 * `skipped.suppressed`. Rows are livemode-scoped.
 */
final class Suppressions extends Resource
{
    private const PATH = '/api/v1/suppressions/';

    /**
     * List suppressions, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params address, channel, reason, cursor, limit
     * @return CursorPage<Suppression>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, Suppression::class, $data);
    }

    /**
     * Add an address to the suppression list.
     *
     * `address` is an E.164 phone number or an email (stored normalized).
     * `channel` scopes the row to one channel; omit it to suppress on ALL
     * channels. `reason` defaults to `manual`. Create is idempotent by nature:
     * re-creating an existing (address, channel) pair answers 200 with the
     * EXISTING object — so no `Idempotency-Key` is sent.
     *
     * @param array<string,mixed> $params address (required), channel, reason
     */
    /**
     * `$params`: address (required), plus optional channel, reason and
     * `scope` (`all` | `marketing`). Omit `scope` and the server derives it
     * from `reason` — `unsubscribe` becomes `marketing`, else `all`.
     */
    public function create(array $params): Suppression
    {
        $data = $this->client->post(self::PATH, ['json' => Util::dropNull($params)]);

        return new Suppression($data);
    }

    /**
     * Remove a suppression (`DELETE /api/v1/suppressions/{id}/` -> 204). An
     * unknown or mode-mismatched id raises
     * {@see \Silon\Exception\NotFoundException}.
     */
    public function delete(string $suppressionId): void
    {
        $this->client->delete(self::PATH . rawurlencode($suppressionId) . '/');
    }
}
