<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * The request never produced an HTTP response (DNS, TLS, socket, ...).
 */
class ApiConnectionException extends SilonException
{
    public function __construct(string $message = 'Connection error.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
