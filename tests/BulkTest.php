<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Exception\SilonException;
use Silon\Model\BulkBatchDetail;
use Silon\Model\BulkFileUpload;
use Silon\Model\BulkSendResult;

final class BulkTest extends TestCase
{
    private const BULK = '/api/v1/bulk/';
    private const FILES = '/api/v1/bulk/files/';

    private const BATCH = [
        'id' => 12, 'filename' => 'batch.csv', 'success' => 8, 'total' => 10,
        'channels' => ['sms'], 'created_at' => '2026-07-01T09:00:00Z', 'sent_at' => null, 'timezone' => 'Asia/Kuwait',
    ];

    private const SEND_RESPONSE = ['ok' => 1, 'message' => 'Queued', 'bulk_id' => 12, 'queued' => 10, 'failed' => 0, 'filename' => 'batch.csv'];

    private const UPLOAD_RESPONSE = ['name' => '0d9f.csv', 'original_filename' => 'contacts.csv', 'size' => 33, 'modified_at' => '2026-07-01T00:00:00Z'];

    public function testList(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [self::BATCH]);
        $batches = $this->makeClient($http)->bulk->list();
        $this->assertSame(12, $batches[0]->id);
        $this->assertSame(8, $batches[0]->success);
    }

    public function testRetrieveDetail(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            ...self::BATCH, 'provider' => 'twilio', 'applications' => [], 'web_applications' => [],
            'sender' => '', 'template' => [], 'subject' => '', 'messages' => ['Hello'], 'scheduled_at' => null,
            'recipients' => [['id' => 1, 'client_id' => 'cust_001', 'phone_number' => '+1', 'email' => '', 'status' => 'SENT', 'error' => '']],
        ]);
        $detail = $this->makeClient($http)->bulk->retrieve(12);
        $this->assertInstanceOf(BulkBatchDetail::class, $detail);
        $this->assertSame('SENT', $detail->recipients[0]->status);
    }

    public function testSendInlineRecipients(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::SEND_RESPONSE);
        $result = null;
        $deprecations = $this->captureDeprecations(function () use ($http, &$result) {
            $result = $this->makeClient($http)->bulk->send([
                'recipients' => [['client_id' => 'cust_001', 'channel' => 'sms'], ['phone_number' => '+1', 'channel' => 'whatsapp']],
                'channel' => 'sms,whatsapp',
                'message' => 'Flash sale on now',
                'remove_duplicates' => true,
            ]);
        });
        $this->assertNotEmpty($deprecations);
        $this->assertStringContainsString('sendBatch', $deprecations[0]);
        $this->assertInstanceOf(BulkSendResult::class, $result);
        $this->assertSame(12, $result->bulk_id);
        $body = $this->body($http->last());
        $this->assertCount(2, $body['recipients']);
        $this->assertSame('sms,whatsapp', $body['channel']);
        $this->assertTrue($body['remove_duplicates']);
        $this->assertArrayNotHasKey('bulk_file', $body);
    }

    public function testSendFromSavedFile(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::SEND_RESPONSE);
        $this->captureDeprecations(function () use ($http) {
            $this->makeClient($http)->bulk->send(['bulk_file' => 'saved-uuid.csv', 'template' => 'welcome', 'language' => 'ar']);
        });
        $body = $this->body($http->last());
        $this->assertSame('saved-uuid.csv', $body['bulk_file']);
        $this->assertSame('welcome', $body['template']);
    }

    public function testSendRequiresExactlyOneSource(): void
    {
        $http = new MockHttpClient();
        $caught = null;
        $deprecations = $this->captureDeprecations(function () use ($http, &$caught) {
            try {
                $this->makeClient($http)->bulk->send(['channel' => 'sms']);
            } catch (SilonException $e) {
                $caught = $e;
            }
        });
        $this->assertInstanceOf(SilonException::class, $caught);
        $this->assertStringContainsString('exactly one', $caught->getMessage());
        $this->assertNotEmpty($deprecations);
    }

    public function testSendWhatsappTemplateQueryParams(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::SEND_RESPONSE);
        $this->captureDeprecations(function () use ($http) {
            $this->makeClient($http)->bulk->send([
                'bulk_file' => 'x.csv',
                'provider' => 'meta_cloud',
                'whatsapp_template' => 'order_shipped',
                'whatsapp_template_language' => 'en',
            ]);
        });
        $params = $this->query($http->last());
        $this->assertSame('meta_cloud', $params['provider']);
        $this->assertSame('order_shipped', $params['whatsapp_template']);
        $this->assertSame('en', $params['whatsapp_template_language']);
        // query keys must not leak into the JSON body
        $this->assertArrayNotHasKey('provider', $this->body($http->last()));
    }

    public function testFilesList(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['count' => 1, 'results' => [['name' => 'a.csv', 'size' => 42, 'modified_at' => '2026-07-01T00:00:00Z']]]);
        $files = $this->makeClient($http)->bulk->files->list();
        $this->assertSame(1, $files->count);
        $this->assertSame('a.csv', $files->results[0]->name);
    }

    public function testUploadBytes(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::UPLOAD_RESPONSE);
        $uploaded = $this->makeClient($http)->bulk->files->upload("client_id,channel\ncust_001,sms\n");
        $this->assertInstanceOf(BulkFileUpload::class, $uploaded);
        $request = $http->last();
        $this->assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="file"', (string) $request->body);
        $this->assertStringContainsString('filename="recipients.csv"', (string) $request->body);
        $this->assertStringContainsString('cust_001,sms', (string) $request->body);
        $this->assertStringContainsString('Content-Type: text/csv', (string) $request->body);
    }

    public function testUploadPath(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::UPLOAD_RESPONSE);
        $tmp = tempnam(sys_get_temp_dir(), 'silon') . '-contacts.csv';
        file_put_contents($tmp, "client_id\ncust_001\n");
        try {
            $this->makeClient($http)->bulk->files->upload($tmp);
            $this->assertStringContainsString('filename="' . basename($tmp) . '"', (string) $http->last()->body);
        } finally {
            @unlink($tmp);
        }
    }

    public function testUploadTupleWithCustomName(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::UPLOAD_RESPONSE);
        $this->makeClient($http)->bulk->files->upload(['q3-campaign.csv', "client_id\n"]);
        $this->assertStringContainsString('filename="q3-campaign.csv"', (string) $http->last()->body);
    }

    public function testRecipientDetail(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [
            'id' => 5, 'file_name' => 'batch.csv', 'status' => 'SENT', 'channel' => 'sms', 'provider' => 'twilio',
            'application' => '', 'web_app' => '', 'sender' => '', 'template' => '', 'subject' => '', 'messages' => 'Hello',
            'created_at' => '2026-07-01T09:00:00Z', 'scheduled_at' => '2026-07-01T09:00:00Z', 'sent_at' => null,
        ]);
        $recipient = $this->makeClient($http)->bulk->recipients->retrieve(5);
        $this->assertSame('sms', $recipient->channel);
        $this->assertNull($recipient->sent_at);
        $this->assertSame('/api/v1/bulk/recipient/5/', $this->path($http->last()));
    }
}
