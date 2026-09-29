<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guest error pages are branded and offer a way back', function () {
    get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Back to live map');

    $responder = User::factory()->responder()->create();

    actingAs($responder)
        ->get('/dashboard/users')
        ->assertForbidden()
        ->assertSee('Access denied')
        ->assertSee('Back to live map');
});

test('report and caller forms include a use-my-location button', function () {
    $communityUser = User::factory()->communityUser()->create();
    $admin = User::factory()->admin()->create();

    actingAs($communityUser)->get('/report')->assertOk()->assertSee('Use my location');
    actingAs($admin)->get('/dashboard/caller')->assertOk()->assertSee('Use my location');
});

test('the users page uses the branded delete confirmation dialog', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->communityUser()->create();

    actingAs($admin)
        ->get('/dashboard/users')
        ->assertOk()
        ->assertSee('delete-user-dialog-title')
        ->assertSee('Delete user');
});

test('the live map shows pin details in the marker popup only', function () {
    get('/')
        ->assertOk()
        ->assertDontSee('Close details')
        ->assertDontSee('x-show="selected"');
});

test('mobile navigation menus stay pinned to the sticky header when scrolling', function () {
    get('/')->assertOk()->assertSee('fixed inset-x-0 top-16 z-40 border-b border-border bg-surface shadow-lg md:hidden');

    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('fixed inset-x-0 top-16 z-40 border-b border-border bg-surface shadow-lg lg:hidden');
});
