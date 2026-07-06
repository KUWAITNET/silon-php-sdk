<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\WhatsAppTemplate;
use Silon\Model\WhatsAppTemplateList;
use Silon\Util;

/**
 * `$client->whatsappTemplates` — read approved WhatsApp templates.
 */
final class WhatsAppTemplates extends Resource
{
    private const PATH = '/api/v1/whatsapp/templates/';

    /**
     * List approved WhatsApp templates.
     *
     * @param array<string,mixed> $params name, language, waba_id
     */
    public function list(array $params = []): WhatsAppTemplateList
    {
        $data = $this->client->get(self::PATH, Util::dropNull($params));

        return new WhatsAppTemplateList($data);
    }

    /** One template with its components + variables. */
    public function retrieve(int $templateId): WhatsAppTemplate
    {
        $data = $this->client->get(self::PATH . $templateId . '/');

        return new WhatsAppTemplate($data);
    }
}
