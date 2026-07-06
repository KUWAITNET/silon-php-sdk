<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Exception\SilonException;
use Silon\Model\MarkReadResult;
use Silon\Model\PushClientDevices;
use Silon\Model\PushNotification;
use Silon\Model\SubscribeResult;
use Silon\Model\WebPushSubscription;
use Silon\Util;

/**
 * `$client->push` — mobile / web push device registration and native
 * notification feeds.
 */
final class Push extends Resource
{
    private const PLATFORM_PATHS = [
        'android' => '/api/v1/push/android/%s/',
        'ios' => '/api/v1/push/ios/%s/',
    ];

    /**
     * Register an Android device token for the app `slug`.
     *
     * @param array<string,mixed> $params slug (required), token
     */
    public function subscribeAndroid(array $params): SubscribeResult
    {
        $data = $this->client->post('/api/v1/subscribe/android/', ['json' => Util::dropNull($params)]);

        return new SubscribeResult($data);
    }

    /**
     * Register an iOS device token (`environment`: `dev`/`prod`).
     *
     * @param array<string,mixed> $params slug (required), token, environment
     */
    public function subscribeIos(array $params): SubscribeResult
    {
        $data = $this->client->post('/api/v1/subscribe/ios/', ['json' => Util::dropNull($params)]);

        return new SubscribeResult($data);
    }

    /**
     * Register (or prune, with `keep_devices: false`) a client's devices.
     *
     * @param array<string,mixed> $params client_id (required), slug (required),
     *   device_type (required), device_id, keep_devices
     */
    public function upsertDevices(array $params): PushClientDevices
    {
        $data = $this->client->post('/api/v1/push/client/', ['json' => Util::dropNull($params)]);

        return new PushClientDevices($data);
    }

    /**
     * Mark a native push notification as read.
     *
     * @param array<string,mixed> $params slug (required)
     */
    public function markRead(array $params): MarkReadResult
    {
        $data = $this->client->post('/api/v1/push/read/', ['json' => ['slug' => $params['slug']]]);

        return new MarkReadResult($data);
    }

    /**
     * List native push notifications (deprecated legacy endpoints).
     *
     * @deprecated The native push notification list endpoints are deprecated.
     * @param string|null $platform `android`, `ios`, or `null` (combined list)
     * @return list<PushNotification>
     */
    public function listNotifications(string $slug, ?string $platform = null): array
    {
        if ($platform !== null && !isset(self::PLATFORM_PATHS[$platform])) {
            throw new SilonException("platform must be 'android', 'ios' or null (combined list).");
        }
        trigger_error('The native push notification list endpoints are deprecated.', E_USER_DEPRECATED);
        $path = $platform === null
            ? '/api/v1/push/list/' . rawurlencode($slug) . '/'
            : sprintf(self::PLATFORM_PATHS[$platform], rawurlencode($slug));
        $data = $this->client->get($path);

        return array_map(static fn (array $item): PushNotification => new PushNotification($item), $data);
    }

    /**
     * Register a browser subscription for a client (deprecated).
     *
     * @deprecated Register web push subscriptions through the widget instead.
     * @param array<string,mixed> $params client_id (required), slug (required), subscription_info
     */
    public function subscribeWeb(array $params): WebPushSubscription
    {
        trigger_error(
            'POST /api/v1/webpush/client/ is a legacy endpoint; register web push '
            . 'subscriptions through the widget instead.',
            E_USER_DEPRECATED,
        );
        $data = $this->client->post('/api/v1/webpush/client/', ['json' => Util::dropNull($params)]);

        return new WebPushSubscription($data);
    }
}
