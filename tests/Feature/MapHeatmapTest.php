<?php

use App\Enums\IncidentType;
use App\Models\Incident;

use function Pest\Laravel\get;

test('the live map renders the heatmap toggle, legend and hot spot controls', function () {
    get('/')
        ->assertOk()
        ->assertSee('aria-label="Toggle heatmap overlay"', escape: false)
        ->assertSee('heat-legend', escape: false)
        ->assertSee('Hot spots', escape: false)
        ->assertSee('x-model="heat"', escape: false);
});

test('the live map passes incident type and label for heat classification', function () {
    $incident = Incident::factory()->verified()->create([
        'incident_type' => IncidentType::Flood,
        'location_label' => 'Dugo',
    ]);

    get('/')
        ->assertOk()
        ->assertSee('\u0022incident_type\u0022:\u0022flood\u0022', escape: false)
        ->assertSee('\u0022incident_type_label\u0022:\u0022Flood\u0022', escape: false)
        ->assertSee('\u0022location_label\u0022:\u0022Dugo\u0022', escape: false);
});

test('the live map heat state is enabled by default', function () {
    get('/')
        ->assertOk()
        ->assertSee('heat: true', escape: false);
});
