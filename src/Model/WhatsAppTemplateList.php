<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `GET /api/v1/whatsapp/templates/` — the `{count, results}` shape.
 */
final class WhatsAppTemplateList extends Model
{
    public int $count = 0;

    /** @var list<WhatsAppTemplate> */
    public array $results = [];

    protected static function schema(): array
    {
        return ['count' => 'int', 'results' => WhatsAppTemplate::class . '[]'];
    }
}
