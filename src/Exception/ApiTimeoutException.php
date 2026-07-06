<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * The request timed out. A subtype of {@see ApiConnectionException}, so
 * `catch (ApiConnectionException)` handles both timeouts and other transport
 * failures.
 */
class ApiTimeoutException extends ApiConnectionException
{
    public function __construct(string $message = 'Request timed out.', ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
