<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * Base class for every error raised by this SDK.
 *
 * Configuration mistakes (missing API key / base URL), client-side validation
 * failures (the message target XOR), a webhook signature that does not verify,
 * and non-JSON success bodies all surface as a {@see SilonException} (or a
 * subclass); every non-2xx API response raises an {@see ApiStatusException}.
 */
class SilonException extends \Exception
{
}
