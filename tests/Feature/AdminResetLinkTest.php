<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

test('an admin can send a reset link to another user', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $target = User::factory()->communityUser()->create();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($admin)
        ->post("/dashboard/users/{$target->id}/reset-password")
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', "Password reset link sent to {$target->email}.");

    Notification::assertSentTo($target, ResetPassword::class);
});

test('a regular admin cannot send a reset link to a superadmin', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->actingAs($admin)
        ->post("/dashboard/users/{$superadmin->id}/reset-password")
        ->assertSessionHasErrors('user');

    Notification::assertNotSentTo($superadmin, ResetPassword::class);
});

test('a responder cannot send reset links on the users page', function () {
    $responder = User::factory()->responder()->create();
    $target = User::factory()->communityUser()->create();

    actingAs($responder)
        ->post("/dashboard/users/{$target->id}/reset-password")
        ->assertForbidden();
});

test('the users page offers a reset password action', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->communityUser()->create();

    actingAs($admin)
        ->get('/dashboard/users')
        ->assertOk()
        ->assertSee('Reset password');
});
