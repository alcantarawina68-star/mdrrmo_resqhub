<?php

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

use function Pest\Laravel\actingAs;

test('approving an incident without an assigned unit is rejected', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.incidents.verify', $incident), ['action' => 'approve'])
        ->assertRedirect()
        ->assertSessionHasErrors('assigned_unit');
});

test('the assigned unit field is highlighted when it fails validation', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['assigned_unit' => ['The assigned unit field is required when action is approve.']]));

    view()->composer('*', fn ($view) => $view->with('errors', $errors));

    actingAs($admin)
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('verify-unit-error')
        ->assertSee('The assigned unit field is required when action is approve.')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('class="label text-danger"', false)
        ->assertSee('border-danger focus:border-danger');
});
