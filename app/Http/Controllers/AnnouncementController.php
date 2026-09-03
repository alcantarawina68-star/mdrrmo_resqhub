<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::query()
            ->with('user:id,name')
            ->latest('published_at')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.announcements', [
            'announcements' => $announcements,
            'categories' => AnnouncementCategory::labels(),
            'severities' => Severity::labels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'in:'.implode(',', AnnouncementCategory::values())],
            'severity' => ['required', 'in:'.implode(',', Severity::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $request->user()->announcements()->create($data);

        return back()->with('status', 'Announcement published.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }
}
