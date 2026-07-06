<?php

declare(strict_types=1);

namespace Silon\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Silon\Exception\ApiStatusException;
use Silon\Exception\AuthenticationException;
use Silon\Exception\BadRequestException;
use Silon\Exception\ConflictException;
use Silon\Exception\GoneException;
use Silon\Exception\InternalServerException;
use Silon\Exception\NotFoundException;
use Silon\Exception\PermissionDeniedException;
use Silon\Exception\RateLimitException;
use Silon\Exception\UnprocessableEntityException;

final class ErrorsTest extends TestCase
{
    private const STANDARD_400 = [
        'type' => 'validation_error',
        'errors' => [
            ['code' => 'required', 'detail' => 'This field is required.', 'attr' => 'channel'],
            ['code' => 'invalid', 'detail' => 'Enter a valid value.', 'attr' => 'to'],
        ],
    ];

    private const INLINE_404 = [
        'type' => 'https://acme.silon.tech/docs/errors/not-found',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'No message with that id.',
        'field' => null,
    ];

    private function failing(int $status, array|string $body, array $headers = []): \Silon\Client
    {
        $http = new MockHttpClient();
        $http->pushJson($status, $body, $headers);

        return $this->makeClient($http);
    }

    public function testStandardizedShapeParsed(): void
    {
        try {
            $this->failing(400, self::STANDARD_400)->profile->retrieve();
            $this->fail('expected BadRequestException');
        } catch (BadRequestException $err) {
            $this->assertSame(400, $err->statusCode);
            $this->assertSame('validation_error', $err->errorType);
            $this->assertCount(2, $err->errors);
            $this->assertSame('required', $err->errors[0]->code);
            $this->assertSame('channel', $err->errors[0]->attr);
            $this->assertSame('Enter a valid value.', $err->errors[1]->detail);
            $this->assertStringContainsString('channel: This field is required.', $err->getMessage());
            $this->assertSame(self::STANDARD_400, $err->body);
        }
    }

    public function testInlineProblemShapeParsed(): void
    {
        try {
            $this->failing(404, self::INLINE_404, ['Content-Type' => 'application/problem+json'])
                ->profile->retrieve();
            $this->fail('expected NotFoundException');
        } catch (NotFoundException $err) {
            $this->assertSame(self::INLINE_404['type'], $err->errorType);
            $this->assertSame('not-found', $err->errors[0]->code);
            $this->assertSame('No message with that id.', $err->errors[0]->detail);
            $this->assertSame('No message with that id.', $err->getMessage());
        }
    }

    public function testInlineProblemFieldBecomesAttr(): void
    {
        try {
            $this->failing(422, [
                'type' => 'https://acme.silon.tech/docs/errors/unknown-channel',
                'title' => 'Unknown channel',
                'status' => 422,
                'detail' => "Sending is not supported for channel='banana'.",
                'field' => 'channel',
            ])->profile->retrieve();
            $this->fail('expected UnprocessableEntityException');
        } catch (UnprocessableEntityException $err) {
            $this->assertSame('channel', $err->errors[0]->attr);
            $this->assertSame('unknown-channel', $err->errors[0]->code);
        }
    }

    /**
     * @return list<array{int,class-string}>
     */
    public static function statusCases(): array
    {
        return [
            [400, BadRequestException::class],
            [401, AuthenticationException::class],
            [403, PermissionDeniedException::class],
            [404, NotFoundException::class],
            [409, ConflictException::class],
            [410, GoneException::class],
            [422, UnprocessableEntityException::class],
            [429, RateLimitException::class],
            [500, InternalServerException::class],
            [503, InternalServerException::class],
        ];
    }

    #[DataProvider('statusCases')]
    public function testStatusCodeMapping(int $status, string $expected): void
    {
        try {
            $this->failing($status, ['type' => 'x', 'errors' => []])->profile->retrieve();
            $this->fail('expected ' . $expected);
        } catch (ApiStatusException $err) {
            $this->assertInstanceOf($expected, $err);
            $this->assertSame($status, $err->statusCode);
        }
    }

