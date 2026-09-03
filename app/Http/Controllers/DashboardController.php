<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Models\Incident;
use App\Services\IncidentService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidents,
        private readonly ReportService $reports,
    ) {}

    public function index(): View
    {
        $summary = $this->reports->summary();

        $latest = Incident::query()
            ->with('reporter:id,name')
            ->latest('reported_at')
            ->limit(8)
            ->get();

        $today = Incident::whereDate('reported_at', today())->count();

        return view('dashboard.index', compact('summary', 'latest', 'today'));
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
