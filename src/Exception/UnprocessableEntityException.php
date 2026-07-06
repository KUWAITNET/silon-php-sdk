<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 422 Unprocessable Entity — request validation failed (e.g. a suppressed
 * recipient, an invalid batch row, or an out-of-range `send_at`).
 */
class UnprocessableEntityException extends ApiStatusException
{
}
