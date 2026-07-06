<?php

declare(strict_types=1);

namespace Silon\Tests;

use Silon\Http\HttpClientInterface;
use Silon\Http\Request;
use Silon\Http\Response;
use Silon\Http\TransportException;

/**
 * A queue-driven {@see HttpClientInterface} for offline tests — the PHP
 * equivalent of Guzzle's MockHandler + history middleware.
 *
 * Either queue responses/exceptions in order with {@see push()} /
 * {@see pushJson()} / {@see pushException()}, or install a persistent
 * {@see setHandler()} that answers every request (e.g. keyed by a `cursor`
 * query param). Every request is recorded on {@see $requests}.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<Request> */
    public array $requests = [];

    /** @var list<Response|TransportException|callable(Request):(Response|TransportException)> */
    private array $queue = [];

    /** @var (callable(Request):(Response|TransportException))|null */
    private $handler = null;

    /**
     * Queue a raw response, an exception to throw, or a per-request callable.
     *
     * @param Response|TransportException|callable(Request):(Response|TransportException) $item
     */
    public function push(Response|TransportException|callable $item): void
    {
        $this->queue[] = $item;
    }

    /**
     * Queue a JSON response (an array is encoded; a string is sent verbatim).
     *
     * @param array<string,mixed>|list<mixed>|string $body
     * @param array<string,string> $headers
     */
    public function pushJson(int $status, array|string $body, array $headers = []): void
    {
        $encoded = is_string($body) ? $body : (string) json_encode($body);
        $this->queue[] = new Response($status, $headers, $encoded);
    }

    /** Queue a transport failure (no HTTP response). */
    public function pushException(bool $timeout = false, string $message = 'boom'): void
    {
        $this->queue[] = new TransportException($message, $timeout);
    }

    /**
     * Install a persistent handler that answers every request. Overrides the
     * queue.
     *
     * @param callable(Request):(Response|TransportException) $handler
     */
    public function setHandler(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function send(Request $request, float $timeout): Response
    {
        $this->requests[] = $request;

        if ($this->handler !== null) {
            $result = ($this->handler)($request);
            if ($result instanceof TransportException) {
                throw $result;
            }

            return $result;
        }

        if ($this->queue === []) {
            throw new \RuntimeException(
                'MockHttpClient: no queued response for ' . $request->method . ' ' . $request->url
            );
        }

        $item = array_shift($this->queue);
        if ($item instanceof TransportException) {
            throw $item;
        }
        if (is_callable($item)) {
            $result = $item($request);
            if ($result instanceof TransportException) {
                throw $result;
            }

            return $result;
        }

        return $item;
    }

    /** The most recently captured request. */
    public function last(): Request
    {
        return $this->requests[count($this->requests) - 1];
    }

    /** How many requests have been sent. */
    public function callCount(): int
    {
        return count($this->requests);
    }

    /** Convenience factory for a JSON {@see Response}. */
    public static function jsonResponse(int $status, array|string $body, array $headers = []): Response
    {
        $encoded = is_string($body) ? $body : (string) json_encode($body);

        return new Response($status, $headers, $encoded);
    }
}
