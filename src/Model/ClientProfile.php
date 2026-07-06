<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * A CRM contact (`/api/v1/crm/clients/`).
 */
final class ClientProfile extends Model
{
    /**
     * Your stable identifier for this contact — the value passed as
     * `to.client_id` (or inside an `audience`) when sending a message.
     */
    public string $client_id = '';

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone_number = '';
    public ?string $civil_id = null;
    public string $notes = '';
    public string $default_language = '';
    public string $default_channel = '';

    protected static function schema(): array
    {
        return [
            'client_id' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'email' => 'string',
            'phone_number' => 'string',
            'civil_id' => 'string',
            'notes' => 'string',
            'default_language' => 'string',
            'default_channel' => 'string',
        ];
    }
}
