<?php

use App\Models\SiteSetting;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('the public footer shows the default agency and hotline', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('MDRRMO Camalaniugan')
        ->assertSee('Emergency hotline: 0917 123 4567');
});

test('an admin can update the site information and it appears on the public site', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.settings.update'), [
            'agency_short_name' => 'MDRRMO Camalaniugan',
            'agency_name' => 'Municipal Disaster Risk Reduction and Management Office',
            'municipality' => 'Camalaniugan, Cagayan',
            'hotline' => '0921 987 6543',
            'website' => 'emergency.camalaniugan.gov.ph',
            'contact_email' => 'mdrrmo@camalaniugan.gov.ph',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(SiteSetting::value('hotline', 'fallback'))->toBe('0921 987 6543');

    $this->get('/')
        ->assertOk()
        ->assertSee('Emergency hotline: 0921 987 6543')
        ->assertSee('emergency.camalaniugan.gov.ph')
        ->assertSee('mdrrmo@camalaniugan.gov.ph');
});

test('only admins and superadmins can manage the site information', function () {
    $admin = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();
    $encoder = User::factory()->encoder()->create();
    $user = User::factory()->communityUser()->create();

    actingAs($admin)->get(route('dashboard.settings'))->assertOk();
    actingAs($superadmin)->get(route('dashboard.settings'))->assertOk();

    actingAs($encoder)->get(route('dashboard.settings'))->assertForbidden();
    actingAs($user)->get(route('dashboard.settings'))->assertForbidden();
});

test('site information updates are validated', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.settings.update'), [
            'agency_short_name' => '',
            'hotline' => '',
            'contact_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['agency_short_name', 'hotline', 'contact_email']);
});
