<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Echo body of the legacy `POST /api/v1/webpush/client/`.
 */
final class WebPushSubscription extends Model
{
    public string $client_id = '';
    public string $slug = '';
    public string $subscription_info = '';

    protected static function schema(): array
    {
        return ['client_id' => 'string', 'slug' => 'string', 'subscription_info' => 'string'];
    }
}
