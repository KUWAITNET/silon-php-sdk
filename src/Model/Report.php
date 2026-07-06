<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Common envelope of every `POST /api/v1/reports/*` endpoint.
 *
 * Row columns in `report_data` vary per report (and per `report_type`), so
 * rows stay plain associative arrays.
 */
final class Report extends Model
{
    /** @var list<array<string,mixed>> */
    public array $report_data = [];

    public int $total_items = 0;
    public int $total_pages = 0;
    public int $page = 1;
    public ?string $report_type = null;

    protected static function schema(): array
    {
        return [
            'report_data' => 'mixed',
            'total_items' => 'int',
            'total_pages' => 'int',
            'page' => 'int',
            'report_type' => 'string',
        ];
    }
}
