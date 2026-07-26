<?php

declare(strict_types=1);

namespace Silon;

use Silon\Exception\ApiConnectionException;
use Silon\Exception\ApiTimeoutException;
use Silon\Exception\ErrorFactory;
use Silon\Exception\SilonException;
use Silon\Http\CurlHttpClient;
use Silon\Http\HttpClientInterface;
use Silon\Http\MultipartBody;
use Silon\Http\Request;
use Silon\Http\Response;
use Silon\Http\TransportException;
use Silon\Resource\Auth;
use Silon\Resource\Broadcasts;
use Silon\Resource\Bulk;
use Silon\Resource\ClientGroups;
use Silon\Resource\Clients;
use Silon\Resource\Events;
use Silon\Resource\Messages;
use Silon\Resource\Otp;
use Silon\Resource\Profile;
use Silon\Resource\Push;
use Silon\Resource\Reports;
use Silon\Resource\Suppressions;
use Silon\Resource\Templates;
use Silon\Resource\Conversations;
use Silon\Resource\PaymentLinks;
use Silon\Resource\WebhookEndpoints;
use Silon\Resource\WhatsAppTemplates;

/**
 * Client for the Silon API.
 *
 * ```php
 * use Silon\Client;
 *
 * $client = new Client(['apiKey' => 'sk_live_...', 'workspace' => 'acme']);
 * $sent = $client->messages->send([
 *     'channel' => 'whatsapp',
 *     'to' => ['client_id' => 'cust_001'],
 *     'content' => ['body' => 'Your order has shipped'],
 * ]);
 * echo $sent->id, ' ', $sent->status;
 * ```
 *
 * Configuration falls back to the `SILON_API_KEY`, `SILON_WORKSPACE` and
 * `SILON_BASE_URL` environment variables; missing required config throws a
 * {@see SilonException} from the constructor.
 */
final class Client
{
    /** Default per-attempt timeout, in seconds. */
    public const DEFAULT_TIMEOUT = 30.0;

    /** Default number of automatic retries after the initial attempt. */
    public const DEFAULT_MAX_RETRIES = 2;

    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'];
    private const MAX_RETRY_DELAY = 30.0;

    /** The resolved API key. */
    public readonly string $apiKey;

    /** The resolved API origin, without a trailing slash. */
    public readonly string $baseUrl;

    /** Per-attempt timeout, in seconds. */
    public readonly float $timeout;

    /** Automatic retries after the initial attempt. */
    public readonly int $maxRetries;

    /** @var array<string,string> */
    private readonly array $defaultHeaders;

    private readonly HttpClientInterface $http;

    /** @var callable(float):void */
    private $sleeper;

    public readonly Messages $messages;
    public readonly Broadcasts $broadcasts;
    public readonly Otp $otp;
    public readonly Clients $clients;
    public readonly ClientGroups $clientGroups;
    public readonly Bulk $bulk;
    public readonly Reports $reports;
    public readonly WhatsAppTemplates $whatsappTemplates;
    public readonly Templates $templates;
    public readonly Conversations $conversations;

    public readonly PaymentLinks $paymentLinks;

    public readonly WebhookEndpoints $webhookEndpoints;
    public readonly Events $events;
    public readonly Suppressions $suppressions;
    public readonly Push $push;
    public readonly Profile $profile;
    public readonly Auth $auth;

    /**
     * @param array{
     *     apiKey?: string,
     *     workspace?: string,
     *     baseUrl?: string,
     *     timeout?: float|int,
     *     maxRetries?: int,
     *     headers?: array<string,string>,
     *     httpClient?: HttpClientInterface,
     *     sleeper?: callable(float):void
     * } $config
     */
    public function __construct(array $config = [])
    {
        $apiKey = $config['apiKey'] ?? self::env('SILON_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            throw new SilonException(
                'No API key provided. Pass ["apiKey" => "..."] or set the SILON_API_KEY '
                . 'environment variable. Create a key in the dashboard under Settings > API keys.'
            );
        }
        $this->apiKey = $apiKey;

        $baseUrl = $config['baseUrl'] ?? self::env('SILON_BASE_URL');
        $workspace = $config['workspace'] ?? self::env('SILON_WORKSPACE');
        if ($baseUrl === null && $workspace !== null && $workspace !== '') {
            $baseUrl = 'https://' . $workspace . '.silon.tech';
        }
        if ($baseUrl === null || $baseUrl === '') {
            throw new SilonException(
                'No base URL. Pass ["workspace" => "<your-workspace>"] (=> '
                . 'https://<workspace>.silon.tech), ["baseUrl" => "..."], or set '
                . 'SILON_WORKSPACE / SILON_BASE_URL.'
            );
        }
        $this->baseUrl = rtrim($baseUrl, '/');

