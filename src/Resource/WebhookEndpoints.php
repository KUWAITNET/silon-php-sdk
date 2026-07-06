<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\WebhookAttempt;
use Silon\Model\WebhookEndpoint;
use Silon\Model\WebhookEndpointTestResult;
use Silon\Model\WebhookEndpointWithSecret;
use Silon\Util;

/**
 * `$client->webhookEndpoints` — manage outbound webhook subscriptions
 * (`/api/v1/webhook_endpoints`).
 */
final class WebhookEndpoints extends Resource
{
    private const PATH = '/api/v1/webhook_endpoints';

    /**
     * List webhook endpoints (cursor-paginated).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<WebhookEndpoint>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, WebhookEndpoint::class, $data);
    }

    /**
     * Subscribe an HTTPS URL to events. The response includes the one-time
     * signing `secret` (`whsec_`) — store it now, it is never returned again.
     * `enabled_events` defaults to `["*"]`; `livemode` is fixed at create time
     * and defaults to `true`.
     *
     * @param array<string,mixed> $params url (required), description, enabled_events, livemode
     */
    public function create(array $params): WebhookEndpointWithSecret
    {
        $data = $this->client->post(self::PATH, ['json' => Util::dropNull($params)]);

        return new WebhookEndpointWithSecret($data);
    }

    public function retrieve(string $endpointId): WebhookEndpoint
    {
        $data = $this->client->get(self::PATH . '/' . rawurlencode($endpointId));

        return new WebhookEndpoint($data);
    }

    /**
     * Partial update; set `status: "disabled"` to pause deliveries.
     *
     * @param array<string,mixed> $params url, description, enabled_events, status
     */
    public function update(string $endpointId, array $params): WebhookEndpoint
    {
        $data = $this->client->patch(self::PATH . '/' . rawurlencode($endpointId), Util::dropNull($params));

        return new WebhookEndpoint($data);
    }

    public function delete(string $endpointId): void
    {
        $this->client->delete(self::PATH . '/' . rawurlencode($endpointId));
    }

    /**
     * Send a signed `ping` to the endpoint (`POST .../{id}/test`). A failing
     * sink is NOT an error — the result carries `delivered=false` with the
     * reason in `error`. A mode mismatch or unknown id raises
     * {@see \Silon\Exception\NotFoundException}.
     */
    public function test(string $endpointId): WebhookEndpointTestResult
    {
        $data = $this->client->post(self::PATH . '/' . rawurlencode($endpointId) . '/test');

        return new WebhookEndpointTestResult($data);
    }

    /**
     * List an endpoint's delivery attempts (cursor-paginated). An unknown
     * endpoint id raises {@see \Silon\Exception\NotFoundException}.
     *
     * @param array<string,mixed> $params cursor, limit
     * @return CursorPage<WebhookAttempt>
     */
    public function listAttempts(string $endpointId, array $params = []): CursorPage
    {
        $path = self::PATH . '/' . rawurlencode($endpointId) . '/attempts';
        $query = Util::dropNull($params);
        $data = $this->client->get($path, $query);

        return new CursorPage($this->client, $path, $query, WebhookAttempt::class, $data);
    }
}
