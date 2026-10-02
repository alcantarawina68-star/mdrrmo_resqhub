<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreIncidentRequest;
use App\Services\IncidentService;
use App\Support\CamalBarangays;
use App\Support\Reports\MyIncidentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function create(): View
    {
        return view('report.create', [
            'barangays' => CamalBarangays::all(),
            'canAssignUnit' => auth()->user()->hasRole(...UserRole::operationsRoles()),
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $incident = $this->incidents->createOnline($request->user(), $request->validated());

        $message = $request->user()->hasRole(...UserRole::operationsRoles())
            ? 'Report '.$incident->incident_number.' submitted and verified.'
            : 'Report received as '.$incident->incident_number.'. Our team will verify it shortly.';

        return redirect()
            ->route('my-reports')
            ->with('status', $message);
    }

    public function myReports(Request $request): View
    {
        // The same query the export uses, so the list and the download can never
        // disagree about which reports are in scope.
        $incidents = (new MyIncidentReport)
            ->query($request->only(MyIncidentReport::filters()), $request->user())
            ->paginate(10)
            ->withQueryString();

        return view('reports.my', compact('incidents'));
    }
}
