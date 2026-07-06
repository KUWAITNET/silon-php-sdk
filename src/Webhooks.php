<?php

declare(strict_types=1);

namespace Silon;

use Silon\Exception\WebhookSignatureVerificationException;
use Silon\Model\Event;

/**
 * Verify Silon webhook deliveries — no HTTP client needed.
 *
 * Every delivery carries a `Silon-Signature` header of the Stripe-style form
 * `t=<unix_ts>,v1=<hex_hmac>`, where the HMAC-SHA256 is taken over
 * `"<unix_ts>." . <raw_request_body>` with the endpoint's `whsec_` secret.
 *
 * ```php
 * use Silon\Webhooks;
 *
 * $event = Webhooks::constructEvent(
 *     $request->getContent(),                 // the RAW body, not parsed JSON
 *     $request->headers->get('Silon-Signature'),
 *     $_ENV['SILON_WEBHOOK_SECRET'],
 * );
 * if ($event->type === 'message.failed') { ... }
 * ```
 */
final class Webhooks
{
    public const SIGNATURE_HEADER = 'Silon-Signature';

    /** Maximum accepted clock skew, in seconds, between the signed timestamp and now. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Produce a valid `Silon-Signature` value — useful in tests and mocks.
     */
    public static function sign(string $secret, string $payload, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();

        return 't=' . $ts . ',v1=' . self::digest($secret, $ts, $payload);
    }

    /**
     * True iff `$header` is a valid signature for `$payload` under `$secret`
     * and within `$tolerance` seconds of now (`$tolerance <= 0` skips the
     * freshness check). A malformed header returns `false`.
     */
    public static function verifySignature(
        string $payload,
        string $header,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): bool {
        [$ts, $sig] = self::parseHeader($header);
        if ($ts === null || $sig === null || $sig === '') {
            return false;
        }
        if ($tolerance > 0 && abs(time() - $ts) > $tolerance) {
            return false;
        }
        $expected = self::digest($secret, $ts, $payload);

        return hash_equals($expected, $sig);
    }

    /**
     * Verify the signature and parse `$payload` into an {@see Event}.
     *
     * @throws WebhookSignatureVerificationException when the signature is
     *   missing, stale, or does not match.
     */
    public static function constructEvent(
        string $payload,
        string $header,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): Event {
        if (!self::verifySignature($payload, $header, $secret, $tolerance)) {
            throw new WebhookSignatureVerificationException(
                'Webhook signature verification failed. Check that you are using the '
                . "endpoint's whsec_ secret and the raw (unparsed) request body."
            );
        }
        $decoded = json_decode($payload, true);

        return new Event(is_array($decoded) ? $decoded : []);
    }

    private static function digest(string $secret, int $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * @return array{0:int|null,1:string|null} the timestamp and v1 signature
     */
    private static function parseHeader(string $header): array
    {
        $ts = null;
        $sig = null;
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $key = substr($part, 0, $eq);
            $value = substr($part, $eq + 1);
            if ($key === 't' && $value !== '' && ctype_digit($value)) {
                $ts = (int) $value;
            } elseif ($key === 'v1') {
                $sig = $value;
            }
        }

        return [$ts, $sig];
    }
}
