<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * One normalized error entry from an API error response.
 *
 * Both API error shapes — the standard DRF body
 * (`{"errors": [{code, detail, attr}]}`) and the inline problem body
 * (`{"type", "title", "detail", "field"}`) — are normalized onto a list of
 * these on {@see ApiStatusException::$errors}.
 */
final class ErrorDetail
{
    public function __construct(
        public readonly string $code,
        public readonly string $detail,
        public readonly ?string $attr = null,
    ) {
    }
}
