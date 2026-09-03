<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function fakeSession(?string $id = null, ?int $userId = null): string
{
    $id ??= Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    return $id;
}

test('login stores the session id on the user', function () {
    $user = User::factory()->communityUser()->create([
        'email' => 'single@example.com',
        'password' => 'password',
    ]);

    post('/login', [
        'email' => 'single@example.com',
        'password' => 'password',
    ])
        ->assertRedirect(route('my-reports'));

    expect($user->fresh()->session_id)->not->toBeNull();
});

test('login is blocked when the account already has an active session', function () {
    $user = User::factory()->communityUser()->create([
        'email' => 'busy@example.com',
        'password' => 'password',
    ]);

    fakeSession('active-session', $user->id);
    $user->forceFill(['session_id' => 'active-session'])->save();

    post('/login', [
        'email' => 'busy@example.com',
        'password' => 'password',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionHasErrors(['email' => 'This account is already logged in on another device. Please log out there first or wait for the session to expire.']);

    expect(auth()->check())->toBeFalse();
});

test('login is allowed when the stored session no longer exists', function () {
    $user = User::factory()->communityUser()->create([
        'email' => 'stale@example.com',
        'password' => 'password',
    ]);

    $user->forceFill(['session_id' => 'expired-session'])->save();

    post('/login', [
        'email' => 'stale@example.com',
        'password' => 'password',
    ])->assertRedirect(route('my-reports'));

    expect($user->fresh()->session_id)->not->toBeNull();
});

test('logging out clears the stored session id', function () {
    $user = User::factory()->communityUser()->create();
    $user->forceFill(['session_id' => 'active-session'])->save();

    actingAs($user)->post('/logout');

    expect($user->fresh()->session_id)->toBeNull();
});
