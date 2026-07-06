<?php

declare(strict_types=1);

namespace Silon;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Silon\Exception\SilonException;
use Silon\Model\Model;
use Traversable;

/**
 * One page of a cursor-paginated list endpoint (`{results, next, previous}`).
 *
 * A page is iterable, countable, and indexable — iterate it (or index/`count`
 * it) for the items on THIS page, or call {@see autoPaging()} to lazily walk
 * every following page:
 *
 * ```php
 * $page = $client->events->list(['limit' => 50]);
 * foreach ($page as $event) { ... }                 // this page only
 * foreach ($page->autoPaging() as $event) { ... }   // every page
 * ```
 *
 * {@see nextPage()} never follows the opaque `next` URL directly: it extracts
 * only its query params, merges them over the original params, and re-requests
 * the original path against the configured base URL (proxy safety).
 *
 * @template T of Model
 * @implements IteratorAggregate<int,T>
 * @implements ArrayAccess<int,T>
 */
final class CursorPage implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var list<T> The items on this page. */
    public readonly array $results;

    /** The opaque next-page URL, or `null` on the last page. */
    public readonly ?string $next;

    /** The opaque previous-page URL, or `null` on the first page. */
    public readonly ?string $previous;

    /**
     * @param class-string<T>     $itemClass
     * @param array<string,mixed> $params
     * @param array<string,mixed> $data
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $path,
        private readonly array $params,
        private readonly string $itemClass,
        array $data,
    ) {
        $results = [];
        foreach ($data['results'] ?? [] as $item) {
            $results[] = $this->itemClass::from($item);
        }
        $this->results = $results;
        $this->next = $data['next'] ?? null;
        $this->previous = $data['previous'] ?? null;
    }

    /** Whether a following page exists. */
    public function hasNextPage(): bool
    {
        return $this->next !== null && $this->next !== '';
    }

    /**
     * Fetch the next page.
     *
     * @return CursorPage<T>
     * @throws SilonException when called on the last page (check
     *   {@see hasNextPage()} first).
     */
    public function nextPage(): self
    {
        if (!$this->hasNextPage()) {
            throw new SilonException('This page has no next page; check hasNextPage() first.');
        }
        $params = self::mergeNextParams($this->params, (string) $this->next);
        $data = $this->client->get($this->path, $params);

        return new self($this->client, $this->path, $params, $this->itemClass, is_array($data) ? $data : []);
    }

    /**
     * Lazily iterate every item across every following page.
     *
     * @return \Generator<int,T>
     */
    public function autoPaging(): \Generator
    {
        $page = $this;
        while (true) {
            foreach ($page->results as $item) {
                yield $item;
            }
            if (!$page->hasNextPage()) {
                return;
            }
            $page = $page->nextPage();
        }
    }

    /**
     * Merge the query params carried by the opaque `next` URL over ours.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private static function mergeNextParams(array $params, string $nextUrl): array
    {
        $query = parse_url($nextUrl, PHP_URL_QUERY);
        $parsed = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $parsed);
        }

        return array_merge($params, $parsed);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->results[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new SilonException('A CursorPage is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new SilonException('A CursorPage is read-only.');
    }
}
