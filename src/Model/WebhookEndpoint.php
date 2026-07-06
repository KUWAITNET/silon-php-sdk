<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * A webhook endpoint as returned by the API.
 *
 * The signing `secret` is never present here — it is revealed once, in the
 * create response ({@see WebhookEndpointWithSecret}).
 */
class WebhookEndpoint extends Model
{
    /** Opaque endpoint id, `we_` prefixed. */
    public string $id = '';

    public string $object = 'webhook_endpoint';
    public string $url = '';

    /**
     * `true`: receives events from live sends; `false`: from test-mode
     * (`sk_test_`) sends. Fixed at create time.
     */
    public bool $livemode = true;

    public string $description = '';

    /** @var list<string> Event types delivered, or `["*"]` for all. */
    public array $enabled_events = [];

    public string $status = 'enabled';
    public ?DateTimeImmutable $created_at = null;

    protected static function schema(): array
    {
        return [
            'id' => 'string',
            'object' => 'string',
            'url' => 'string',
            'livemode' => 'bool',
            'description' => 'string',
            'enabled_events' => 'mixed',
            'status' => 'string',
            'created_at' => 'datetime',
        ];
    }
}
