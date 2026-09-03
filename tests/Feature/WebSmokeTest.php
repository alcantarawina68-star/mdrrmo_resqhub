<?php

use App\Models\Incident;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('public pages are accessible to guests', function () {
    get('/')->assertOk();
    get('/advisories')->assertOk();
    get('/login')->assertOk();
    get('/register')->assertOk();
});

test('a publicly visible incident detail page renders', function () {
    $incident = Incident::factory()->ongoing()->create();

    get("/incidents/{$incident->id}")
        ->assertOk()
        ->assertSee($incident->incident_number);
});

test('a non-public incident detail page returns 404', function () {
    $incident = Incident::factory()->underVerification()->create();

    get("/incidents/{$incident->id}")->assertNotFound();
});

test('guests are redirected to login for protected pages', function () {
    get('/report')->assertRedirect(route('login'));
    get('/my-reports')->assertRedirect(route('login'));
    get('/dashboard')->assertRedirect(route('login'));
});

test('a community user can access reporting pages but not the dashboard', function () {
    $user = User::factory()->communityUser()->create();

    actingAs($user)->get('/report')->assertOk();
    actingAs($user)->get('/my-reports')->assertOk();

    actingAs($user)->get('/dashboard')->assertForbidden();
});

test('a responder can access the dashboard but not admin pages', function () {
    $responder = User::factory()->responder()->create();

    actingAs($responder)->get('/dashboard')->assertOk();
    actingAs($responder)->get('/dashboard/incidents')->assertOk();

    actingAs($responder)->get('/dashboard/users')->assertForbidden();
    actingAs($responder)->get('/dashboard/reports')->assertForbidden();
});

test('an admin can access all dashboard pages', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->verified()->create();

    actingAs($admin)->get('/dashboard')->assertOk();
    actingAs($admin)->get('/dashboard/incidents')->assertOk();
    actingAs($admin)->get('/dashboard/caller')->assertOk();
    actingAs($admin)->get('/dashboard/announcements')->assertOk();
    actingAs($admin)->get('/dashboard/users')->assertOk();
    actingAs($admin)->get('/dashboard/reports')->assertOk();
});

test('an inactive user is redirected from authenticated pages', function () {
    $user = User::factory()->inactive()->create();

    actingAs($user)->get('/report')->assertRedirect(route('login'));
});
