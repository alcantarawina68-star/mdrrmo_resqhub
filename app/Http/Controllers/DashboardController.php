<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidents,
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        if ($request->user()->isSuperadmin()) {
            return view('dashboard.index', [
                'analytics' => $this->superadminAnalytics(),
            ]);
        }

        $summary = $this->reports->summary();

        $latest = Incident::query()
            ->with('reporter:id,name')
            ->latest('reported_at')
            ->limit(8)
            ->get();

        $today = Incident::whereDate('reported_at', today())->count();

        return view('dashboard.index', compact('summary', 'latest', 'today'));
    }

    /**
     * Build the user and session analytics shown on the superadmin overview.
     *
     * @return array<string, mixed>
     */
    private function superadminAnalytics(): array
    {
        $statusCounts = User::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $roleCounts = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $activeCutoff = Carbon::now()->subMinutes((int) config('session.lifetime'));

        $onlineForUsers = DB::table('sessions')
            ->select(
                'sessions.user_id',
                'sessions.id as session_id',
                'sessions.ip_address',
                'sessions.last_activity',
                'users.name',
                'users.email',
                'users.role',
            )
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', $activeCutoff->timestamp)
            ->orderByDesc('sessions.last_activity');

        $onlineByRole = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', $activeCutoff->timestamp)
            ->selectRaw('users.role, COUNT(*) as total')
            ->groupBy('users.role')
            ->pluck('total', 'role');

        $onlineUsers = $onlineForUsers->get()->groupBy('user_id')->map->first()->values();

        $sessionBase = DB::table('sessions');

        return [
            'users' => [
                'total' => (int) User::count(),
                'active' => (int) ($statusCounts->get(UserStatus::Active->value) ?? 0),
                'suspended' => (int) ($statusCounts->get(UserStatus::Suspended->value) ?? 0),
                'inactive' => (int) ($statusCounts->get(UserStatus::Inactive->value) ?? 0),
                'new_30_days' => (int) User::where('created_at', '>=', now()->subDays(30))->count(),
                'by_role' => collect(UserRole::cases())
                    ->map(fn (UserRole $role) => [
                        'label' => $role->label(),
                        'value' => $role->value,
                        'total' => (int) ($roleCounts->get($role->value) ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
            'sessions' => [
                'online_now' => (int) $onlineForUsers->count(),
                'sessions_24h' => (int) (clone $sessionBase)->where('last_activity', '>=', now()->subDay()->timestamp)->count(),
                'sessions_7d' => (int) (clone $sessionBase)->where('last_activity', '>=', now()->subDays(7)->timestamp)->count(),
                'online_by_role' => collect(UserRole::cases())
                    ->map(fn (UserRole $role) => [
                        'label' => $role->label(),
                        'value' => $role->value,
                        'total' => (int) ($onlineByRole->get($role->value) ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
            'online_users' => $onlineUsers,
        ];
    }

    public function incidents(Request $request): View
    {
        $query = Incident::query()->with('reporter:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('incident_type', $request->input('type'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('location_label', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        $incidents = $query->latest('reported_at')->paginate(15)->withQueryString();

        return view('dashboard.incidents', [
            'incidents' => $incidents,
            'statuses' => IncidentStatus::labels(),
            'types' => IncidentType::labels(),
            'priorities' => Priority::labels(),
        ]);
    }

    public function show(Incident $incident): View
    {
        $incident->load('reporter:id,name,contact_number,barangay', 'evidence', 'statusLogs.user:id,name');

        return view('dashboard.show', compact('incident'));
    }

    public function notifyEmergencyContact(Request $request, Incident $incident): RedirectResponse
    {
        $phone = $incident->emergencyContactPhone();

        if (! $phone) {
            return back()->with('error', 'No contact number on file for this incident.');
        }

        if ($incident->hasSentContactSms()) {
            return back()->with('status', 'The emergency contact was already notified for this incident.');
        }

        $sent = $this->incidents->resendContactNotification($incident);

        return $sent
            ? back()->with('status', 'SMS sent to the emergency contact ('.$phone.').')
            : back()->with('error', 'SMS delivery to the emergency contact failed.');
    }

    public function verify(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assigned_unit' => ['nullable', 'string', 'max:120', 'required_if:action,approve'],
        ]);

        $this->incidents->processVerification(
            $request->user(),
            $incident,
            $data['action'] === 'approve',
            $data['notes'] ?? null,
            $data['assigned_unit'] ?? null,
        );

        return back()->with('status', 'Incident '.$incident->incident_number.' marked as '.($data['action'] === 'approve' ? 'verified' : 'rejected').'.');
    }

    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', IncidentStatus::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->incidents->updateStatus($request->user(), $incident, IncidentStatus::from($data['status']), $data['note'] ?? null);

        return back()->with('status', 'Incident '.$incident->incident_number.' updated to '.IncidentStatus::from($data['status'])->label().'.');
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'incident_type' => ['required', 'in:'.implode(',', IncidentType::values())],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'in:'.implode(',', Priority::values())],
        ]);

        $this->incidents->updateDetails($request->user(), $incident, $data);

        return back()->with('status', 'Incident details updated.');
    }
}
