<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Create response — includes the one-time signing `secret`.
 */
final class WebhookEndpointWithSecret extends WebhookEndpoint
{
    /**
     * Signing secret (`whsec_` prefix). Shown ONCE, only here. Use it to
     * verify the `Silon-Signature` header on every delivery.
     */
    public string $secret = '';

    protected static function schema(): array
    {
        return array_merge(parent::schema(), ['secret' => 'string']);
    }
}
