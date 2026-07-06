<?php

declare(strict_types=1);

namespace Silon\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Silon\Client;
use Silon\Http\Request;

/**
 * Shared base for the SDK test suite: builds a mock-backed {@see Client} and
 * offers small helpers for reading captured requests and capturing
 * deprecations.
 */
abstract class TestCase extends BaseTestCase
{
    protected const API_KEY = 'sk_live_test';
    protected const BASE_URL = 'https://acme.silon.tech';

    /**
     * @param array<string,mixed> $overrides
     */
    protected function makeClient(MockHttpClient $http, array $overrides = []): Client
    {
        return new Client(array_merge([
            'apiKey' => self::API_KEY,
            'baseUrl' => self::BASE_URL,
            'maxRetries' => 0,
            'httpClient' => $http,
        ], $overrides));
    }

    /**
     * Run `$fn`, returning the `E_USER_DEPRECATED` messages it triggered.
     * Exceptions thrown by `$fn` propagate (the handler is always restored).
     *
     * @return list<string>
     */
    protected function captureDeprecations(callable $fn): array
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $message) use (&$messages): bool {
            if ($errno === E_USER_DEPRECATED) {
                $messages[] = $message;

                return true;
            }

            return false;
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }

    /**
     * The decoded JSON body of a captured request (empty array when no body).
     *
     * @return array<string,mixed>
     */
    protected function body(Request $request): array
    {
        return $request->body === null ? [] : (array) json_decode($request->body, true);
    }

    /**
     * The query params of a captured request URL.
     *
     * @return array<string,mixed>
     */
    protected function query(Request $request): array
    {
        $queryString = parse_url($request->url, PHP_URL_QUERY);
        $params = [];
        if (is_string($queryString)) {
            parse_str($queryString, $params);
        }

        return $params;
    }

    /** The path (no host, no query) of a captured request URL. */
    protected function path(Request $request): string
    {
        return (string) parse_url($request->url, PHP_URL_PATH);
    }
}
