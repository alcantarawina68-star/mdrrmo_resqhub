<?php

use App\Models\SiteSetting;

use function Pest\Laravel\get;

test('the public map is the landing page and shows the hotline popup', function () {
    get('/')
        ->assertOk()
        ->assertSee('Emergency hotline')
        ->assertSee('0917 123 4567')
        ->assertSee('hotline-dialog-title', false)
        ->assertSee('tel:09171234567', false)
        ->assertSee("Don't show again", false)
        ->assertSee('resqhub:hide-hotline', false);
});

test('the hotline popup reflects the configured hotline setting', function () {
    SiteSetting::set('hotline', '0919 999 9999');

    get('/')
        ->assertOk()
        ->assertSee('0919 999 9999')
        ->assertSee('tel:09199999999', false);
});

test('the hotline popup only renders on the public map', function () {
    get('/advisories')
        ->assertOk()
        ->assertDontSee('hotline-dialog-title', false);
});

test('the call link strips formatting from the configured hotline', function (string $hotline, string $expected) {
    SiteSetting::set('hotline', $hotline);

    get('/')
        ->assertOk()
        ->assertSee("tel:{$expected}", false);
})->with([
    'spaced' => ['0917 123 4567', '09171234567'],
    'hyphenated' => ['0917-123-4567', '09171234567'],
    'parenthesised' => ['(0917) 123-4567', '09171234567'],
    'dotted' => ['0917.123.4567', '09171234567'],
    'international' => ['+63 917 123 4567', '+639171234567'],
]);

test('the call link is omitted when the hotline has no dialable digits', function () {
    SiteSetting::set('hotline', 'N/A');

    get('/')
        ->assertOk()
        ->assertDontSee('tel:', false);
});
