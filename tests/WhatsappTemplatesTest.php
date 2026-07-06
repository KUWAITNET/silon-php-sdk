<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Model\WhatsAppTemplate;

final class WhatsappTemplatesTest extends TestCase
{
    private const TEMPLATES = '/api/v1/whatsapp/templates/';

    private const TEMPLATE = [
        'id' => 3, 'name' => 'order_confirmed', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED',
        'external_id' => '1234567890', 'waba' => ['id' => 1, 'name' => 'Silon Test'],
        'preview' => 'Hi {{1}}, order {{2}} is confirmed.', 'mode' => 'structured',
        'variables' => [['key' => 'body_1', 'label' => 'Customer name'], ['key' => 'body_2', 'label' => 'Order id']],
    ];

    public function testListWithFilters(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['count' => 1, 'results' => [self::TEMPLATE]]);
        $templates = $this->makeClient($http)->whatsappTemplates->list(['name' => 'order', 'language' => 'en', 'waba_id' => 1]);
        $this->assertSame(1, $templates->count);
        $this->assertSame('body_1', $templates->results[0]->variables[0]->key);
        $params = $this->query($http->last());
        $this->assertSame('order', $params['name']);
        $this->assertSame('en', $params['language']);
        $this->assertSame('1', $params['waba_id']);
    }

    public function testRetrieveIncludesComponents(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::TEMPLATE, 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']]]);
        $template = $this->makeClient($http)->whatsappTemplates->retrieve(3);
        $this->assertInstanceOf(WhatsAppTemplate::class, $template);
        $this->assertSame([['type' => 'BODY', 'text' => 'Hi {{1}}']], $template->components);
        $this->assertSame('Silon Test', $template->waba->name);
        $this->assertSame('/api/v1/whatsapp/templates/3/', $this->path($http->last()));
    }
}
