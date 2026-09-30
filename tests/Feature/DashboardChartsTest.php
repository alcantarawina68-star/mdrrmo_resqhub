<?php

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('operations roles see the assigned unit workload chart', function (UserRole $role) {
    Incident::factory()->underVerification()->assignedTo('Fire Rescue')->create();
    Incident::factory()->ongoing()->create(['assigned_unit' => null]);

    actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Open incidents by assigned unit')
        ->assertSee('Fire Rescue')
        ->assertSee('Unassigned');
})->with([UserRole::Admin, UserRole::Encoder]);

test('field roles see the shared charts but not the operations-only ones', function (UserRole $role) {
    Incident::factory()->underVerification()->create();

    actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Incidents reported')
        ->assertSee('By incident type')
        ->assertSee('By source')
        ->assertSee('Top barangays')
        ->assertDontSee('Open incidents by assigned unit');
})->with([UserRole::BarangayOfficial, UserRole::Responder]);

test('every dashboard role still sees the same global incident totals', function (UserRole $role) {
    Incident::factory()->ongoing()->count(3)->create();

    actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Incidents reported')
        ->assertDontSee('No incidents reported yet.');
})->with([UserRole::Admin, UserRole::Encoder, UserRole::BarangayOfficial, UserRole::Responder]);

test('assigned unit workload counts only open incidents and flags unassigned ones', function () {
    Incident::factory()->ongoing()->assignedTo('MDRRMO')->count(2)->create();
    Incident::factory()->underVerification()->assignedTo('MDRRMO')->create();
    Incident::factory()->closed()->assignedTo('MDRRMO')->create();
    Incident::factory()->ongoing()->create(['assigned_unit' => null]);

    actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('MDRRMO: 3 open incidents', false)
        ->assertSee('Unassigned: 1 open incidents', false);
});

test('the unit workload chart is hidden when nothing is open', function () {
    Incident::factory()->closed()->count(2)->create();

    actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Open incidents by assigned unit')
        ->assertSee('No open incidents right now.');
});

test('the superadmin overview charts signups and the role mix', function () {
    User::factory()->count(2)->create();

    actingAs(User::factory()->superadmin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Signups (last 30 days)')
        ->assertSee('Role mix')
        ->assertSee('Community User');
});

test('the charts render above the latest incidents and status panels', function () {
    Incident::factory()->ongoing()->create();

    $content = actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    $trend = strpos($content, 'Incidents reported');
    $latest = strpos($content, 'Latest incidents');
    $byStatus = strpos($content, 'By status');

    expect($trend)->not->toBeFalse()
        ->and($latest)->not->toBeFalse()
        ->and($byStatus)->not->toBeFalse()
        ->and($trend)->toBeLessThan($latest)
        ->and($latest)->toBeLessThan($byStatus);
});

test('the status panel matches the incident panel heading structure', function () {
    Incident::factory()->ongoing()->create();

    $content = actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    // Both side-by-side panels must use the same heading row and card wrapper,
    // otherwise the taller "View all" button offsets one heading from the other.
    expect(substr_count($content, 'class="mb-3 flex items-center justify-between"'))->toBe(2)
        ->and(substr_count($content, 'class="card divide-y divide-border"'))->toBe(1);
});

test('the top barangay chart is limited to six rows', function () {
    foreach (range(1, 8) as $index) {
        Incident::factory()->ongoing()->count($index)->create(['location_label' => "Barangay {$index}"]);
    }

    actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Barangay 8: 8 incidents', false)
        ->assertSee('Barangay 3: 3 incidents', false)
        ->assertDontSee('Barangay 2:', false)
        ->assertDontSee('Barangay 1:', false);
});
