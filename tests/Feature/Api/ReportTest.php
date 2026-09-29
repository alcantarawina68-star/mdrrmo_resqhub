<?php

use App\Enums\IncidentClassification;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;
use App\Services\ReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\actingAs;

test('the summary endpoint returns dashboard metrics', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->underVerification()->create(['incident_type' => IncidentType::Fire, 'priority' => IncidentClassification::Black]);
    Incident::factory()->verified()->create();
    Incident::factory()->ongoing()->create();
    Incident::factory()->closed()->create();

    actingAs($admin, 'sanctum')->getJson('/api/v1/reports/summary')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.under_verification', 1)
        ->assertJsonPath('data.active', 1)
        ->assertJsonPath('data.closed', 1)
        ->assertJsonStructure([
            'data' => [
                'by_status' => [['value', 'label', 'total']],
                'by_type' => [['label', 'types' => [['value', 'label', 'total']]]],
                'by_classification' => [['value', 'label', 'total']],
                'by_source' => [['value', 'label', 'total']],
            ],
        ]);
});

test('the summary endpoint respects the date range', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->create(['reported_at' => now()->subDays(10)]);
    Incident::factory()->verified()->create(['reported_at' => now()]);

    actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/summary?from='.now()->subDays(2)->toDateString().'&to='.now()->toDateString())
        ->assertStatus(200)
        ->assertJsonPath('data.total', 1);
});

test('the trend endpoint returns a daily series', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->create(['reported_at' => now()]);

    actingAs($admin, 'sanctum')->getJson('/api/v1/reports/trend?days=7')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(7, 'data')
        ->assertJsonStructure(['data' => [['date', 'label', 'total']]]);
});

test('the barangay breakdown endpoint returns sorted counts', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->create(['location_label' => 'Dugo']);
    Incident::factory()->verified()->create(['location_label' => 'Dugo']);
    Incident::factory()->verified()->create(['location_label' => 'Agusi']);

    actingAs($admin, 'sanctum')->getJson('/api/v1/reports/barangays')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.barangay', 'Dugo')
        ->assertJsonPath('data.0.total', 2);
});

test('only admins can export the report CSV', function () {
    $admin = User::factory()->admin()->create();
    $encoder = User::factory()->encoder()->create();
    Incident::factory()->verified()->create(['description' => 'First incident for the CSV export test report.']);

    actingAs($encoder, 'sanctum')->getJson('/api/v1/reports/export')
        ->assertStatus(403);

    $response = actingAs($admin, 'sanctum')->get('/api/v1/reports/export');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition');

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
});

test('the CSV export includes a BOM and column headers', function () {
    Incident::factory()->verified()->create([
        'description' => 'A minor flood along the national road for the CSV export.',
        'location_label' => 'Dugo',
    ]);

    $csv = app(ReportService::class)->exportCsv([]);

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();
    expect($csv)->toContain('Incident No.');
    expect($csv)->toContain('Dugo');
    expect($csv)->toContain('RQ-');
});

test('non-operators cannot access report endpoints', function () {
    $user = User::factory()->communityUser()->create();

    actingAs($user, 'sanctum')->getJson('/api/v1/reports/summary')
        ->assertStatus(403);
});
