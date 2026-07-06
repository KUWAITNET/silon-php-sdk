<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * A 5xx server error (500, 502, 503, 504, ...). Transient by nature — the SDK
 * retries these automatically for idempotent requests.
 */
class InternalServerException extends ApiStatusException
{
}
