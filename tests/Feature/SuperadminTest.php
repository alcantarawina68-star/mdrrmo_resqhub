<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function superadminFakeSession(string $id, int $userId): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);
}

test('the seeder creates a superadmin account', function () {
    $this->seed(SuperadminSeeder::class);

    $superadmin = User::where('email', 'superadmin@resqhub.ph')->first();

    expect($superadmin)->not->toBeNull()
        ->and($superadmin->role)->toBe(UserRole::Superadmin)
        ->and($superadmin->isSuperadmin())->toBeTrue();
});

test('a superadmin can access all dashboard pages', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get('/dashboard')->assertOk();

    actingAs($superadmin)->get('/dashboard/incidents')->assertOk();
    actingAs($superadmin)->get('/dashboard/caller')->assertOk();
    actingAs($superadmin)->get('/dashboard/announcements')->assertOk();
    actingAs($superadmin)->get('/dashboard/users')->assertOk();
    actingAs($superadmin)->get('/dashboard/reports')->assertOk();
});

test('a superadmin can access the sessions management page', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get('/dashboard/sessions')->assertOk();
});

test('a regular admin cannot access the sessions management page', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get('/dashboard/sessions')->assertForbidden();
});

test('only a superadmin can view the sessions link in the dashboard', function () {
    $superadmin = User::factory()->superadmin()->create();
    $admin = User::factory()->admin()->create();

    actingAs($superadmin)->get('/dashboard')->assertOk()->assertSee('Sessions');
    actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('Sessions');
});

test('a regular admin cannot create a superadmin account', function () {
    $admin = User::factory()->admin()->create();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($admin)
        ->post('/dashboard/users', [
            'name' => 'Rogue Super Admin',
            'email' => 'rogue@example.com',
            'password' => 'password',
            'role' => UserRole::Superadmin->value,
            'status' => 'active',
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'rogue@example.com')->exists())->toBeFalse();
});

test('a superadmin can create another superadmin account', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->post('/dashboard/users', [
            'name' => 'Second Super Admin',
            'email' => 'second@example.com',
            'password' => 'password',
            'role' => UserRole::Superadmin->value,
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'second@example.com')->where('role', UserRole::Superadmin->value)->exists())->toBeTrue();
});

test('a regular admin cannot modify or delete a superadmin account', function () {
    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create(['email' => 'boss@example.com']);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($admin)
        ->post("/dashboard/users/{$superadmin->id}", [
            'status' => 'inactive',
        ])
        ->assertSessionHasErrors();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($admin)
        ->delete("/dashboard/users/{$superadmin->id}")
        ->assertSessionHasErrors();

    expect($superadmin->fresh())->not->toBeNull();
});

test('the last active superadmin cannot be demoted or deleted', function () {
    $superadmin = User::factory()->superadmin()->create();
    $otherSuperadmin = User::factory()->superadmin()->create();

    // Demote one of the two superadmins first, leaving only one active superadmin.
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->post("/dashboard/users/{$otherSuperadmin->id}", [
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasNoErrors();

    $last = User::where('role', UserRole::Superadmin->value)->where('status', 'active')->first();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->post("/dashboard/users/{$last->id}", [
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasErrors();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->delete("/dashboard/users/{$last->id}")
        ->assertSessionHasErrors();
});

test('a superadmin can log out a user from all devices', function () {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->communityUser()->create(['email' => 'target@example.com']);

    superadminFakeSession('device-a', $target->id);
    superadminFakeSession('device-b', $target->id);
    $target->forceFill(['session_id' => 'device-a'])->save();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->post("/dashboard/sessions/users/{$target->id}/logout");

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0)
        ->and($target->fresh()->session_id)->toBeNull();
});

test('a superadmin can terminate a single device session', function () {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->communityUser()->create();

    superadminFakeSession('device-a', $target->id);
    superadminFakeSession('device-b', $target->id);
    $target->forceFill(['session_id' => 'device-a'])->save();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($superadmin)
        ->post('/dashboard/sessions/device-a/terminate');

    expect(DB::table('sessions')->where('id', 'device-a')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'device-b')->exists())->toBeTrue()
        ->and($target->fresh()->session_id)->toBeNull();
});

test('the sessions page renders for the current superadmin device', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->get('/dashboard/sessions');

    expect($response->status())->toBe(200);
});
