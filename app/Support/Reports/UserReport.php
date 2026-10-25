<?php

namespace App\Support\Reports;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Reports\Concerns\AppliesReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The account roster. Deliberately carries no password hash, session id, or
 * remember token: an export is a file that outlives the screen it came from.
 */
class UserReport implements Report
{
    use AppliesReportFilters;

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to', 'role', 'status', 'search'];
    }

    public function title(): string
    {
        return 'User Roster Report';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-users';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Role',
            'Status',
            'Barangay',
            'Contact Number',
            'Incidents Submitted',
            'Created At',
            'Last Active',
        ];
    }

    public function emptyMessage(): string
    {
        return 'No users found in this period.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator
    {
        $query = $this->baseQuery($filters, $viewer, $limit);

        foreach ($query->cursor() as $user) {
            yield [
                (string) $user->id,
                $this->formatText($user->name),
                $this->formatText($user->email),
                $user->role?->label(),
                $user->status?->label(),
                $this->formatText($user->barangay),
                $this->formatText($user->contact_number),
                (string) $user->incidents_count,
                $this->formatDate($user->created_at, $dateFormat),
                $this->lastActive($user->last_active, $dateFormat),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters, ?User $viewer = null, ?int $limit = null): Builder
    {
        $query = User::query()->withCount('incidents')->orderBy('name');

        // Mirror the roster page: an admin never sees a superadmin row there,
        // so the export must not hand them one either.
        if (! $viewer?->isSuperadmin()) {
            $query->where('role', '!=', UserRole::Superadmin->value);
        }

        // The most recent session row, so "last active" reads the same way the
        // roster page already shows it.
        $query->addSelect(['last_active' => DB::table('sessions')
            ->select('last_activity')
            ->whereColumn('sessions.user_id', 'users.id')
            ->orderByDesc('last_activity')
            ->limit(1)]);

        if (filled($filters['role'] ?? null) && in_array($filters['role'], UserRole::values(), true)) {
            $query->where('role', $filters['role']);
        }

        if (filled($filters['status'] ?? null) && in_array($filters['status'], UserStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];

            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return $this->withinPeriod($query, $filters, 'created_at', $limit);
    }

    private function lastActive(mixed $timestamp, string $dateFormat): string
    {
        return is_numeric($timestamp)
            ? $this->formatDate(Carbon::createFromTimestamp((int) $timestamp), $dateFormat)
            : '—';
    }
}
