<?php

use App\Models\Incident;
use App\Models\User;
use App\Services\ReportService;

use function Pest\Laravel\actingAs;

test('the report aggregates honour a barangay filter', function () {
    Incident::factory()->create(['location_label' => 'Minanga', 'reported_at' => now()]);
    Incident::factory()->create(['location_label' => 'Dugo', 'reported_at' => now()]);

    $reports = app(ReportService::class);
    $trend = collect($reports->dailyTrend(30, null, null, 'Minanga'));

    expect($reports->summary(null, null, 'Minanga')['total'])->toBe(1)
        ->and($trend->sum('total'))->toBe(1)
        ->and($reports->barangayBreakdown(null, null, 'Minanga'))->toBe([
            ['barangay' => 'Minanga', 'total' => 1],
        ]);
});

test('an unknown barangay filter is ignored', function () {
    Incident::factory()->count(2)->create();

    expect(app(ReportService::class)->summary(null, null, 'Not A Barangay')['total'])->toBe(2);
});

test('the reports page applies the barangay filter', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['location_label' => 'Minanga', 'reported_at' => now()]);
    Incident::factory()->create(['location_label' => 'Dugo', 'reported_at' => now()]);

    actingAs($admin)
        ->get(route('dashboard.reports', ['barangay' => 'Minanga']))
        ->assertOk()
        ->assertSee('value="Minanga" selected', false)
        ->assertSee('aria-label="Minanga: 1 incidents"', false)
        ->assertDontSee('aria-label="Dugo: 1 incidents"', false);
});

test('the api report endpoints honour a barangay filter', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['location_label' => 'Minanga', 'reported_at' => now()]);
    Incident::factory()->create(['location_label' => 'Dugo', 'reported_at' => now()]);

    actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/summary?barangay=Minanga')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/barangays?barangay=Minanga')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.barangay', 'Minanga');

    actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/summary?barangay=Not%20A%20Barangay')
        ->assertOk()
        ->assertJsonPath('data.total', 2);
});
