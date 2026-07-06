<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\UserProfile;
use Silon\Util;

/**
 * `$client->profile` — the authenticated user's own profile.
 */
final class Profile extends Resource
{
    private const PATH = '/api/v1/profile/';

    public function retrieve(): UserProfile
    {
        return new UserProfile($this->client->get(self::PATH));
    }

    /**
     * Partial update (PATCH).
     *
     * @param array<string,mixed> $params email, first_name, last_name,
     *   phone_number, civil_id, default_language
     */
    public function update(array $params): UserProfile
    {
        return new UserProfile($this->client->patch(self::PATH, Util::dropNull($params)));
    }

    /**
     * Full replace (PUT).
     *
     * @param array<string,mixed> $params
     */
    public function replace(array $params): UserProfile
    {
        return new UserProfile($this->client->put(self::PATH, Util::dropNull($params)));
    }
}
