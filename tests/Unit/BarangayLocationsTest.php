<?php

use App\Support\BarangayLocations;
use App\Support\CamalBarangays;

test('every barangay of Camalaniugan has a centroid', function () {
    expect(array_keys(BarangayLocations::centroids()))
        ->toBe(CamalBarangays::all());
});

test('the centroids cover the 28 barangays exactly once', function () {
    expect(BarangayLocations::centroids())->toHaveCount(28);
});

test('centroids are distinct points inside Camalaniugan', function () {
    $centroids = BarangayLocations::centroids();

    $points = array_map(
        fn (array $centroid) => "{$centroid['lat']},{$centroid['lng']}",
        $centroids,
    );

    expect($points)->toHaveCount(count(array_unique($points)));

    foreach ($centroids as $centroid) {
        expect($centroid['lat'])->toBeGreaterThan(18.2)->toBeLessThan(18.35);
        expect($centroid['lng'])->toBeGreaterThan(121.6)->toBeLessThan(121.8);
    }
});

test('nearest resolves a pin dropped on a barangay centroid', function (string $barangay) {
    $centroid = BarangayLocations::centroids()[$barangay];

    $nearest = BarangayLocations::nearest($centroid['lat'], $centroid['lng']);

    expect($nearest['name'])->toBe($barangay)
        ->and($nearest['distance_metres'])->toBeLessThan(1.0);
})->with(array_keys(BarangayLocations::centroids()));

test('the match follows the pin as it moves between two neighbouring barangays', function () {
    $baggao = BarangayLocations::centroids()['Baggao'];
    $alilinu = BarangayLocations::centroids()['Alilinu'];

    $towardsAlilinu = [
        'lat' => $baggao['lat'] + 0.9 * ($alilinu['lat'] - $baggao['lat']),
        'lng' => $baggao['lng'] + 0.9 * ($alilinu['lng'] - $baggao['lng']),
    ];

    $towardsBaggao = [
        'lat' => $alilinu['lat'] + 0.9 * ($baggao['lat'] - $alilinu['lat']),
        'lng' => $alilinu['lng'] + 0.9 * ($baggao['lng'] - $alilinu['lng']),
    ];

    expect(BarangayLocations::nearest($towardsAlilinu['lat'], $towardsAlilinu['lng'])['name'])->toBe('Alilinu')
        ->and(BarangayLocations::nearest($towardsBaggao['lat'], $towardsBaggao['lng'])['name'])->toBe('Baggao');
});

test('suggest fills in a barangay for a pin inside the municipality', function () {
    $centroSur = BarangayLocations::centroids()['Centro Sur'];

    $suggestion = BarangayLocations::suggest($centroSur['lat'], $centroSur['lng']);

    expect($suggestion['name'])->toBe('Centro Sur');
});

test('suggest refuses a pin outside the municipality', function () {
    expect(BarangayLocations::suggest(17.6138, 121.7270))->toBeNull();
});

test('the suggestion radius covers every barangay but stays local', function () {
    $farthest = 0.0;

    foreach (BarangayLocations::centroids() as $centroid) {
        $distance = BarangayLocations::nearest($centroid['lat'], $centroid['lng'])['distance_metres'];
        $farthest = max($farthest, $distance);
    }

    expect(BarangayLocations::MAX_SUGGESTION_METRES)->toBeGreaterThan($farthest)
        ->and(BarangayLocations::MAX_SUGGESTION_METRES)->toBeLessThan(15_000.0);
});
