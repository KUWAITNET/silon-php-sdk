<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 401 Unauthorized — a missing, invalid, or revoked API key.
 */
class AuthenticationException extends ApiStatusException
{
}
