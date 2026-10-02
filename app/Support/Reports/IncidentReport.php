<?php

namespace App\Support\Reports;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;
use App\Support\Reports\Concerns\AppliesReportFilters;
use Illuminate\Database\Eloquent\Builder;

class IncidentReport implements Report
{
    use AppliesReportFilters;

    /**
     * The only query-string keys this report understands. Controllers narrow
     * the request with it, so an unknown key can never reach the where clause
     * as a column name.
     *
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to', 'status', 'type', 'source', 'search'];
    }

    public function title(): string
    {
        return 'Incident Report';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-incidents';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Incident No.',
            'Type',
            'Status',
            'Location',
            'Source',
            'Assigned Unit',
            'Reporter',
            'Reported At',
            'Verified At',
            'Resolved At',
            'Evidence',
        ];
    }

    public function emptyMessage(): string
    {
        return 'No incidents found in this period.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator
    {
        foreach ($this->baseQuery($filters, $viewer, $limit)->cursor() as $incident) {
            yield [
                $incident->incident_number,
                $incident->incident_type?->label(),
                $incident->status?->label(),
                $this->formatText($incident->location_label),
                $incident->source?->label(),
                $this->formatText($incident->assigned_unit),
                $incident->is_anonymous ? 'Anonymous' : $this->formatText($incident->reporter?->name),
                $this->formatDate($incident->reported_at, $dateFormat),
                $this->formatDate($incident->verified_at, $dateFormat),
                $this->formatDate($incident->resolved_at, $dateFormat),
                (string) $incident->evidence_count,
            ];
        }
    }

    /**
     * The filtered query behind both the on-screen list and the exports, so the
     * two can never disagree about which rows are in scope. Exports call rows(),
     * which layers the row cap on top of this.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters, ?User $viewer = null): Builder
    {
        return $this->baseQuery($filters, $viewer);
    }

    /**
     * The filtered incident query shared by every entry point. Subclasses narrow
     * it further.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters, ?User $viewer, ?int $limit = null): Builder
    {
        $query = Incident::query()
            ->with('reporter:id,name')
            ->withCount('evidence')
            ->orderByDesc('reported_at');

        if (filled($filters['status'] ?? null) && in_array($filters['status'], IncidentStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['type'] ?? null) && in_array($filters['type'], IncidentType::allValues(), true)) {
            $query->where('incident_type', $filters['type']);
        }

        if (filled($filters['source'] ?? null) && in_array($filters['source'], IncidentSource::values(), true)) {
            $query->where('source', $filters['source']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];

            $query->where(fn (Builder $query) => $query
                ->where('description', 'like', "%{$search}%")
                ->orWhere('location_label', 'like', "%{$search}%")
                ->orWhere('id', $search));
        }

        return $this->withinPeriod($query, $filters, 'reported_at', $limit);
    }
}