        $this->timeout = (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->maxRetries = (int) ($config['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);
        $this->defaultHeaders = $config['headers'] ?? [];
        $this->http = $config['httpClient'] ?? new CurlHttpClient();
        $this->sleeper = $config['sleeper'] ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };

        $this->messages = new Messages($this);
        $this->broadcasts = new Broadcasts($this);
        $this->otp = new Otp($this);
        $this->clients = new Clients($this);
        $this->clientGroups = new ClientGroups($this);
        $this->bulk = new Bulk($this);
        $this->reports = new Reports($this);
        $this->whatsappTemplates = new WhatsAppTemplates($this);
        $this->templates = new Templates($this);
        $this->conversations = new Conversations($this);
        $this->paymentLinks = new PaymentLinks($this);
        $this->webhookEndpoints = new WebhookEndpoints($this);
        $this->events = new Events($this);
        $this->suppressions = new Suppressions($this);
        $this->push = new Push($this);
        $this->profile = new Profile($this);
        $this->auth = new Auth($this);
    }

    // -- transport ---------------------------------------------------------

    /**
     * Perform a request with automatic retries, returning the decoded JSON
     * body (or `null` for a 204/empty response).
     *
     * @param array{
     *     query?: array<string,mixed>,
     *     json?: mixed,
     *     multipart?: list<array<string,mixed>>,
     *     headers?: array<string,string>
     * } $options
     */
    public function request(string $method, string $path, array $options = []): mixed
    {
        $method = strtoupper($method);
        $headers = $this->buildHeaders($options['headers'] ?? []);

        $url = $this->baseUrl . $path;
        if (!empty($options['query'])) {
            $query = http_build_query(Util::dropNull($options['query']), '', '&', PHP_QUERY_RFC3986);
            if ($query !== '') {
                $url .= '?' . $query;
            }
        }

        $body = null;
        if (array_key_exists('json', $options) && $options['json'] !== null) {
            $body = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        } elseif (!empty($options['multipart'])) {
            [$body, $contentType] = MultipartBody::build($options['multipart']);
            $headers['Content-Type'] = $contentType;
        }

        $request = new Request($method, $url, $headers, $body);

        $attempt = 0;
        while (true) {
            try {
                $response = $this->http->send($request, $this->timeout);
            } catch (TransportException $exc) {
                if ($this->shouldRetry($method, $headers, $attempt)) {
                    ($this->sleeper)($this->retryDelay(null, $attempt));
                    $attempt++;
                    continue;
                }
                if ($exc->isTimeout) {
                    throw new ApiTimeoutException(previous: $exc);
                }
                throw new ApiConnectionException(
                    "Connection error while requesting {$path}: " . $exc->getMessage(),
                    $exc,
                );
            }

            if ($response->getStatusCode() >= 400) {
                if ($this->shouldRetry($method, $headers, $attempt, $response)) {
                    ($this->sleeper)($this->retryDelay($response, $attempt));
                    $attempt++;
                    continue;
                }
                throw ErrorFactory::fromResponse($response);
            }

            return $this->parse($response);
        }
    }

    /**
     * @param array<string,mixed> $params
     */
    public function get(string $path, array $params = []): mixed
    {
        return $this->request('GET', $path, ['query' => $params]);
    }

    /**
     * @param array{query?: array<string,mixed>, json?: mixed, multipart?: list<array<string,mixed>>, headers?: array<string,string>} $options
     */
    public function post(string $path, array $options = []): mixed
    {
        return $this->request('POST', $path, $options);
    }

    public function patch(string $path, mixed $json = null): mixed
    {
        return $this->request('PATCH', $path, ['json' => $json]);
    }

    public function put(string $path, mixed $json = null): mixed
    {
        return $this->request('PUT', $path, ['json' => $json]);
    }

    public function delete(string $path): mixed
    {
        return $this->request('DELETE', $path);
    }

    // -- internals ---------------------------------------------------------

    /**
     * @param array<string,string> $extra
     * @return array<string,string>
     */
    private function buildHeaders(array $extra): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'silon-php/' . Version::VERSION . ' php/' . PHP_VERSION,
        ];

        return array_merge($headers, $this->defaultHeaders, $extra);
    }

    /**
     * @param array<string,string> $headers
     */
    private function shouldRetry(
        string $method,
        array $headers,
        int $attempt,
        ?Response $response = null,
    ): bool {
        if ($attempt >= $this->maxRetries) {
            return false;
        }
        // POST/PATCH are only replayed when the request carries an
        // Idempotency-Key, so a retry can never double-send.
        if (!in_array($method, self::IDEMPOTENT_METHODS, true) && !isset($headers['Idempotency-Key'])) {
            return false;
        }

        return $response === null || in_array($response->getStatusCode(), self::RETRYABLE_STATUSES, true);
    }

    private function retryDelay(?Response $response, int $attempt): float
    {
        $delay = min(0.5 * (2 ** $attempt), 8.0) + (mt_rand(0, 250) / 1000);
        if ($response !== null) {
            $advertised = Util::parseRetryAfter($response);
            if ($advertised !== null) {
                $delay = max($delay, $advertised);
            }
        }

        return max(0.0, min($delay, self::MAX_RETRY_DELAY));
    }

    private function parse(Response $response): mixed
    {
        $body = $response->getBody();
        if ($response->getStatusCode() === 204 || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new SilonException(
                'Could not parse response body as JSON (HTTP ' . $response->getStatusCode() . ').'
            );
        }

        return $decoded;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
