<?php

namespace App\Support\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A community user's own submissions. Same rows and columns as the operations
 * incident report, hard-scoped to the viewer: there is no filter that can widen
 * it, because the route is reachable by every authenticated user.
 */
class MyIncidentReport extends IncidentReport
{
    public function title(): string
    {
        return 'My Reports Record';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-my-reports';
    }

    public function emptyMessage(): string
    {
        return 'You have not submitted any reports in this period.';
    }

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to', 'status'];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters, ?User $viewer, ?int $limit = null): Builder
    {
        $query = parent::baseQuery($filters, $viewer, $limit);

        if ($viewer === null) {
            // Scoping on a null id would match incidents that were never
            // attributed to anyone, so an unauthenticated call gets nothing.
            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $viewer->id);
    }
}
