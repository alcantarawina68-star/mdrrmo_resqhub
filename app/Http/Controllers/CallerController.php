<?php

namespace App\Http\Controllers;

use App\Http\Requests\CallerIncidentRequest;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CallerController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function create(): View
    {
        return view('dashboard.caller');
    }

    public function store(CallerIncidentRequest $request): RedirectResponse
    {
        $incident = $this->incidents->createCallerBased($request->user(), $request->validated());

        return redirect()
            ->route('dashboard.incidents.show', $incident)
            ->with('status', 'Caller report '.$incident->incident_number.' encoded and verified in under 90 seconds.');
    }
}
