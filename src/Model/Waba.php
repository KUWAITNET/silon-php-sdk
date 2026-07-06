<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * The WhatsApp Business Account a template belongs to.
 */
final class Waba extends Model
{
    public ?int $id = null;
    public ?string $name = null;

    protected static function schema(): array
    {
        return ['id' => 'int', 'name' => 'string'];
    }
}
