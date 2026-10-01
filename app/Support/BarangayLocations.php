<?php

namespace App\Support;

/**
 * Approximate centre point of every barangay of Camalaniugan, Cagayan, used to
 * suggest a barangay when a reporter or encoder drops a pin on a map.
 *
 * Coordinates are barangay centroids published by PhilAtlas
 * (https://www.philatlas.com/luzon/r02/cagayan/camalaniugan.html), not
 * boundaries, so the nearest-centroid match is a suggestion only: a pin close
 * to a boundary can resolve to the neighbouring barangay. `nearest()` is the
 * reference implementation; `nearestBarangay()` in `resources/js/map.js` must
 * stay equivalent to it.
 *
 * Keys match `CamalBarangays::ALL` exactly.
 */
final class BarangayLocations
{
    /**
     * @var array<string, array{lat: float, lng: float}>
     */
    public const CENTROIDS = [
        'Abagao' => ['lat' => 18.2567, 'lng' => 121.7449],
        'Afunan Cabayu' => ['lat' => 18.2578, 'lng' => 121.7182],
        'Agusi' => ['lat' => 18.2826, 'lng' => 121.6715],
        'Alilinu' => ['lat' => 18.2912, 'lng' => 121.6664],
        'Baggao' => ['lat' => 18.2812, 'lng' => 121.6889],
        'Bantay' => ['lat' => 18.2490, 'lng' => 121.6955],
        'Bulala' => ['lat' => 18.2615, 'lng' => 121.6882],
        'Casili Norte' => ['lat' => 18.2754, 'lng' => 121.7084],
        'Casili Sur' => ['lat' => 18.2694, 'lng' => 121.7039],
        'Catotoran Norte' => ['lat' => 18.2980, 'lng' => 121.6622],
        'Catotoran Sur' => ['lat' => 18.2947, 'lng' => 121.6643],
        'Centro Norte' => ['lat' => 18.2755, 'lng' => 121.6739],
        'Centro Sur' => ['lat' => 18.2733, 'lng' => 121.6751],
        'Cullit' => ['lat' => 18.2520, 'lng' => 121.7569],
        'Dacal-Lafugu' => ['lat' => 18.2662, 'lng' => 121.6844],
        'Dammang Norte' => ['lat' => 18.2803, 'lng' => 121.6532],
        'Dammang Sur' => ['lat' => 18.2592, 'lng' => 121.6476],
        'Dugo' => ['lat' => 18.2531, 'lng' => 121.6866],
        'Fusina' => ['lat' => 18.2455, 'lng' => 121.7004],
        'Gang-ngo' => ['lat' => 18.2473, 'lng' => 121.6850],
        'Jurisdiction' => ['lat' => 18.2381, 'lng' => 121.6808],
        'Luec' => ['lat' => 18.2577, 'lng' => 121.7322],
        'Minanga' => ['lat' => 18.2713, 'lng' => 121.7210],
        'Paragat' => ['lat' => 18.2442, 'lng' => 121.7059],
        'Sapping' => ['lat' => 18.2703, 'lng' => 121.6769],
        'Tagum' => ['lat' => 18.2724, 'lng' => 121.7554],
        'Tuluttuging' => ['lat' => 18.2544, 'lng' => 121.7066],
        'Ziminila' => ['lat' => 18.2632, 'lng' => 121.7262],
    ];

    /**
     * Metres per degree of latitude, used to turn the squared-degree distance
     * into something a person can read on screen.
     */
    private const METRES_PER_DEGREE = 111_320.0;

    /**
     * How far a pin may sit from a centroid and still be auto-filled. Camalaniugan
     * is only 76.5 km2, so every in-town pin is well inside this radius while a
     * pin in a neighbouring municipality is not — that keeps the form from
     * confidently filing a report under the wrong barangay.
     */
    public const MAX_SUGGESTION_METRES = 5000.0;

    /**
     * @return array<string, array{lat: float, lng: float}>
     */
    public static function centroids(): array
    {
        return self::CENTROIDS;
    }

    /**
     * The barangay whose centroid sits closest to the given point, with the
     * distance in metres so the UI can show how confident the match is.
     *
     * @return array{name: string, distance_metres: float}|null
     */
    public static function nearest(float $lat, float $lng): ?array
    {
        $nearest = null;
        $best = INF;

        foreach (self::CENTROIDS as $name => $centroid) {
            $distance = (self::METRES_PER_DEGREE ** 2) * (
                ($centroid['lat'] - $lat) ** 2
                + (($centroid['lng'] - $lng) * cos(deg2rad($lat))) ** 2
            );

            if ($distance < $best) {
                $best = $distance;
                $nearest = $name;
            }
        }

        if ($nearest === null) {
            return null;
        }

        return [
            'name' => $nearest,
            'distance_metres' => sqrt($best),
        ];
    }

    /**
     * `nearest()` gated on `MAX_SUGGESTION_METRES`, for auto-filling a form
     * field. Returns null when the pin is too far from any barangay of
     * Camalaniugan for the match to be worth suggesting.
     *
     * @return array{name: string, distance_metres: float}|null
     */
    public static function suggest(float $lat, float $lng): ?array
    {
        $nearest = self::nearest($lat, $lng);

        if ($nearest === null || $nearest['distance_metres'] > self::MAX_SUGGESTION_METRES) {
            return null;
        }

        return $nearest;
    }
}
