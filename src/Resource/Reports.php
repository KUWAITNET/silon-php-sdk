<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\ConversationsReport;
use Silon\Model\ProviderBalance;
use Silon\Model\Report;
use Silon\Util;

/**
 * `$client->reports` — activity reports (all POST endpoints share a
 * date-range filter).
 */
final class Reports extends Resource
{
    private const BASE = '/api/v1/reports';

    /**
     * Messages activity. `report_type`: `phone` (SMS/TTS/WhatsApp), `email`,
     * `mobile` (push) or `web` (web push).
     *
     * @param array<string,mixed> $params report_type (required), date_from,
     *   date_to, search, device, status, template, source
     */
    public function messages(array $params): Report
    {
        return $this->post('/messages/', $params);
    }

    /** @param array<string,mixed> $params date_from, date_to, channel_name */
    public function channels(array $params = []): Report
    {
        return $this->post('/channels/', $params);
    }

    /**
     * @param array<string,mixed> $params date_from, date_to, search, language,
     *   device, web_subscription
     */
    public function clients(array $params = []): Report
    {
        return $this->post('/clients/', $params);
    }

    /** @param array<string,mixed> $params date_from, date_to, search */
    public function users(array $params = []): Report
    {
        return $this->post('/users/', $params);
    }

    /** @param array<string,mixed> $params date_from, date_to, search */
    public function bulks(array $params = []): Report
    {
        return $this->post('/bulks/', $params);
    }

    /**
     * @param array<string,mixed> $params bulks_file (required int), date_from,
     *   date_to, search, mobile_app, web_app, status
     */
    public function specificBulks(array $params): Report
    {
        return $this->post('/specific-bulks/', $params);
    }

    /**
     * @param array<string,mixed> $params report_type (required), date_from,
     *   date_to, search, mobile_app, web_app, device_type
     */
    public function subscriptions(array $params): Report
    {
        return $this->post('/subscriptions/', $params);
    }

    /** AWS usage statistics. @param array<string,mixed> $params page */
    public function awsUsage(array $params = []): Report
    {
        $data = $this->client->post(self::BASE . '/aws-usage-statistics/', [
            'query' => Util::dropNull($params),
        ]);

        return new Report($data);
    }

    /**
     * Support-desk metrics for Live Desk conversations (first-response /
     * resolution / reply times, CSAT, open/unassigned/unattended), with an
     * agent / channel / team / label breakdown. A GET with query params.
     *
     * @param array<string,mixed> $params date_from, date_to,
     *   group_by (agent|channel|team|label), business_hours (bool),
     *   channels (comma-separated slugs), compare (bool)
     */
    public function conversations(array $params = []): ConversationsReport
    {
        $data = $this->client->get(self::BASE . '/conversations/', Util::dropNull($params));

        return new ConversationsReport($data);
    }

    /** Upstream balance for a provider account. */
    public function balance(string $slug): ProviderBalance
    {
        $data = $this->client->get(self::BASE . '/balance/' . rawurlencode($slug) . '/');

        return new ProviderBalance($data);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function post(string $path, array $params): Report
    {
        $data = $this->client->post(self::BASE . $path, ['json' => Util::dropNull($params)]);

        return new Report($data);
    }
}
