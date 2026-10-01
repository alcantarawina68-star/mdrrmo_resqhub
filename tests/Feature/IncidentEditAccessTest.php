<?php

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

use function Pest\Laravel\actingAs;

function confirmedSession(): array
{
    return ['auth.password_confirmed_at' => time()];
}

test('responders can update incident details', function () {
    $responder = User::factory()->responder()->create();
    $incident = Incident::factory()->ongoing()->create();

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.update', $incident), [
            'incident_type' => 'flood',
            'description' => 'Flood water has risen to knee level along the national highway.',
            'location_label' => 'Ilawod',
            'latitude' => 18.25,
            'longitude' => 121.55,
        ])
        ->assertRedirect();

    $incident->refresh();

    expect($incident->incident_type->value)->toBe('flood')
        ->and($incident->location_label)->toBe('Ilawod')
        ->and((float) $incident->latitude)->toBe(18.25)
        ->and((float) $incident->longitude)->toBe(121.55);
});

test('responders can save a correction without submitting coordinates', function () {
    $responder = User::factory()->responder()->create();
    $incident = Incident::factory()->ongoing()->create([
        'latitude' => 18.25,
        'longitude' => 121.55,
    ]);

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.update', $incident), [
            'incident_type' => $incident->incident_type->value,
            'description' => 'Corrected description of the flooding at the barangay hall.',
        ])
        ->assertSessionHasNoErrors();

    $incident->refresh();

    expect((float) $incident->latitude)->toBe(18.25)
        ->and((float) $incident->longitude)->toBe(121.55);
});

test('responders cannot verify, restatus, or notify on an incident', function () {
    $responder = User::factory()->responder()->create();
    $incident = Incident::factory()->underVerification()->create();

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.verify', $incident), ['action' => 'approve'])
        ->assertForbidden();

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.status', $incident), ['status' => 'resolved'])
        ->assertForbidden();

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.notify', $incident))
        ->assertForbidden();
});

test('a community user cannot update incident details', function () {
    $user = User::factory()->communityUser()->create();
    $incident = Incident::factory()->ongoing()->create();

    actingAs($user)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.update', $incident), [
            'incident_type' => 'flood',
            'description' => 'A description long enough to pass validation rules.',
        ])
        ->assertForbidden();
});

test('out of range coordinates are rejected', function () {
    $responder = User::factory()->responder()->create();
    $incident = Incident::factory()->ongoing()->create();

    actingAs($responder)
        ->withSession(confirmedSession())
        ->post(route('dashboard.incidents.update', $incident), [
            'incident_type' => $incident->incident_type->value,
            'description' => 'A description long enough to pass validation rules.',
            'latitude' => 999,
            'longitude' => 121.55,
        ])
        ->assertSessionHasErrors('latitude');
});

test('the edit form is visible to every incident editor role', function (UserRole $role) {
    $incident = Incident::factory()->ongoing()->create();

    actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('Edit details', false)
        ->assertSee('name="latitude"', false)
        ->assertSee('name="longitude"', false);
})->with([
    UserRole::Superadmin,
    UserRole::Admin,
    UserRole::Encoder,
    UserRole::Responder,
]);

test('the edit form lets an editor pick the location on a map', function (UserRole $role) {
    $incident = Incident::factory()->ongoing()->create();

    actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('id="incident-edit-map"', false)
        ->assertSee('Use current location', false)
        ->assertSee('incidentEditForm(', false)
        ->assertSee('createLocationPicker', false);
})->with([UserRole::Responder, UserRole::Admin]);

test('the picker restores the current coordinates as its centre point', function () {
    $incident = Incident::factory()->ongoing()->create([
        'latitude' => 18.5123,
        'longitude' => 121.6543,
    ]);

    actingAs(User::factory()->responder()->create())
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('value="18.5123"', false)
        ->assertSee('value="121.6543"', false);
});

test('coordinates rejected by validation are reported next to the inputs', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['latitude' => ['The latitude field must be between -90 and 90.']]));

    view()->composer('*', fn ($view) => $view->with('errors', $errors));

    $incident = Incident::factory()->ongoing()->create();

    actingAs(User::factory()->responder()->create())
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('edit-latitude-error')
        ->assertSee('The latitude field must be between -90 and 90.')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('class="label text-danger"', false)
        ->assertSee('border-danger focus:border-danger');
});

test('a community user cannot reach the incident detail page at all', function () {
    $incident = Incident::factory()->ongoing()->create();

    actingAs(User::factory()->communityUser()->create())
        ->get(route('dashboard.incidents.show', $incident))
        ->assertForbidden();
});

test('incident editor roles cover responders and every operations role', function () {
    expect(UserRole::incidentEditorRoles())
        ->toContain(UserRole::Superadmin->value)
        ->toContain(UserRole::Admin->value)
        ->toContain(UserRole::Encoder->value)
        ->toContain(UserRole::Responder->value)
        ->not->toContain(UserRole::CommunityUser->value);
});
