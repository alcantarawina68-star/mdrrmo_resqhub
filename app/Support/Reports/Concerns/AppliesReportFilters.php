<?php

namespace App\Support\Reports\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait AppliesReportFilters
{
    /**
     * Narrow a query to the shared from/to window and an optional row cap.
     *
     * The bounds are computed in PHP and handed over as bindings instead of
     * mixing a stored column with NOW(): the connection runs in UTC while the
     * app writes datetimes in the app timezone, so a database-side comparison
     * comes out shifted by the offset.
     */
    protected function withinPeriod(Builder $query, array $filters, string $column, ?int $limit = null): Builder
    {
        if (filled($filters['from'] ?? null)) {
            $query->where($column, '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (filled($filters['to'] ?? null)) {
            $query->where($column, '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * Render a date cell, falling back to the em dash used across the PDFs.
     */
    protected function formatDate(?DateTimeInterface $value, string $format): string
    {
        return $value instanceof DateTimeInterface ? $value->format($format) : '—';
    }

    /**
     * Collapse a nullable text cell onto the same em dash placeholder.
     */
    protected function formatText(?string $value, int $limit = 0): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        return $limit > 0 && mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit - 1).'…'
            : $value;
    }
}
