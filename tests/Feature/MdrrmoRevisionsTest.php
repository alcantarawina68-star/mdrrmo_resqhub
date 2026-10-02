<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

function validReportPayload(array $overrides = []): array
{
    return array_merge([
        'incident_type' => IncidentType::Fire->value,
        'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
        'latitude' => 18.2756,
        'longitude' => 121.6756,
        'location_label' => 'Minanga',
        'contact_number' => '09171234567',
    ], $overrides);
}

test('the incident status enum exposes only the MDRRMO statuses', function () {
    expect(IncidentStatus::values())->toBe([
        'under_verification',
        'verified',
        'ongoing',
        'closed',
        'rejected',
    ]);
});

test('only verified ongoing and closed incidents are publicly visible', function () {
    expect(IncidentStatus::publiclyVisible())->toBe([
        IncidentStatus::Verified,
        IncidentStatus::Ongoing,
        IncidentStatus::Closed,
    ]);
});

test('the priority column no longer exists on incidents', function () {
    expect(Schema::hasColumn('incidents', 'priority'))->toBeFalse();
});

test('selectable incident types exclude the legacy combined type', function () {
    $selectable = IncidentType::selectableLabels();

    expect($selectable)->not->toHaveKey(IncidentType::TyphoonFlood->value)
        ->and($selectable)->toHaveKey(IncidentType::Typhoon->value)
        ->and($selectable)->toHaveKey(IncidentType::Flood->value)
        ->and($selectable)->toHaveKey(IncidentType::Others->value)
        ->and($selectable)->not->toHaveKey('other');
});

test('legacy combined incident types remain readable for historical records', function () {
    $incident = Incident::factory()->create(['incident_type' => IncidentType::TyphoonFlood]);

    expect($incident->refresh()->incident_type)->toBe(IncidentType::TyphoonFlood)
        ->and(IncidentType::TyphoonFlood->isLegacy())->toBeTrue();
});

test('incident types are grouped by the agreed categories', function () {
    $grouped = IncidentType::grouped();

    expect($grouped)->toHaveKeys(array_keys(IncidentType::CATEGORIES))
        ->and(array_column($grouped, 'label'))
        ->toBe(array_values(IncidentType::CATEGORIES))
        ->and($grouped['disaster_risk']['types'])->toHaveKeys(['typhoon', 'flood', 'earthquake', 'landslide'])
        ->and($grouped['others']['types'])->toHaveKey('others')
        ->and(collect($grouped)->pluck('types')->collapse()->keys())
        ->not->toContain(IncidentType::TyphoonFlood->value);
});

test('an online report is accepted without a classification', function () {
    $user = User::factory()->communityUser()->create();

    actingAs($user)
        ->post(route('report.store'), validReportPayload())
        ->assertSessionHasNoErrors();

    expect(Incident::count())->toBe(1);
});

test('a caller report is accepted without a classification', function () {
    $encoder = User::factory()->encoder()->create();

    actingAs($encoder)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.caller.store'), [
            'incident_type' => IncidentType::Flood->value,
            'description' => 'Rising floodwater entered the houses along the riverbank quickly.',
            'latitude' => 18.2756,
            'longitude' => 121.6756,
            'location_label' => 'Balogo',
            'caller_name' => 'Maria Santos',
            'caller_contact' => '09179876543',
        ])
        ->assertSessionHasNoErrors();

    expect(Incident::count())->toBe(1);
});

test('closing an incident is the only transition that records a resolution time', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}/status", [
        'status' => IncidentStatus::Closed->value,
    ])->assertStatus(200);

    expect($incident->refresh()->resolved_at)->not->toBeNull();
});

test('the public map payload carries status but no classification', function () {
    Incident::factory()->verified()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('status_label', false)
        ->assertDontSee('classification', false)
        ->assertDontSee('priority', false);
});

test('the stylesheet carries no classification marker rings', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->not->toContain('.incident-marker.is-red .shape')
        ->not->toContain('.incident-marker.is-green .shape')
        ->not->toContain('.incident-marker.is-yellow .shape')
        ->not->toContain('.incident-marker.is-black .shape');
});

test('the incident api no longer exposes priority or classification fields', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    $response = actingAs($admin, 'sanctum')->getJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(200);

    expect($response->json('data'))
        ->not->toHaveKey('priority')
        ->not->toHaveKey('priority_label')
        ->not->toHaveKey('classification')
        ->not->toHaveKey('classification_label')
        ->toHaveKey('status');
});

test('the report summary drops the classification breakdown', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->create();

    $response = actingAs($admin, 'sanctum')->getJson('/api/v1/reports/summary')
        ->assertStatus(200);

    expect($response->json('data'))->not->toHaveKey('by_classification');
});

test('screens that used to show a classification no longer mention one', function () {
    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)->get(route('report.create'))->assertDontSee('classification', false);
    $this->actingAs($user)->get(route('home'))->assertDontSee('classification', false);
});
