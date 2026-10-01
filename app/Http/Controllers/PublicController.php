<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Announcement;
use App\Models\Incident;
use App\Models\SiteSetting;
use App\Support\CamalBarangays;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function map(): View
    {
        $incidents = Incident::query()
            ->publiclyVisible()
            ->latest('reported_at')
            ->get(['id', 'incident_type', 'description', 'latitude', 'longitude', 'location_label', 'status', 'reported_at'])
            ->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'incident_number' => $incident->incident_number,
                'incident_type' => $incident->incident_type->value,
                'incident_type_label' => $incident->incident_type->label(),
                'description' => $incident->description,
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
                'location_label' => $incident->location_label,
                'status' => $incident->status->value,
                'status_label' => $incident->status->label(),
            ])
            ->values()
            ->all();

        return view('map', [
            'incidents' => $incidents,
            'types' => IncidentType::grouped(),
            'statuses' => IncidentStatus::labels(),
            'barangays' => CamalBarangays::all(),
            'hotline' => SiteSetting::value('hotline'),
        ]);
    }

    public function advisories(): View
    {
        $announcements = Announcement::query()
            ->currentlyActive()
            ->with('user:id,name')
            ->orderByDesc('published_at')
            ->get();

        return view('advisories', compact('announcements'));
    }

    public function show(Request $request, Incident $incident): View
    {
        if (! in_array($incident->status, IncidentStatus::publiclyVisible(), true)) {
            abort(404);
        }

        $incident->load('reporter:id,name', 'evidence');

        return view('incidents.show', compact('incident'));
    }
}
