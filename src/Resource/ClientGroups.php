<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\ClientGroup;
use Silon\Util;

/**
 * `$client->clientGroups` — CRM client groups
 * (canonical plural route `/api/v1/crm/groups/`).
 */
final class ClientGroups extends Resource
{
    private const PATH = '/api/v1/crm/groups/';

    /**
     * List client groups, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<ClientGroup>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, ClientGroup::class, $data);
    }

    /**
     * @param array<string,mixed> $params name, slug (required), client_ids, is_active
     */
    public function create(array $params): ClientGroup
    {
        $data = $this->client->post(self::PATH, ['json' => Util::dropNull($params)]);

        return new ClientGroup($data);
    }

    public function retrieve(int $groupId): ClientGroup
    {
        $data = $this->client->get(self::PATH . $groupId . '/');

        return new ClientGroup($data);
    }

    /**
     * Partial update (PATCH). `client_ids` replaces the membership.
     *
     * @param array<string,mixed> $params
     */
    public function update(int $groupId, array $params): ClientGroup
    {
        $data = $this->client->patch(self::PATH . $groupId . '/', Util::dropNull($params));

        return new ClientGroup($data);
    }

    /**
     * Full replace (PUT).
     *
     * @param array<string,mixed> $params
     */
    public function replace(int $groupId, array $params): ClientGroup
    {
        $data = $this->client->put(self::PATH . $groupId . '/', Util::dropNull($params));

        return new ClientGroup($data);
    }

    public function delete(int $groupId): void
    {
        $this->client->delete(self::PATH . $groupId . '/');
    }
}
