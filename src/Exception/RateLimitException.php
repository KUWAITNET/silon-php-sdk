<?php

declare(strict_types=1);

namespace Silon\Exception;

use Silon\Http\Response;

/**
 * 429 Too Many Requests — back off until {@see $retryAfter} seconds have
 * passed. The value is parsed from the `Retry-After` header (delta-seconds or
 * an HTTP-date), falling back to the IETF-draft `RateLimit-Reset` epoch;
 * `null` when the server advertised neither.
 */
class RateLimitException extends ApiStatusException
{
    /**
     * @param list<ErrorDetail>    $errors
     * @param array<string,mixed>|list<mixed>|null $body
     */
    public function __construct(
        string $message,
        int $statusCode,
        ?Response $response = null,
        ?string $requestId = null,
        ?string $errorType = null,
        array $errors = [],
        ?bool $retryable = null,
        mixed $body = null,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct(
            $message,
            $statusCode,
            $response,
            $requestId,
            $errorType,
            $errors,
            $retryable,
            $body,
        );
    }
}
