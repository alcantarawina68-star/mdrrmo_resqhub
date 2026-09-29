<?php

use App\Enums\IncidentClassification;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;

use function Pest\Laravel\actingAs;

function validClassificationPayload(array $overrides = []): array
{
    return array_merge([
        'incident_type' => IncidentType::Fire->value,
        'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
        'latitude' => 18.2756,
        'longitude' => 121.6756,
        'location_label' => 'Minanga',
        'priority' => IncidentClassification::Red->value,
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

test('the classification enum exposes only the MDRRMO classifications', function () {
    expect(IncidentClassification::values())->toBe(['red', 'green', 'yellow', 'black']);
});

test('classification labels carry the agreed colour indicators', function () {
    expect(IncidentClassification::labels())->toBe([
        'red' => '🔴 Red',
        'green' => '🟢 Green',
        'yellow' => '🟡 Yellow',
        'black' => '⚫ Black',
    ]);
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

test('an online report rejects a removed priority value', function () {
    $user = User::factory()->communityUser()->create();

    actingAs($user)
        ->post(route('report.store'), validClassificationPayload(['priority' => 'high']))
        ->assertSessionHasErrors('priority');

    expect(Incident::count())->toBe(0);
});

test('an online report accepts every current classification', function (string $classification) {
    $user = User::factory()->communityUser()->create();

    actingAs($user)
        ->post(route('report.store'), validClassificationPayload(['priority' => $classification]))
        ->assertSessionHasNoErrors();

    expect(Incident::first()->priority)->toBe(IncidentClassification::from($classification));
})->with(IncidentClassification::values());

test('a caller report is stored with the default yellow classification', function () {
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

    expect(Incident::first()->priority)->toBe(IncidentClassification::Yellow);
});

test('closing an incident is the only transition that records a resolution time', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}/status", [
        'status' => IncidentStatus::Closed->value,
    ])->assertStatus(200);

    expect($incident->refresh()->resolved_at)->not->toBeNull();
});

test('the incident api exposes classification aliases alongside priority', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->classification(IncidentClassification::Black)->create();

    actingAs($admin, 'sanctum')->getJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.priority', 'black')
        ->assertJsonPath('data.classification', 'black')
        ->assertJsonPath('data.classification_label', '⚫ Black');
});

test('the incident api filters by the classification alias', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->classification(IncidentClassification::Black)->create();
    Incident::factory()->verified()->classification(IncidentClassification::Green)->create();

    actingAs($admin, 'sanctum')->getJson('/api/v1/incidents?classification=black')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});
