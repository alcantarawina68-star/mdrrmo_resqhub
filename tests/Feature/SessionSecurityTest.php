<?php

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('the session lifetime default is set to 20 minutes', function () {
    config()->set('session.lifetime', 20);
    expect(config('session.lifetime'))->toBe(20);
});

test('remember me is disabled: the login view does not offer it', function () {
    get('/login')
        ->assertOk()
        ->assertDontSee('Remember me');
});

test('logging in does not create a remember me cookie when requested', function () {
    $user = User::factory()->communityUser()->create([
        'email' => 'noremember@example.com',
        'password' => 'password',
    ]);

    $response = post('/login', [
        'email' => 'noremember@example.com',
        'password' => 'password',
        'remember' => 'on',
    ]);

    $hasRememberCookie = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect(auth()->check())->toBeTrue()
        ->and($hasRememberCookie)->toBeFalse();
});

test('sensitive actions require re-authentication when not recently confirmed', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();

    actingAs($admin);

    post("/dashboard/incidents/{$incident->id}/status", [
        'status' => 'ongoing',
    ])->assertRedirect(route('password.confirm'));

    expect(session('auth.redirect_to'))->not->toBeNull();
});

test('sensitive actions are allowed after re-authentication', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();

    actingAs($admin);

    session(['auth.password_confirmed_at' => time()]);

    $response = post("/dashboard/incidents/{$incident->id}/status", [
        'status' => 'ongoing',
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->not->toContain('confirm-password');
});

test('the re-authentication window expires after 15 minutes', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();

    actingAs($admin);

    session(['auth.password_confirmed_at' => now()->subMinutes(16)->getTimestamp()]);

    post("/dashboard/incidents/{$incident->id}/status", [
        'status' => 'ongoing',
    ])->assertRedirect(route('password.confirm'));
});

test('confirming the password marks the session as re-authenticated', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'confirm@example.com',
        'password' => 'secret-password',
    ]);

    actingAs($admin);

    post('/confirm-password', [
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard'));

    expect(session()->has('auth.password_confirmed_at'))->toBeTrue();
});

test('confirming the password with the wrong password fails', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'wrong@example.com',
        'password' => 'secret-password',
    ]);

    actingAs($admin);

    post('/confirm-password', [
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('password');
});

test('the dashboard renders the logout action for authenticated users', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Log out');
});

test('logout clears the stored session id', function () {
    $user = User::factory()->communityUser()->create();
    $user->forceFill(['session_id' => 'active-session'])->save();

    actingAs($user)->post('/logout');

    expect($user->fresh()->session_id)->toBeNull();
});
