<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * 403 Forbidden — the key is valid but lacks the scope for this operation
 * (e.g. `override_suppression` without the `suppressions:override` scope).
 */
class PermissionDeniedException extends ApiStatusException
{
}
