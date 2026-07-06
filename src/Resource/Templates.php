<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\CursorPage;
use Silon\Model\Template;
use Silon\Model\TemplateDetail;
use Silon\Util;

/**
 * `$client->templates` — slug-keyed message templates with an immutable
 * version spine (`/api/v1/templates/`).
 *
 * Every content edit (`subject` / `body` / `body_md`) mints an immutable
 * version N+1; a send may pin an older revision via
 * `template: {"slug": ..., "version": N}`. {@see delete()} is a soft archive.
 */
final class Templates extends Resource
{
    private const PATH = '/api/v1/templates/';

    /**
     * List templates, newest first (cursor-paginated).
     *
     * @param array<string,mixed> $params channel, q (slug-prefix), cursor, limit
     * @return CursorPage<Template>
     */
    public function list(array $params = []): CursorPage
    {
        $query = Util::dropNull($params);
        $data = $this->client->get(self::PATH, $query);

        return new CursorPage($this->client, self::PATH, $query, Template::class, $data);
    }

    /**
     * Create a template at version 1 (`POST /api/v1/templates/`). A duplicate
     * slug (including an archived one) raises
     * {@see \Silon\Exception\ConflictException} (409 `template-exists`).
     *
     * @param array<string,mixed> $params slug (required), channel, subject, body, body_md
     */
    public function create(array $params): TemplateDetail
    {
        $data = $this->client->post(self::PATH, ['json' => Util::dropNull($params)]);

        return new TemplateDetail($data);
    }

    /**
     * Fetch a template — latest content plus its `versions` list. An unknown
     * or archived slug raises {@see \Silon\Exception\NotFoundException}.
     */
    public function retrieve(string $slug): TemplateDetail
    {
        $data = $this->client->get(self::PATH . rawurlencode($slug) . '/');

        return new TemplateDetail($data);
    }

    /**
     * Update a template (`PATCH /api/v1/templates/{slug}/`). Changing any
     * content field mints version N+1; `channel` is metadata and never bumps.
     *
     * @param array<string,mixed> $params >=1 of channel, subject, body, body_md
     */
    public function update(string $slug, array $params): TemplateDetail
    {
        $data = $this->client->patch(self::PATH . rawurlencode($slug) . '/', Util::dropNull($params));

        return new TemplateDetail($data);
    }

    /**
     * Archive a template (`DELETE /api/v1/templates/{slug}/` -> 204). Soft
     * delete: the slug stays reserved and history survives, but the template
     * reads as missing everywhere afterwards.
     */
    public function delete(string $slug): void
    {
        $this->client->delete(self::PATH . rawurlencode($slug) . '/');
    }
}
