<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Models\Announcement;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
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
