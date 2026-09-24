<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    private const FIELDS = [
        'agency_short_name' => [
            'label' => 'Agency short name',
            'help' => 'Shown in the site header next to the brand.',
            'rules' => ['required', 'string', 'max:120'],
        ],
        'agency_name' => [
            'label' => 'Agency full name',
            'help' => 'Shown in the site footer.',
            'rules' => ['required', 'string', 'max:200'],
        ],
        'municipality' => [
            'label' => 'Municipality',
            'help' => 'Shown in the site footer.',
            'rules' => ['required', 'string', 'max:200'],
        ],
        'hotline' => [
            'label' => 'Emergency hotline',
            'help' => 'Shown in the site footer.',
            'rules' => ['required', 'string', 'max:60'],
        ],
        'website' => [
            'label' => 'Website',
            'help' => 'Shown in the footer and in SMS tracking links.',
            'rules' => ['required', 'string', 'max:120'],
        ],
        'contact_email' => [
            'label' => 'Contact email',
            'help' => 'Optional; shown in the site footer.',
            'rules' => ['nullable', 'email', 'max:120'],
        ],
    ];

    public function index(): View
    {
        return view('dashboard.settings', [
            'fields' => collect(self::FIELDS)->map(fn (array $field, string $key) => [
                ...$field,
                'key' => $key,
                'value' => SiteSetting::value($key),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(self::FIELDS)
            ->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])
            ->all();

        $data = $request->validate($rules);

        foreach (array_keys(self::FIELDS) as $key) {
            SiteSetting::set($key, $data[$key] ?? null);
        }

        return back()->with('status', 'Site information updated and applied.');
    }
}
