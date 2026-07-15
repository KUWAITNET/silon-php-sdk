<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\SignupResult;
use Silon\Util;

/**
 * `$client->auth` — sign up new users.
 */
final class Auth extends Resource
{
    /**
     * Sign up a new user (throttled server-side).
     *
     * @param array<string,mixed> $params email, first_name, last_name,
     *   phone_number, password (all required), civil_id, default_language, client_id
     */
    public function signup(array $params): SignupResult
    {
        $data = $this->client->post('/api/v1/signup/', ['json' => Util::dropNull($params)]);

        return new SignupResult($data);
    }
}
