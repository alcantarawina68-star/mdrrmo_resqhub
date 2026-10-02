<?php

namespace App\Support\Reports;

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use App\Models\Announcement;
use App\Models\User;
use App\Support\Reports\Concerns\AppliesReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public advisory log: what was announced, how urgent it was, and whether
 * it has since expired.
 */
class AnnouncementReport implements Report
{
    use AppliesReportFilters;

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to', 'category', 'severity'];
    }

    public function title(): string
    {
        return 'Announcements Report';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-announcements';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Title',
            'Category',
            'Severity',
            'Published At',
            'Expires At',
            'Expired',
            'Author',
            'Created At',
        ];
    }

    public function emptyMessage(): string
    {
        return 'No announcements found in this period.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator
    {
        $query = $this->baseQuery($filters, $limit);

        foreach ($query->cursor() as $announcement) {
            yield [
                $this->formatText($announcement->title),
                $announcement->category?->label(),
                $announcement->severity?->label(),
                $this->formatDate($announcement->published_at, $dateFormat),
                $this->formatDate($announcement->expires_at, $dateFormat),
                $this->expiry($announcement),
                $this->formatText($announcement->user?->name),
                $this->formatDate($announcement->created_at, $dateFormat),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters, ?int $limit = null): Builder
    {
        $query = Announcement::query()
            ->with('user:id,name')
            ->orderByDesc('published_at');

        if (filled($filters['category'] ?? null) && in_array($filters['category'], AnnouncementCategory::values(), true)) {
            $query->where('category', $filters['category']);
        }

        if (filled($filters['severity'] ?? null) && in_array($filters['severity'], Severity::values(), true)) {
            $query->where('severity', $filters['severity']);
        }

        return $this->withinPeriod($query, $filters, 'published_at', $limit);
    }

    private function expiry(Announcement $announcement): string
    {
        if ($announcement->expires_at === null) {
            return 'No expiry';
        }

        return $announcement->expires_at->isPast() ? 'Yes' : 'No';
    }
}
