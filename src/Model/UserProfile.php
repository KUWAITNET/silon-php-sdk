<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `GET /api/v1/profile/`.
 */
class UserProfile extends Model
{
    public string $email = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $phone_number = '';
    public ?string $civil_id = null;
    public string $default_language = '';

    /** The linked contact profile's client_id (read-only). */
    public string $client_id = '';

    protected static function schema(): array
    {
        return [
            'email' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'phone_number' => 'string',
            'civil_id' => 'string',
            'default_language' => 'string',
            'client_id' => 'string',
        ];
    }
}
