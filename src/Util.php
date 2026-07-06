<?php

declare(strict_types=1);

namespace Silon;

use DateTimeInterface;
use Silon\Http\Response;

/**
 * Small internal helpers shared across the SDK. Not part of the public API.
 *
 * @internal
 */
final class Util
{
    /**
     * Return the array with every `null` value removed (`null` fields are
     * omitted from the JSON body on every endpoint).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function dropNull(array $data): array
    {
        return array_filter($data, static fn ($value) => $value !== null);
    }

    /**
     * A random RFC 4122 version-4 UUID — the default `Idempotency-Key`.
     */
    public static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * ISO-8601 wire value for a date-time argument (e.g. `send_at`).
     *
     * A {@see DateTimeInterface} serializes with its UTC offset (give it one —
     * the server rejects naive date-times); a string passes through verbatim;
     * `null` stays `null`.
     */
    public static function isoDatetime(DateTimeInterface|string|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            // e.g. 2026-08-01T09:30:00+00:00 — RFC 3339 with the offset.
            return $value->format('Y-m-d\TH:i:sP');
        }

        return $value;
    }

    /**
     * Seconds the server asked us to wait before retrying, if advertised.
     *
     * Reads the standard `Retry-After` header (delta-seconds or an HTTP-date)
     * and falls back to the IETF-draft `RateLimit-Reset` header, which Silon
     * sends as a Unix epoch on throttled endpoints. Returns `null` when
     * neither is present or parseable.
     */
    public static function parseRetryAfter(Response $response, ?float $now = null): ?float
    {
        $now ??= (float) time();

        $retryAfter = $response->getHeaderLine('Retry-After');
        if ($retryAfter !== '') {
            if (is_numeric($retryAfter)) {
                return max(0.0, (float) $retryAfter);
            }
            $when = strtotime($retryAfter);
            if ($when !== false) {
                return max(0.0, (float) $when - $now);
            }

            return null;
        }

        $reset = $response->getHeaderLine('RateLimit-Reset');
        if ($reset !== '' && is_numeric($reset)) {
            return max(0.0, (float) $reset - $now);
        }

        return null;
    }
}
