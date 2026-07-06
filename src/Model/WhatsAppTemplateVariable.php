<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * One variable of an approved WhatsApp template.
 */
final class WhatsAppTemplateVariable extends Model
{
    /** Use this key in the send `whatsapp_template.variables` map. */
    public string $key = '';

    public string $label = '';

    protected static function schema(): array
    {
        return ['key' => 'string', 'label' => 'string'];
    }
}
