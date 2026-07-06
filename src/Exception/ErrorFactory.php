<?php

declare(strict_types=1);

namespace Silon\Exception;

use Silon\Http\Response;
use Silon\Util;

/**
 * Builds the right {@see ApiStatusException} subclass for a non-2xx response.
 *
 * Normalizes both API error shapes — the standard DRF body
 * (`{"type", "errors": [{code, detail, attr}], "retryable"}`) and the inline
 * problem body (`{"type": url, "title", "status", "detail", "field",
 * "retryable"}`) — onto a common set of exception fields.
 *
 * @internal
 */
final class ErrorFactory
{
    /** @var array<int,class-string<ApiStatusException>> */
    private const STATUS_MAP = [
        400 => BadRequestException::class,
        401 => AuthenticationException::class,
        403 => PermissionDeniedException::class,
        404 => NotFoundException::class,
        409 => ConflictException::class,
        410 => GoneException::class,
        422 => UnprocessableEntityException::class,
        429 => RateLimitException::class,
    ];

    public static function fromResponse(Response $response): ApiStatusException
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $body = self::decode($raw);

        $errorType = null;
        $errors = [];
        $retryable = null;
        $message = '';

        if (is_array($body)) {
            $errorType = isset($body['type']) && is_string($body['type']) ? $body['type'] : ($body['type'] ?? null);
            // Every v1 error body (both shapes) carries a top-level `retryable`
            // bool. Read it verbatim; leave it null for legacy/non-v1 bodies
            // that omit it (never recompute from the status code).
            if (isset($body['retryable']) && is_bool($body['retryable'])) {
                $retryable = $body['retryable'];
            }

            if (isset($body['errors']) && is_array($body['errors'])) {
                // Standard DRF shape.
                foreach ($body['errors'] as $entry) {
                    if (is_array($entry)) {
                        $errors[] = new ErrorDetail(
                            code: (string) ($entry['code'] ?? ''),
                            detail: (string) ($entry['detail'] ?? ''),
                            attr: $entry['attr'] ?? null,
                        );
                    }
                }
                if ($errors !== []) {
                    $first = $errors[0];
                    $message = $first->attr !== null && $first->attr !== ''
                        ? "{$first->attr}: {$first->detail}"
                        : $first->detail;
                }
            } elseif (array_key_exists('detail', $body)) {
                // Inline problem shape.
                $detail = (string) ($body['detail'] ?? '');
                $errors[] = new ErrorDetail(
                    code: self::slugFromType($errorType) ?: (string) ($body['title'] ?? ''),
                    detail: $detail,
                    attr: $body['field'] ?? null,
                );
                $message = $detail;
            }
        }

        if ($message === '') {
            $reason = $response->getReasonPhrase();
            $message = 'HTTP ' . $status . ': ' . ($reason !== '' ? $reason : substr($raw, 0, 200));
        }

        $errorType = is_string($errorType) ? $errorType : null;

        if ($status === 429) {
            return new RateLimitException(
                message: $message,
                statusCode: $status,
                response: $response,
                requestId: self::requestId($response),
                errorType: $errorType,
                errors: $errors,
                retryable: $retryable,
                body: $body,
                retryAfter: Util::parseRetryAfter($response),
            );
        }

        $class = self::STATUS_MAP[$status]
            ?? ($status >= 500 ? InternalServerException::class : ApiStatusException::class);

        return new $class(
            message: $message,
            statusCode: $status,
            response: $response,
            requestId: self::requestId($response),
            errorType: $errorType,
            errors: $errors,
            retryable: $retryable,
            body: $body,
        );
    }

    /**
     * `https://silon.tech/docs/errors/not-found` -> `not-found`.
     */
    private static function slugFromType(mixed $errorType): string
    {
        if (is_string($errorType) && str_contains($errorType, '/')) {
            $trimmed = rtrim($errorType, '/');
            $pos = strrpos($trimmed, '/');

            return $pos === false ? $trimmed : substr($trimmed, $pos + 1);
        }

        return $errorType === null ? '' : (string) $errorType;
    }

    private static function requestId(Response $response): ?string
    {
        $id = $response->getHeaderLine('X-Request-Id');

        return $id !== '' ? $id : null;
    }

    /**
     * Parse a JSON body, returning `null` when it is not valid JSON.
     *
     * @return array<string,mixed>|list<mixed>|null
     */
    private static function decode(string $raw): array|null
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
