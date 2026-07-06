<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 404 Not Found — no such resource, or an id that does not match the key's
 * mode (a live key addressing a test-mode resource, or vice versa).
 */
class NotFoundException extends ApiStatusException
{
}
