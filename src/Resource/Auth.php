<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\LoginResult;
use Silon\Model\SignupResult;
use Silon\Util;

/**
 * `$client->auth` — sign up new users and (deprecated) exchange credentials
 * for a token.
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

    /**
     * Exchange username + password for a Bearer token (deprecated).
     *
     * @deprecated Prefer a scoped `sk_live_` API key created under
     *   Settings > API keys.
     * @param array<string,mixed> $params username (required), password (required)
     */
    public function login(array $params): LoginResult
    {
        trigger_error(
            'POST /api/v1/login/ is deprecated - prefer a scoped sk_live_ API key '
            . 'created under Settings > API keys.',
            E_USER_DEPRECATED,
        );
        $data = $this->client->post('/api/v1/login/', [
            'json' => ['username' => $params['username'], 'password' => $params['password']],
        ]);

        return new LoginResult($data);
    }
}
