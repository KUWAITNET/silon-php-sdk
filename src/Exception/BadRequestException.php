<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 400 Bad Request — a malformed request (also raised for an OTP verified with
 * a wrong code, whose {@see ApiStatusException::$body} carries
 * `remaining_attempts`).
 */
class BadRequestException extends ApiStatusException
{
}
