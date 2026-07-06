<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * Default transport — native curl, no external dependencies.
 *
 * A 4xx/5xx is returned as an ordinary {@see Response}; only a genuine
 * transport failure (no response) throws a {@see TransportException}, with its
 * `isTimeout` flag set for curl timeouts.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function send(Request $request, float $timeout): Response
    {
        $handle = curl_init();

        $responseHeaders = [];
        $reasonPhrase = '';

        curl_setopt_array($handle, [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round(min($timeout, 10.0) * 1000),
            CURLOPT_HTTPHEADER => $this->formatHeaders($request->headers),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders, &$reasonPhrase): int {
                $trimmed = trim($line);
                if ($trimmed !== '' && str_starts_with($trimmed, 'HTTP/')) {
                    $parts = explode(' ', $trimmed, 3);
                    $reasonPhrase = $parts[2] ?? '';
                } elseif ($trimmed !== '') {
                    $colon = strpos($trimmed, ':');
                    if ($colon !== false) {
                        $name = trim(substr($trimmed, 0, $colon));
                        $responseHeaders[$name] = trim(substr($trimmed, $colon + 1));
                    }
                }

                return strlen($line);
            },
        ]);

        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }
        if ($request->method === 'HEAD') {
            curl_setopt($handle, CURLOPT_NOBODY, true);
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            curl_close($handle);
            $isTimeout = in_array($errno, [CURLE_OPERATION_TIMEDOUT, 28], true);

            throw new TransportException($error !== '' ? $error : 'curl error', $isTimeout);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, $responseHeaders, (string) $body, $reasonPhrase);
    }

    /**
     * @param array<string,string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }
}
