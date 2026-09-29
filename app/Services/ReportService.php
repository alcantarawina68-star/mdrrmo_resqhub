<?php

namespace App\Services;

use App\Enums\IncidentClassification;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Build the operations dashboard summary.
     *
     * @return array<string, mixed>
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $base = Incident::query();

        if ($from) {
            $base->where('reported_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $base->where('reported_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $byStatus = $base->clone()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $avgResponseMinutes = $base->clone()
            ->whereNotNull('verified_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, reported_at, verified_at)) as avg_minutes')
            ->value('avg_minutes');

        return [
            'total' => $base->clone()->count(),
            'under_verification' => $byStatus->get(IncidentStatus::UnderVerification->value, 0),
            'active' => $byStatus->get(IncidentStatus::Ongoing->value, 0),
            'closed' => $byStatus->get(IncidentStatus::Closed->value, 0),
            'by_status' => $this->labelsWithCounts($byStatus, IncidentStatus::labels()),
            'by_type' => $this->groupedTypeCounts(
                $base->clone()->selectRaw('incident_type, COUNT(*) as total')->groupBy('incident_type')->pluck('total', 'incident_type'),
            ),
            'by_classification' => $this->labelsWithCounts(
                $base->clone()->selectRaw('priority, COUNT(*) as total')->groupBy('priority')->pluck('total', 'priority'),
                IncidentClassification::labels(),
            ),
            'by_source' => $this->labelsWithCounts(
                $base->clone()->selectRaw('source, COUNT(*) as total')->groupBy('source')->pluck('total', 'source'),
                IncidentSource::labels(),
            ),
            'average_response_minutes' => $avgResponseMinutes ? (int) round((float) $avgResponseMinutes) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dailyTrend(int $days = 30, ?string $from = null, ?string $to = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->subDays($days - 1)->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $rows = Incident::query()
            ->whereBetween('reported_at', [$start, $end])
            ->selectRaw('DATE(reported_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'label' => $date->format('M d'),
                'total' => (int) ($rows->get($key) ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return array<string, mixed>
     */
    public function barangayBreakdown(?string $from = null, ?string $to = null): array
    {
        $query = Incident::query();

        if ($from) {
            $query->where('reported_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('reported_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query
            ->whereNotNull('location_label')
            ->selectRaw('location_label, COUNT(*) as total')
            ->groupBy('location_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['barangay' => $row->location_label, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * Build a CSV export of incidents matching the given filters.
     */
    public function exportCsv(array $filters): string
    {
        $rows = $this->filteredIncidents($filters);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Incident No.', 'Type', 'Classification', 'Status', 'Location', 'Barangay', 'Source',
            'Reporter', 'Reported At', 'Verified At', 'Resolved At',
        ]);

        foreach ($rows as $incident) {
            fputcsv($handle, [
                $incident->incident_number,
                $incident->incident_type?->label(),
                $incident->priority?->label(),
                $incident->status?->label(),
                $incident->location_label,
                $incident->location_label,
                $incident->source?->label(),
                $incident->is_anonymous ? 'Anonymous' : $incident->reporter?->name,
                $incident->reported_at?->toDateTimeString(),
                $incident->verified_at?->toDateTimeString(),
                $incident->resolved_at?->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Build a PDF export of incidents matching the given filters.
     *
     * @param  array<int|string, mixed>  $filters
     */
    public function exportPdf(array $filters): \Barryvdh\DomPDF\PDF
    {
        $rows = $this->filteredIncidents($filters);

        return Pdf::loadView('dashboard.reports-pdf', [
            'incidents' => $rows,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'generatedAt' => now(),
        ]);
    }

    /**
     * Return incidents matching the given export filters.
     *
     * @param  array<int|string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, Incident>
     */
    private function filteredIncidents(array $filters)
    {
        $query = Incident::query()->with('reporter:id,name');

        foreach ($filters as $column => $value) {
            if (blank($value)) {
                continue;
            }

            if ($column === 'from') {
                $query->where('reported_at', '>=', Carbon::parse($value)->startOfDay());

                continue;
            }

            if ($column === 'to') {
                $query->where('reported_at', '<=', Carbon::parse($value)->endOfDay());

                continue;
            }

            $query->where($column, $value);
        }

        return $query->orderByDesc('reported_at')->get();
    }

    /**
     * Incident type counts nested under their main category, in display order.
     *
     * Legacy types are included so historical incidents still contribute to
     * their category total, even though they can no longer be selected.
     *
     * @param  Collection<int|string, mixed>  $counts
     * @return array<int, array{label: string, types: array<int, array{value: string, label: string, total: int}>}>
     */
    private function groupedTypeCounts(Collection $counts): array
    {
        $grouped = [];

        foreach (IncidentType::CATEGORIES as $category => $categoryLabel) {
            $types = [];

            foreach (IncidentType::cases() as $case) {
                if ($case->category() !== $category) {
                    continue;
                }

                $types[] = [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'total' => (int) ($counts->get($case->value) ?? 0),
                ];
            }

            $grouped[] = [
                'label' => $categoryLabel,
                'types' => $types,
            ];
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, mixed>  $counts
     * @return array<int, array{value: string, label: string, total: int}>
     */
    private function labelsWithCounts($counts, array $labels): array
    {
        return collect($labels)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'total' => (int) ($counts->get($value) ?? 0),
            ])
            ->values()
            ->all();
    }
}
