<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Http\Requests\StoreIncidentRequest;
use App\Services\IncidentService;
use App\Support\CamalBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function create(): View
    {
        return view('report.create', [
            'types' => IncidentType::labels(),
            'priorities' => Priority::labels(),
            'barangays' => CamalBarangays::all(),
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $incident = $this->incidents->createOnline($request->user(), $request->validated());

        return redirect()
            ->route('my-reports')
            ->with('status', 'Report received as '.$incident->incident_number.'. Our team will verify it shortly.');
    }

    public function myReports(Request $request): View
    {
        $incidents = $request->user()
            ->incidents()
            ->latest('reported_at')
            ->withCount('evidence')
            ->paginate(10);

        return view('reports.my', compact('incidents'));
    }
}
