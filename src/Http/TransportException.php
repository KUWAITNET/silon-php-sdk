<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * A low-level transport failure (no HTTP response was produced): DNS, TLS,
 * socket, or timeout. The {@see Client} maps this onto
 * {@see \Silon\Exception\ApiTimeoutException} /
 * {@see \Silon\Exception\ApiConnectionException} (and applies its retry policy).
 */
final class TransportException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $isTimeout = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
