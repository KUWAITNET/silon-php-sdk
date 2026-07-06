<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Model\SignupResult;
use Silon\Model\UserProfile;

final class AccountTest extends TestCase
{
    private const PROFILE = '/api/v1/profile/';

    private const PROFILE_BODY = [
        'email' => 'sara@example.com', 'first_name' => 'Sara', 'last_name' => 'Ahmad',
        'phone_number' => '+96512345678', 'civil_id' => '', 'default_language' => 'en', 'client_id' => 'KMS42',
    ];

    public function testProfileRetrieve(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::PROFILE_BODY);
        $profile = $this->makeClient($http)->profile->retrieve();
        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertSame('KMS42', $profile->client_id);
    }

    public function testProfileUpdatePatch(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, [...self::PROFILE_BODY, 'first_name' => 'Noor']);
        $updated = $this->makeClient($http)->profile->update(['first_name' => 'Noor']);
        $this->assertSame('Noor', $updated->first_name);
        $this->assertSame('PATCH', $http->last()->method);
        $this->assertEquals(['first_name' => 'Noor'], $this->body($http->last()));
    }

    public function testProfileReplacePut(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, self::PROFILE_BODY);
        $this->makeClient($http)->profile->replace([
            'email' => 'sara@example.com', 'first_name' => 'Sara', 'last_name' => 'Ahmad', 'phone_number' => '+96512345678',
        ]);
        $this->assertSame('PUT', $http->last()->method);
    }

    public function testSignup(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(201, self::PROFILE_BODY);
        $result = $this->makeClient($http)->auth->signup([
            'email' => 'sara@example.com', 'first_name' => 'Sara', 'last_name' => 'Ahmad',
            'phone_number' => '+96512345678', 'password' => 's3cret!pass',
        ]);
        $this->assertInstanceOf(SignupResult::class, $result);
        $body = $this->body($http->last());
        $this->assertSame('s3cret!pass', $body['password']);
        $this->assertArrayNotHasKey('civil_id', $body);
    }

    public function testLoginIsDeprecatedButWorks(): void
    {
        $http = new MockHttpClient();
        $http->pushJson(200, ['token' => 'tok_123']);
        $result = null;
        $deprecations = $this->captureDeprecations(function () use ($http, &$result) {
            $result = $this->makeClient($http)->auth->login(['username' => 'sara@example.com', 'password' => 'pw']);
        });
        $this->assertNotEmpty($deprecations);
        $this->assertStringContainsString('sk_live_', $deprecations[0]);
        $this->assertSame('tok_123', $result->token);
    }
}
