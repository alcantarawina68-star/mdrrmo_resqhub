<?php

use App\Models\Announcement;
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

test('the pending action runs once the password is confirmed', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'secret-password',
    ]);

    actingAs($admin);

    $payload = [
        'title' => 'Road closure advisory',
        'content' => 'The national highway is closed near the bridge for repair work.',
        'category' => 'advisory',
        'severity' => 'info',
    ];

    // First click on Publish: no password confirmed yet, so this must challenge.
    post(route('dashboard.announcements.store'), $payload)
        ->assertRedirect(route('password.confirm'));

    expect(Announcement::count())->toBe(0);

    // Entering the password must lead straight back to the publish itself,
    // rather than returning the user to a page where they resubmit everything.
    post('/confirm-password', ['password' => 'secret-password'])
        ->assertRedirect(route('password.confirm.resume'));

    get(route('password.confirm.resume'))
        ->assertOk()
        ->assertSee('action="/dashboard/announcements"', false);

    // The resume page self-submits the deferred request to its original action.
    post(route('dashboard.announcements.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Announcement published.');

    expect(Announcement::count())->toBe(1)
        ->and(Announcement::first()->title)->toBe('Road closure advisory');
});

test('a deferred request is only replayed once', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'secret-password',
    ]);

    actingAs($admin);

    $payload = [
        'title' => 'Road closure advisory',
        'content' => 'The national highway is closed near the bridge for repair work.',
        'category' => 'advisory',
        'severity' => 'info',
    ];

    post(route('dashboard.announcements.store'), $payload)
        ->assertRedirect(route('password.confirm'));

    post('/confirm-password', ['password' => 'secret-password']);

    get(route('password.confirm.resume'))->assertOk();

    // A validation failure bounces the user back to the resume page. It must
    // show the error instead of resubmitting the same payload in a loop.
    get(route('password.confirm.resume'))
        ->assertOk()
        ->assertSee('Nothing to finish');

    expect(Announcement::count())->toBe(0);
});

test('a deferred delete keeps its method spoofing', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'secret-password',
    ]);

    $announcement = Announcement::create([
        'user_id' => $admin->id,
        'title' => 'Flood advisory',
        'content' => 'Flooding is reported in the riverside barangays right now.',
        'category' => 'warning',
        'severity' => 'urgent',
        'published_at' => now(),
    ]);

    actingAs($admin);

    // The delete button posts with method spoofing, exactly as the Blade form does.
    post(route('dashboard.announcements.destroy', $announcement), ['_method' => 'DELETE'])
        ->assertRedirect(route('password.confirm'));

    post('/confirm-password', ['password' => 'secret-password'])
        ->assertRedirect(route('password.confirm.resume'));

    get(route('password.confirm.resume'))
        ->assertOk()
        ->assertSee('name="_method" value="DELETE"', false);

    post(route('dashboard.announcements.destroy', $announcement), ['_method' => 'DELETE'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Announcement deleted.');

    expect(Announcement::count())->toBe(0);
});

test('a read-only challenge still returns the user to where they were', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'secret-password',
    ]);

    actingAs($admin);

    // A GET challenge has no deferred payload to replay.
    session(['auth.redirect_to' => route('dashboard.announcements')]);

    get(route('password.confirm'))->assertOk();

    post('/confirm-password', ['password' => 'secret-password'])
        ->assertRedirect(route('dashboard.announcements'));
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
