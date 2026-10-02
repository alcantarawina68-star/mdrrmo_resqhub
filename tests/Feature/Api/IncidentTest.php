<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

$validPayload = [
    'incident_type' => IncidentType::Fire->value,
    'description' => 'A house fire was spotted along the road near the chapel.',
    'latitude' => 18.2756,
    'longitude' => 121.6756,
    'location_label' => 'Minanga',
    'contact_number' => '09171234567',
];

test('a community user can submit an incident online', function () use ($validPayload) {
    $user = User::factory()->communityUser()->create();

    $response = actingAs($user, 'sanctum')->postJson('/api/v1/incidents', $validPayload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', IncidentStatus::UnderVerification->value)
        ->assertJsonPath('data.source', 'online')
        ->assertJsonPath('data.reporter.name', $user->name)
        ->assertJsonStructure(['data' => ['id', 'incident_number']]);

    expect($response->json('data.incident_number'))->toMatch('/^RQ-'.now()->year.'-\d+$/');
    expect(Incident::first()->status)->toBe(IncidentStatus::UnderVerification);
    expect(Incident::first()->statusLogs()->count())->toBe(1);
});

test('incident submission validates input', function () use ($validPayload) {
    $user = User::factory()->create();

    actingAs($user, 'sanctum')
        ->postJson('/api/v1/incidents', [
            ...$validPayload,
            'description' => 'too short',
            'latitude' => 999,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['description', 'latitude']]);
});

test('the public incident list only shows publicly visible statuses', function () {
    Incident::factory()->verified()->create();
    Incident::factory()->ongoing()->create();
    Incident::factory()->underVerification()->create();

    getJson('/api/v1/incidents')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2);
});

test('the public incident list can be filtered and limited', function () {
    Incident::factory()->verified()->create(['incident_type' => IncidentType::Fire]);
    Incident::factory()->verified()->create(['incident_type' => IncidentType::Earthquake]);

    getJson('/api/v1/incidents?incident_type=fire&limit=1')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.incident_type', 'fire');
});

test('a publicly visible incident can be shown', function () {
    $incident = Incident::factory()->ongoing()->create();

    getJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $incident->id)
        ->assertJsonPath('data.status', IncidentStatus::Ongoing->value);
});

test('a non-public incident is hidden from the public API', function () {
    $incident = Incident::factory()->underVerification()->create();

    getJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

test('a user can list their own incidents', function () {
    $user = User::factory()->create();
    Incident::factory()->count(3)->for($user, 'reporter')->create();
    Incident::factory()->create();

    actingAs($user, 'sanctum')->getJson('/api/v1/my/incidents')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 3);
});

test('an encoder can encode a caller-based incident', function () use ($validPayload) {
    $encoder = User::factory()->encoder()->create();

    actingAs($encoder, 'sanctum')
        ->postJson('/api/v1/incidents/caller', [
            ...$validPayload,
            'caller_name' => 'Juan Dela Cruz',
            'caller_contact' => '09179876543',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.source', 'caller_based')
        ->assertJsonPath('data.caller_name', 'Juan Dela Cruz');
});

test('a community user cannot encode caller-based incidents', function () use ($validPayload) {
    $user = User::factory()->communityUser()->create();

    actingAs($user, 'sanctum')
        ->postJson('/api/v1/incidents/caller', $validPayload)
        ->assertStatus(403);
});

test('an operator can approve an incident under verification', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    actingAs($admin, 'sanctum')
        ->postJson("/api/v1/incidents/{$incident->id}/verify", [
            'action' => 'approve',
            'assigned_unit' => 'Rescue 117',
            'notes' => 'Confirmed by barangay captain.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', IncidentStatus::Verified->value)
        ->assertJsonPath('data.assigned_unit', 'Rescue 117');

    $incident->refresh();
    expect($incident->verified_at)->not->toBeNull();
    expect($incident->statusLogs()->count())->toBe(1);
});

test('approving requires an assigned unit', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    actingAs($admin, 'sanctum')
        ->postJson("/api/v1/incidents/{$incident->id}/verify", [
            'action' => 'approve',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['assigned_unit']]);
});

test('an operator can reject an incident', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    actingAs($admin, 'sanctum')
        ->postJson("/api/v1/incidents/{$incident->id}/verify", [
            'action' => 'reject',
            'notes' => 'False alarm.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', IncidentStatus::Rejected->value);

    expect($incident->refresh()->status)->toBe(IncidentStatus::Rejected);
});

test('a verified incident status can be updated to ongoing and closed', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/incidents/{$incident->id}/status", [
            'status' => IncidentStatus::Ongoing->value,
            'note' => 'Rescue team deployed.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', IncidentStatus::Ongoing->value);

    expect($incident->refresh()->resolved_at)->toBeNull();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/incidents/{$incident->id}/status", [
            'status' => IncidentStatus::Closed->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', IncidentStatus::Closed->value);

    expect($incident->refresh()->resolved_at)->not->toBeNull();
});

test('rejection through the status endpoint is blocked', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/incidents/{$incident->id}/status", [
            'status' => IncidentStatus::Rejected->value,
        ])
        ->assertStatus(422);
});

test('an operator can assign a unit to an incident', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/incidents/{$incident->id}/status", [
            'status' => IncidentStatus::Ongoing->value,
            'assigned_unit' => 'Rescue 117',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.assigned_unit', 'Rescue 117');
});

test('an operator can update incident details', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/incidents/{$incident->id}", [
            'description' => 'Updated report with a longer verified description.',
            'location_label' => 'Agusi',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.location_label', 'Agusi');
});

test('only an admin can delete an incident', function () {
    $admin = User::factory()->admin()->create();
    $encoder = User::factory()->encoder()->create();
    $incident = Incident::factory()->verified()->create();

    actingAs($encoder, 'sanctum')
        ->deleteJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(403);

    actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/incidents/{$incident->id}")
        ->assertStatus(200);

    expect(Incident::find($incident->id))->toBeNull();
});

test('barangay filter is validated against the known list', function () {
    Incident::factory()->verified()->create(['location_label' => 'Dugo']);

    getJson('/api/v1/incidents?barangay=Not-A-Barangay')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 0);
});
