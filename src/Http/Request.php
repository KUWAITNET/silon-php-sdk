<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * A fully-prepared HTTP request handed to an {@see HttpClientInterface}.
 *
 * The query string is already baked into {@see $url}, and the body (JSON or
 * multipart) is already serialized into {@see $body} with the matching
 * `Content-Type` in {@see $headers} — so a transport just sends the bytes.
 *
 * @internal
 */
final class Request
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
    ) {
    }

    /** Case-insensitive header lookup. */
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
