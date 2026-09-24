<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Models\Incident;
use App\Models\User;

use function Pest\Laravel\actingAs;

$onlinePayload = [
    'incident_type' => IncidentType::Fire->value,
    'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
    'latitude' => 18.2756,
    'longitude' => 121.6756,
    'location_label' => 'Minanga',
    'priority' => Priority::High->value,
    'contact_number' => '09171234567',
];

$callerPayload = [
    'incident_type' => IncidentType::TyphoonFlood->value,
    'description' => 'Rising floodwater entered the houses along the riverbank quickly.',
    'latitude' => 18.2756,
    'longitude' => 121.6756,
    'location_label' => 'Balogo',
    'priority' => Priority::High->value,
    'caller_name' => 'Maria Santos',
    'caller_contact' => '09179876543',
];

test('an admin submitting a report online has it verified immediately', function () use ($onlinePayload) {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('report.store'), $onlinePayload)
        ->assertRedirect(route('my-reports'))
        ->assertSessionHas('status');

    $incident = Incident::first();

    expect(session('status'))->toContain('verified');
    expect($incident->status)->toBe(IncidentStatus::Verified);
    expect($incident->verified_at)->not->toBeNull();
    expect($incident->statusLogs()->count())->toBe(1);
});

test('an encoder submitting a report online can assign a unit and it is verified', function () use ($onlinePayload) {
    $encoder = User::factory()->encoder()->create();

    actingAs($encoder)
        ->post(route('report.store'), [...$onlinePayload, 'assigned_unit' => 'Rescue 117'])
        ->assertRedirect(route('my-reports'));

    $incident = Incident::first();

    expect(session('status'))->toContain('verified');
    expect($incident->status)->toBe(IncidentStatus::Verified);
    expect($incident->assigned_unit)->toBe('Rescue 117');
    expect($incident->verified_at)->not->toBeNull();
});

test('a community user submitting a report online stays under verification', function () use ($onlinePayload) {
    $user = User::factory()->communityUser()->create();

    actingAs($user)
        ->post(route('report.store'), $onlinePayload)
        ->assertRedirect(route('my-reports'));

    $incident = Incident::first();

    expect(session('status'))->toContain('Our team will verify it shortly');
    expect($incident->status)->toBe(IncidentStatus::UnderVerification);
    expect($incident->verified_at)->toBeNull();
});

test('a community user cannot assign a unit when submitting a report', function () use ($onlinePayload) {
    $user = User::factory()->communityUser()->create();

    actingAs($user)
        ->post(route('report.store'), [...$onlinePayload, 'assigned_unit' => 'Hijacked'])
        ->assertRedirect(route('my-reports'));

    $incident = Incident::first();

    expect($incident->status)->toBe(IncidentStatus::UnderVerification);
    expect($incident->assigned_unit)->toBeNull();
});

test('an encoder encoding a caller report can assign a unit and it is verified', function () use ($callerPayload) {
    $encoder = User::factory()->encoder()->create();

    actingAs($encoder)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.caller.store'), [...$callerPayload, 'assigned_unit' => 'Rescue 117'])
        ->assertRedirect();

    $incident = Incident::first();

    expect(session('status'))->toContain('encoded and verified');
    expect($incident->status)->toBe(IncidentStatus::Verified);
    expect($incident->assigned_unit)->toBe('Rescue 117');
    expect($incident->verified_at)->not->toBeNull();
});

test('the report form shows the assigned unit field to operations roles only', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->communityUser()->create();

    actingAs($admin)->get(route('report.create'))->assertOk()->assertSee('assigned_unit');

    actingAs($user)->get(route('report.create'))->assertOk()->assertDontSee('assigned_unit');
});
