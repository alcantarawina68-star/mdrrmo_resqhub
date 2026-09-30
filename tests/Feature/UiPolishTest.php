<?php

use App\Models\Incident;
use App\Models\User;
use App\Support\MapLayers;

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

test('destructive confirmation dialogs are focusable and described for screen readers', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->communityUser()->create();

    actingAs($admin)
        ->get('/dashboard/users')
        ->assertOk()
        ->assertSee('delete-user-dialog-description', escape: false)
        ->assertSee('x-ref="dialog"', escape: false)
        ->assertSee('@keydown.tab="trap($event)"', escape: false);
});

test('the map filter sheet is an accessible labelled dialog', function () {
    get('/')
        ->assertOk()
        ->assertSee('id="map-filter-sheet"', escape: false)
        ->assertSee('aria-label="Map filters"', escape: false)
        ->assertSee('@click.self="$store.bottomSheet.close()"', escape: false);
});

test('password fields expose a keyboard reachable visibility toggle', function () {
    get('/login')
        ->assertOk()
        ->assertSee('name="password"', escape: false)
        ->assertSee(':aria-pressed="show"', escape: false)
        ->assertDontSee('aria-label="Toggle password visibility"', escape: false);
});

test('every map offers the shared base layer switch', function () {
    $responder = User::factory()->responder()->create();
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    $pages = [
        get('/'),
        actingAs($responder)->get('/report'),
        actingAs($admin)->get('/dashboard/caller'),
        get("/incidents/{$incident->id}"),
    ];

    foreach ($pages as $page) {
        $page->assertOk()
            ->assertSee('aria-label="Map imagery"', escape: false)
            ->assertSee('x-data="mapLayerSwitch(', escape: false)
            ->assertSee('@click="$store.mapLayer.set(layer.key)"', escape: false);
    }
});

test('base layer options cover standard, satellite and terrain', function () {
    $keys = array_column(MapLayers::all(), 'key');

    expect($keys)->toBe(['standard', 'satellite', 'terrain']);

    foreach (MapLayers::all() as $layer) {
        expect($layer['attribution'])->not->toBeEmpty();
    }
});
