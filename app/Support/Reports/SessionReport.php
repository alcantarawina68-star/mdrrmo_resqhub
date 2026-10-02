<?php

namespace App\Support\Reports;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Reports\Concerns\AppliesReportFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who is signed in, from where, on which device.
 *
 * The raw session id is deliberately left out: it is the identifier in the
 * session cookie, so it does not belong in a file that outlives the screen it
 * was downloaded from.
 */
class SessionReport implements Report
{
    use AppliesReportFilters;

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to'];
    }

    public function title(): string
    {
        return 'Active Sessions Report';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-sessions';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'User',
            'Email',
            'Role',
            'IP Address',
            'User Agent',
            'Last Active',
        ];
    }

    public function emptyMessage(): string
    {
        return 'No sessions found in this period.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator
    {
        $query = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->select(
                'users.name',
                'users.email',
                'users.role',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
            )
            ->orderByDesc('sessions.last_activity');

        // last_activity is a unix timestamp, not a datetime column, so the
        // window has to be compared against integers.
        if (filled($filters['from'] ?? null)) {
            $query->where('sessions.last_activity', '>=', Carbon::parse($filters['from'])->startOfDay()->timestamp);
        }

        if (filled($filters['to'] ?? null)) {
            $query->where('sessions.last_activity', '<=', Carbon::parse($filters['to'])->endOfDay()->timestamp);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $session) {
            yield [
                $this->formatText($session->name),
                $this->formatText($session->email),
                $this->formatText(UserRole::tryFrom($session->role)?->label()),
                $this->formatText($session->ip_address),
                $this->formatText($session->user_agent, 120),
                $this->formatDate(Carbon::createFromTimestamp((int) $session->last_activity), $dateFormat),
            ];
        }
    }
}
