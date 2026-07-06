<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 410 Gone — the resource has expired (e.g. an OTP verified past its window).
 */
class GoneException extends ApiStatusException
{
}
