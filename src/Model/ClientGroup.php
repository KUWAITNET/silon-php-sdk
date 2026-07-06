<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * A CRM client group (`/api/v1/crm/groups/`).
 */
final class ClientGroup extends Model
{
    public int $id = 0;
    public string $name = '';

    /** Pass as `audience.slug` with `audience.type: "client_group"`. */
    public string $slug = '';

    public bool $is_active = true;

    /** @var list<ClientProfile> */
    public array $clients = [];

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'name' => 'string',
            'slug' => 'string',
            'is_active' => 'bool',
            'clients' => ClientProfile::class . '[]',
        ];
    }
}