    public function testUnmapped4xxIsGenericStatusError(): void
    {
        try {
            $this->failing(418, [])->profile->retrieve();
            $this->fail('expected ApiStatusException');
        } catch (ApiStatusException $err) {
            $this->assertSame(ApiStatusException::class, $err::class);
            $this->assertSame(418, $err->statusCode);
        }
    }

    public function testNonJsonBodyFallsBackToHttpMessage(): void
    {
        try {
            $this->failing(502, 'Bad Gateway (apache)')->profile->retrieve();
            $this->fail('expected InternalServerException');
        } catch (InternalServerException $err) {
            $this->assertStringContainsString('HTTP 502', $err->getMessage());
            $this->assertNull($err->body);
        }
    }

    public function testRequestIdCaptured(): void
    {
        try {
            $this->failing(401, [], ['X-Request-Id' => 'req_abc123'])->profile->retrieve();
            $this->fail('expected AuthenticationException');
        } catch (AuthenticationException $err) {
            $this->assertSame('req_abc123', $err->requestId);
        }
    }

    public function testRateLimitRetryAfterHeader(): void
    {
        try {
            $this->failing(429, [], ['Retry-After' => '17'])->profile->retrieve();
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $err) {
            $this->assertSame(17.0, $err->retryAfter);
        }
    }

    public function testRateLimitResetEpoch(): void
    {
        $reset = (string) (time() + 7);
        try {
            $this->failing(429, [], ['RateLimit-Reset' => $reset])->profile->retrieve();
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $err) {
            $this->assertNotNull($err->retryAfter);
            $this->assertGreaterThan(0.0, $err->retryAfter);
            $this->assertLessThanOrEqual(7.5, $err->retryAfter);
        }
    }

    public function testRateLimitWithoutHeaders(): void
    {
        try {
            $this->failing(429, [])->profile->retrieve();
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $err) {
            $this->assertNull($err->retryAfter);
        }
    }

    public function testRetryableTrueOnTransientStandardBody(): void
    {
        try {
            $this->failing(503, ['type' => 'server_error', 'errors' => [], 'retryable' => true])
                ->profile->retrieve();
            $this->fail('expected InternalServerException');
        } catch (InternalServerException $err) {
            $this->assertTrue($err->retryable);
        }
    }

    public function testRetryableFalseOnSemanticProblemBody(): void
    {
        try {
            $this->failing(422, [
                'type' => 'https://acme.silon.tech/docs/errors/recipient-suppressed',
                'title' => 'Recipient suppressed',
                'status' => 422,
                'detail' => 'The recipient is on the suppression list.',
                'field' => 'to',
                'retryable' => false,
            ], ['Content-Type' => 'application/problem+json'])->profile->retrieve();
            $this->fail('expected UnprocessableEntityException');
        } catch (UnprocessableEntityException $err) {
            $this->assertFalse($err->retryable);
        }
    }

    public function testRetryableReadVerbatimNotRecomputed(): void
    {
        try {
            $this->failing(409, [
                'type' => 'https://acme.silon.tech/docs/errors/idempotency-in-flight',
                'title' => 'Idempotency in flight',
                'status' => 409,
                'detail' => 'A request with this Idempotency-Key is still processing.',
                'retryable' => true,
            ], ['Content-Type' => 'application/problem+json'])->profile->retrieve();
            $this->fail('expected ConflictException');
        } catch (ConflictException $err) {
            $this->assertTrue($err->retryable);
        }
    }

    public function testRetryableNullWhenAbsentFromLegacyBody(): void
    {
        try {
            $this->failing(400, self::STANDARD_400)->profile->retrieve();
            $this->fail('expected BadRequestException');
        } catch (BadRequestException $err) {
            $this->assertNull($err->retryable);
        }
    }

    public function testRetryableOnRateLimitError(): void
    {
        try {
            $this->failing(429, [
                'type' => 'https://acme.silon.tech/docs/errors/rate-limited',
                'title' => 'Too many requests',
                'status' => 429,
                'detail' => 'Slow down.',
                'retryable' => true,
            ], ['Content-Type' => 'application/problem+json', 'Retry-After' => '3'])->profile->retrieve();
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $err) {
            $this->assertTrue($err->retryable);
            $this->assertSame(3.0, $err->retryAfter);
        }
    }
}
