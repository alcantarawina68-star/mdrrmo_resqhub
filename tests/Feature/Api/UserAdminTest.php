<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('only admins can list users', function () {
    $admin = User::factory()->admin()->create();
    $encoder = User::factory()->encoder()->create();
    User::factory()->count(3)->create();

    actingAs($admin, 'sanctum')->getJson('/api/v1/users')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 5);

    actingAs($encoder, 'sanctum')->getJson('/api/v1/users')
        ->assertStatus(403);
});

test('admins can filter users by role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->responder()->create();
    User::factory()->communityUser()->create();

    actingAs($admin, 'sanctum')->getJson('/api/v1/users?role=responder')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.role', UserRole::Responder->value);
});

test('admins can search users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Zandro Reyes']);
    User::factory()->create(['name' => 'Jane Doe']);

    actingAs($admin, 'sanctum')->getJson('/api/v1/users?search=Reyes')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Zandro Reyes');
});

test('an admin can create a user', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'New Responder',
            'email' => 'responder.new@example.com',
            'password' => 'password123',
            'role' => UserRole::Responder->value,
            'status' => UserStatus::Active->value,
            'contact_number' => '09171112222',
            'barangay' => 'Anoling',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.role', UserRole::Responder->value)
        ->assertJsonPath('data.status', UserStatus::Active->value);

    expect(User::where('email', 'responder.new@example.com')->exists())->toBeTrue();
});

test('user creation validates required fields', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->postJson('/api/v1/users', [
            'name' => 'No Role',
            'email' => 'nope@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['password', 'role', 'status']]);
});

test('an admin can update a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->communityUser()->create();

    actingAs($admin, 'sanctum')
        ->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Renamed User',
            'email' => $user->email,
            'role' => UserRole::CommunityUser->value,
            'status' => UserStatus::Inactive->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Renamed User')
        ->assertJsonPath('data.status', UserStatus::Inactive->value);
});

test('an admin cannot change their own role or status', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->putJson("/api/v1/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Encoder->value,
            'status' => UserStatus::Active->value,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['role']]);
});

test('an admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/users/{$admin->id}")
        ->assertStatus(422);
});

test('the last active admin cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->communityUser()->create();

    actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/users/{$admin->id}")
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['user']]);
});

test('an admin can delete another user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->communityUser()->create();

    actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/users/{$target->id}")
        ->assertStatus(200);

    expect(User::find($target->id))->toBeNull();
});
