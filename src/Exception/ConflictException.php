<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 409 Conflict — an idempotency conflict (same key, different body), a
 * duplicate template slug, or a non-cancellable send.
 */
class ConflictException extends ApiStatusException
{
}
