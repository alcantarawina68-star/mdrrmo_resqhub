<?php

namespace App\Services;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations behind the operations dashboard and the Reports & Analytics
 * page. Exporting those figures to CSV or PDF is ReportExporter's job, which
 * keeps this service free of any response handling.
 */
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
     * Open incidents per assigned unit, so workload is visible before work is
     * reassigned. Anything still unassigned is appended and flagged.
     *
     * @return array<int, array{label: string, total: int, tone: string}>
     */
    public function assignedUnitWorkload(int $limit = 6): array
    {
        $open = Incident::query()
            ->whereIn('status', [
                IncidentStatus::UnderVerification->value,
                IncidentStatus::Ongoing->value,
            ]);

        $assigned = $open->clone()
            ->whereNotNull('assigned_unit')
            ->where('assigned_unit', '!=', '')
            ->selectRaw('assigned_unit, COUNT(*) as total')
            ->groupBy('assigned_unit')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->assigned_unit,
                'total' => (int) $row->total,
                'tone' => 'primary',
            ])
            ->all();

        $unassigned = $open->clone()
            ->where(fn (Builder $query) => $query->whereNull('assigned_unit')->orWhere('assigned_unit', ''))
            ->count();

        if ($unassigned > 0) {
            $assigned[] = [
                'label' => 'Unassigned',
                'total' => (int) $unassigned,
                'tone' => 'danger',
            ];
        }

        return $assigned;
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
