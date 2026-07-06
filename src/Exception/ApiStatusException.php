<?php

declare(strict_types=1);

namespace Silon\Exception;

use Silon\Http\Response;

/**
 * An API response with a 4xx/5xx status code.
 *
 * The status code selects the concrete subclass (see {@see ErrorFactory}).
 * Both API error shapes are normalized onto this exception: {@see $errors}
 * always holds a list of {@see ErrorDetail}, {@see $errorType} the `type`
 * discriminator, {@see $retryable} the body's top-level `retryable` flag
 * (`null` when a legacy/non-v1 body omits it), and {@see $body} the parsed
 * JSON body (useful for shapes like the OTP-verify failure, which carries
 * `remaining_attempts`).
 */
class ApiStatusException extends SilonException
{
    /**
     * @param list<ErrorDetail>    $errors
     * @param array<string,mixed>|list<mixed>|null $body
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?Response $response = null,
        public readonly ?string $requestId = null,
        public readonly ?string $errorType = null,
        public readonly array $errors = [],
        public readonly ?bool $retryable = null,
        public readonly mixed $body = null,
    ) {
        parent::__construct($message);
    }
}
