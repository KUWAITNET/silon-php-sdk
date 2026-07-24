<?php

declare(strict_types=1);

namespace Silon\Model;

/**
 * Body of `GET /api/v1/reports/conversations/` — support-desk metrics.
 *
 * First-response / resolution / reply times, CSAT, and open / unassigned /
 * unattended gauges, with an agent / channel / team / label breakdown.
 * `totals` and `rows` stay plain associative arrays (columns vary by
 * `group_by`); `previous_totals` / `deltas` are present only in `compare` mode.
 */
final class ConversationsReport extends Model
{
    public string $group_by = 'agent';
    public bool $business_hours = false;
    public ?string $date_from = null;
    public ?string $date_to = null;

    /** @var array<string,mixed> */
    public array $totals = [];

    /** @var list<array<string,mixed>> */
    public array $rows = [];

    /** @var array<string,mixed>|null */
    public ?array $previous_totals = null;

    /** @var array<string,mixed>|null */
    public ?array $deltas = null;

    protected static function schema(): array
    {
        return [
            'group_by' => 'string',
            'business_hours' => 'bool',
            'date_from' => 'string',
            'date_to' => 'string',
            'totals' => 'mixed',
            'rows' => 'mixed',
            'previous_totals' => 'mixed',
            'deltas' => 'mixed',
        ];
    }
}
