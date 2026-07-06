<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Full template object — create/retrieve/update response.
 *
 * Adds the latest content bodies and `versions`, the ascending list of
 * immutable version numbers a send may pin.
 */
final class TemplateDetail extends Template
{
    /** HTML body (latest version). */
    public string $body = '';

    /** Markdown body (latest version). */
    public string $body_md = '';

    /**
     * All available version numbers, ascending. Pin one on a send via
     * `template: {"slug": ..., "version": N}`.
     *
     * @var list<int>
     */
    public array $versions = [];

    protected static function schema(): array
    {
        return array_merge(parent::schema(), [
            'body' => 'string',
            'body_md' => 'string',
            'versions' => 'mixed',
        ]);
    }
}
