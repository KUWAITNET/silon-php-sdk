<?php

declare(strict_types=1);

namespace Silon\Exception;

/**
 * A webhook payload failed `Silon-Signature` verification — the signature is
 * missing, stale, or does not match the endpoint's `whsec_` secret.
 */
class WebhookSignatureVerificationException extends SilonException
{
}
