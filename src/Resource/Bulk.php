<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Client;
use Silon\Exception\SilonException;
use Silon\Model\BulkBatch;
use Silon\Model\BulkBatchDetail;
use Silon\Model\BulkSendResult;
use Silon\Util;

/**
 * `$client->bulk` — bulk (CSV) sends: batches, saved files, and per-recipient
 * rows.
 */
final class Bulk extends Resource
{
    private const PATH = '/api/v1/bulk/';
    private const SEND_PATH = '/api/v1/bulk/send/';

    /** Query-string (not body) parameters of the deprecated bulk send. */
    private const SEND_QUERY_KEYS = [
        'provider',
        'whatsapp_template',
        'whatsapp_template_language',
        'whatsapp_template_variables',
    ];

    public readonly BulkFiles $files;
    public readonly BulkRecipients $recipients;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->files = new BulkFiles($client);
        $this->recipients = new BulkRecipients($client);
    }

    /**
     * List bulk batches (bare array).
     *
     * @return list<BulkBatch>
     */
    public function list(): array
    {
        $data = $this->client->get(self::PATH);

        return array_map(static fn (array $item): BulkBatch => new BulkBatch($item), $data);
    }

    public function retrieve(int $bulkId): BulkBatchDetail
    {
        $data = $this->client->get(self::PATH . $bulkId . '/');

        return new BulkBatchDetail($data);
    }

    /**
     * Start a bulk batch (`POST /api/v1/bulk/send/`).
     *
     * @deprecated Prefer {@see Messages::sendBatch()} (`$client->messages->sendBatch`),
     *   which covers both of this method's shapes. This endpoint keeps working
     *   unchanged, but new integrations should not adopt it.
     *
     * Supply recipients either inline via `recipients` (each row a
     * column->value map) or by referencing a previously uploaded CSV via
     * `bulk_file` — not both.
     *
     * @param array<string,mixed> $params exactly one of recipients / bulk_file,
     *   plus channel, message, template, subject, sender, group, application,
     *   web_application, language, files, name, expire, remove_duplicates,
     *   scheduled_at, timezone, and the query params provider,
     *   whatsapp_template, whatsapp_template_language, whatsapp_template_variables.
     * @throws SilonException when neither or both of `recipients` / `bulk_file` are given.
     */
    public function send(array $params): BulkSendResult
    {
        trigger_error(
            'POST /api/v1/bulk/send/ is deprecated - prefer $client->messages->sendBatch '
            . "(POST /api/v1/messages/batch/), which covers both shapes: inline rows via "
            . "'messages' and saved CSVs via 'file'.",
            E_USER_DEPRECATED,
        );

        $query = [];
        foreach (self::SEND_QUERY_KEYS as $key) {
            if (array_key_exists($key, $params)) {
                $query[$key] = $params[$key];
                unset($params[$key]);
            }
        }

        if ((($params['recipients'] ?? null) === null) === (($params['bulk_file'] ?? null) === null)) {
            throw new SilonException(
                "Provide exactly one of 'recipients' (inline rows) or 'bulk_file' (a saved CSV name)."
            );
        }

        $data = $this->client->post(self::SEND_PATH, [
            'json' => Util::dropNull($params),
            'query' => Util::dropNull($query),
        ]);

        return new BulkSendResult($data);
    }
}
