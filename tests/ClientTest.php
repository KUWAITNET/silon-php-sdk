<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Client;
use Silon\Exception\SilonException;

final class ClientTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach (['SILON_API_KEY', 'SILON_BASE_URL', 'SILON_WORKSPACE'] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    // -- configuration -----------------------------------------------------

    public function testMissingApiKeyRaises(): void
    {
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('No API key');
        new Client(['baseUrl' => self::BASE_URL]);
    }

    public function testMissingBaseUrlRaises(): void
    {
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('No base URL');
        new Client(['apiKey' => self::API_KEY]);
    }

    public function testWorkspaceBuildsBaseUrl(): void
    {
        $client = new Client(['apiKey' => self::API_KEY, 'workspace' => 'acme']);
        $this->assertSame('https://acme.silon.tech', $client->baseUrl);
    }

    public function testExplicitBaseUrlWinsOverWorkspace(): void
    {
        $client = new Client([
            'apiKey' => self::API_KEY,
            'workspace' => 'acme',
            'baseUrl' => 'https://other.example',
        ]);
        $this->assertSame('https://other.example', $client->baseUrl);
    }

    public function testTrailingSlashStripped(): void
    {
        $client = new Client(['apiKey' => self::API_KEY, 'baseUrl' => self::BASE_URL . '/']);
        $this->assertSame(self::BASE_URL, $client->baseUrl);
    }

    public function testEnvFallbacks(): void
    {
        putenv('SILON_API_KEY=sk_live_env');
        putenv('SILON_WORKSPACE=envspace');
        $client = new Client();
        $this->assertSame('sk_live_env', $client->apiKey);
        $this->assertSame('https://envspace.silon.tech', $client->baseUrl);
    }

    public function testEnvBaseUrl(): void
    {
        putenv('SILON_API_KEY=sk_live_env');
        putenv('SILON_BASE_URL=https://on-prem.example/');
        $client = new Client();
        $this->assertSame('https://on-prem.example', $client->baseUrl);
    }

    public function testExplicitBaseUrlWinsOverEnvWorkspace(): void
    {
        putenv('SILON_WORKSPACE=envspace');
        $client = new Client(['apiKey' => self::API_KEY, 'baseUrl' => self::BASE_URL]);
        $this->assertSame(self::BASE_URL, $client->baseUrl);
    }

    // -- transport ---------------------------------------------------------

    public function testAuthAndUserAgentHeaders(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['email' => 'a@b.c']);
        $client = $this->makeClient($http);
        $client->profile->retrieve();

        $request = $http->last();
        $this->assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertStringStartsWith('silon-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testDefaultHeadersSent(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, []);
        $client = $this->makeClient($http, ['headers' => ['X-Custom' => 'yes']]);
        $client->profile->retrieve();
        $this->assertSame('yes', $http->last()->getHeaderLine('X-Custom'));
    }

    public function test204ReturnsNull(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(204, '');
        $client = $this->makeClient($http);
        $this->assertNull($client->clients->delete('c1'));
    }

    public function testInvalidJsonRaisesSilonError(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, '<html>not json</html>');
        $client = $this->makeClient($http);
        $this->expectException(SilonException::class);
        $this->expectExceptionMessage('Could not parse response body');
        $client->profile->retrieve();
    }

    public function testGetRequestHasNoBody(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['email' => 'a@b.c']);
        $client = $this->makeClient($http);
        $client->profile->retrieve();
        $this->assertNull($http->last()->body);
        $this->assertSame('GET', $http->last()->method);
    }
}
