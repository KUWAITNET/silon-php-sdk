<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * A message template list row (`/api/v1/templates/`).
 *
 * This is the light list-row shape; {@see TemplateDetail} (returned by
 * create/retrieve/update) adds the body fields and the version list.
 */
class Template extends Model
{
    /** Unique template identifier — referenced via `template: {"slug": ...}`. */
    public string $slug = '';

    /** Always `template`. */
    public string $object = 'template';

    /** Optional channel hint (e.g. `sms`); `null` for a cross-channel template. */
    public ?string $channel = null;

    /** Subject line (latest version). */
    public string $subject = '';

    /**
     * Latest immutable version number (starts at 1). Every content edit mints
     * N+1; pin an older revision via `template: {"slug": ..., "version": N}`.
     */
    public int $version = 1;

    public ?DateTimeImmutable $created = null;
    public ?DateTimeImmutable $updated = null;

    protected static function schema(): array
    {
        return [
            'slug' => 'string',
            'object' => 'string',
            'channel' => 'string',
            'subject' => 'string',
            'version' => 'int',
            'created' => 'datetime',
            'updated' => 'datetime',
        ];
    }
}
