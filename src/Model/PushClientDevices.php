<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Echo body of `POST /api/v1/push/client/`.
 */
final class PushClientDevices extends Model
{
    public string $client_id = '';
    public string $slug = '';
    public string $device_id = '';
    public string $device_type = '';
    public ?bool $keep_devices = null;

    protected static function schema(): array
    {
        return [
            'client_id' => 'string',
            'slug' => 'string',
            'device_id' => 'string',
            'device_type' => 'string',
            'keep_devices' => 'bool',
        ];
    }
}
