<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\Event;
use Silon\Util;

/**
 * `$client->events` — read the event stream your webhooks are fed from.
 */
final class Events extends Resource
{
    private const PATH = '/api/v1/events';

    /**
     * List events, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params type, cursor, limit
     * @return CursorPage<Event>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, Event::class, $data);
    }

    public function retrieve(string $eventId): Event
    {
        $data = $this->client->get(self::PATH . '/' . rawurlencode($eventId));

        return new Event($data);
    }
}
