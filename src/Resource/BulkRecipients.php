<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\BulkRecipientDetail;

/**
 * `$client->bulk->recipients` — per-recipient rows of a bulk batch.
 */
final class BulkRecipients extends Resource
{
    public function retrieve(int $recipientId): BulkRecipientDetail
    {
        $data = $this->client->get('/api/v1/bulk/recipient/' . $recipientId . '/');

        return new BulkRecipientDetail($data);
    }
}
