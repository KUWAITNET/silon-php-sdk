<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * An immutable HTTP response returned by an {@see HttpClientInterface}.
 *
 * @internal
 */
final class Response
{
    /** @var array<string,string> header name (as received) -> value */
    private array $headers;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        array $headers = [],
        private readonly string $body = '',
        private readonly string $reasonPhrase = '',
    ) {
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** Case-insensitive header lookup; empty string when absent. */
    public function getHeaderLine(string $name): string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return '';
    }
}
