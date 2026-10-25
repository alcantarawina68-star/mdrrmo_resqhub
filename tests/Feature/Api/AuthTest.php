<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('the registration endpoint is gone', function () {
    postJson('/api/v1/auth/register', [
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertNotFound();
});

test('a user can log in and fetch their profile', function () {
    $user = User::factory()->communityUser()->create(['email' => 'login@example.com']);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user']]);

    getJson('/api/v1/me', [
        'Authorization' => 'Bearer '.$response->json('data.token'),
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.id', $user->id);
});

test('login rejects wrong credentials', function () {
    User::factory()->create(['email' => 'wrong@example.com']);

    postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'not-the-password',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email']]);
});

test('login rejects suspended accounts', function () {
    User::factory()->suspended()->create(['email' => 'suspended@example.com']);

    postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email']]);
});

test('an unauthenticated request is rejected with 401', function () {
    getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.message', 'Logged out successfully.');

    expect($user->tokens()->count())->toBe(0);
});

test('an inactive user cannot reach protected endpoints', function () {
    $user = User::factory()->inactive()->create();
    $token = $user->createToken('api')->plainTextToken;

    getJson('/api/v1/me', [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertStatus(403);
});

test('sanctum guard works with actingAs', function () {
    $user = User::factory()->create();

    actingAs($user, 'sanctum');

    getJson('/api/v1/me')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $user->id);
});
