<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Client;
use Silon\Util;

/**
 * Base class for the API resource groups accessed via `$client->messages`,
 * `$client->broadcasts`, and so on.
 *
 * @internal
 */
abstract class Resource
{
    public function __construct(protected readonly Client $client)
    {
    }

    /**
     * Always send an `Idempotency-Key` (caller-supplied or an auto-generated
     * UUIDv4) so automatic retries can never double-send.
     *
     * @return array<string,string>
     */
    protected function idempotencyHeaders(?string $key): array
    {
        return ['Idempotency-Key' => $key ?? Util::uuid4()];
    }
}
