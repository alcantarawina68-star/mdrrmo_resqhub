<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('guests are offered password reset entry points', function () {
    get('/login')
        ->assertOk()
        ->assertSee('Forgot your password?');

    get('/forgot-password')
        ->assertOk()
        ->assertSee('Send reset link');
});

test('a reset link is emailed to a known account', function () {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        return str_contains($notification->toMail($user)->actionUrl, '/reset-password/');
    });
});

test('an unknown email is reported without revealing whether the account exists', function () {
    post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
});

test('the reset form renders with the token and email', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Reset password')
        ->assertSee($token);
});

test('a valid token sets the new password and terminates existing sessions', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $sessionId = str_repeat('a', 40);
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test agent',
        'payload' => 'test',
        'last_activity' => now()->timestamp,
    ]);
    $user->forceFill(['session_id' => $sessionId])->save();

    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $user->refresh();

    expect(Hash::check('new-secure-password', $user->password))->toBeTrue();
    expect($user->session_id)->toBeNull();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(Auth::check())->toBeFalse();
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => 'not-the-right-token',
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('a weak or unconfirmed password is rejected before the token is consumed', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});
