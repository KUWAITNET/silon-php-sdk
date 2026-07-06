<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\ClientProfile;
use Silon\Util;

/**
 * `$client->clients` — CRM client profiles
 * (canonical plural route `/api/v1/crm/clients/`).
 */
final class Clients extends Resource
{
    private const PATH = '/api/v1/crm/clients/';

    /**
     * List client profiles, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<ClientProfile>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, ClientProfile::class, $data);
    }

    /**
     * Create a client profile (`client_id` required).
     *
     * @param array<string,mixed> $params
     */
    public function create(array $params): ClientProfile
    {
        $data = $this->client->post(self::PATH, ['json' => Util::dropNull($params)]);

        return new ClientProfile($data);
    }

    public function retrieve(string $clientId): ClientProfile
    {
        $data = $this->client->get(self::PATH . rawurlencode($clientId) . '/');

        return new ClientProfile($data);
    }

    /**
     * Partial update (PATCH) — only the given fields change.
     *
     * @param array<string,mixed> $params
     */
    public function update(string $clientId, array $params): ClientProfile
    {
        $data = $this->client->patch(self::PATH . rawurlencode($clientId) . '/', Util::dropNull($params));

        return new ClientProfile($data);
    }

    /**
     * Full replace (PUT). `client_id` itself is immutable.
     *
     * @param array<string,mixed> $params
     */
    public function replace(string $clientId, array $params): ClientProfile
    {
        $data = $this->client->put(self::PATH . rawurlencode($clientId) . '/', Util::dropNull($params));

        return new ClientProfile($data);
    }

    public function delete(string $clientId): void
    {
        $this->client->delete(self::PATH . rawurlencode($clientId) . '/');
    }
}
