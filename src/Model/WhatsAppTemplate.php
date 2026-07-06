<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * A single approved WhatsApp template.
 *
 * `components` (the raw Meta-shaped payload) is only present on the detail
 * endpoint; the list endpoint omits it.
 */
final class WhatsAppTemplate extends Model
{
    public int $id = 0;

    /** Pass as `whatsapp_template.name` when sending a Meta-Cloud template. */
    public string $name = '';

    public string $language = '';
    public string $category = '';
    public string $status = '';
    public string $external_id = '';
    public ?Waba $waba = null;
    public string $preview = '';
    public string $mode = '';

    /** @var list<WhatsAppTemplateVariable> */
    public array $variables = [];

    /** @var list<array<string,mixed>>|null */
    public ?array $components = null;

    protected static function schema(): array
    {
        return [
            'id' => 'int',
            'name' => 'string',
            'language' => 'string',
            'category' => 'string',
            'status' => 'string',
            'external_id' => 'string',
            'waba' => Waba::class,
            'preview' => 'string',
            'mode' => 'string',
            'variables' => WhatsAppTemplateVariable::class . '[]',
            'components' => 'mixed',
        ];
    }
}
