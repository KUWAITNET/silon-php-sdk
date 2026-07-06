<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * The transport seam. The SDK ships {@see CurlHttpClient} (native curl, no
 * dependencies) as the default; inject your own implementation to control TLS
 * (private CAs, client certs), route through a proxy, or drive the SDK against
 * a mock in tests.
 *
 * An implementation MUST NOT throw on a 4xx/5xx response — those are ordinary
 * {@see Response} objects (the SDK maps them to typed exceptions). It MUST
 * throw a {@see TransportException} when no response is produced (connection
 * failure or timeout).
 */
interface HttpClientInterface
{
    /**
     * @param float $timeout per-attempt timeout, in seconds
     * @throws TransportException when no HTTP response is produced
     */
    public function send(Request $request, float $timeout): Response;
}
