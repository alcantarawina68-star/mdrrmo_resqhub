<?php

namespace App\Support;

/**
 * Base layers offered by the Leaflet maps (live map, incident detail and the
 * report pickers). They are the single source of truth: the switch UI is built
 * from `all()` in Blade and the tile layers are created from the same array in
 * `resources/js/map.js`.
 *
 * The first entry is the default; dark mode dims light layers through
 * `.map-tiles-light` in `resources/css/app.css` rather than swapping tiles.
 */
final class MapLayers
{
    /**
     * @return array<int, array{key: string, label: string, url: string, max_zoom: int, subdomains?: string, attribution: string, light: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'standard',
                'label' => 'Standard',
                'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'max_zoom' => 19,
                'light' => true,
                'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            ],
            [
                'key' => 'satellite',
                'label' => 'Satellite',
                'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                'max_zoom' => 19,
                'light' => false,
                'attribution' => 'Imagery &copy; <a href="https://www.esri.com">Esri</a>, Maxar, Earthstar Geographics, GIS User Community',
            ],
            [
                'key' => 'terrain',
                'label' => 'Terrain',
                'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'max_zoom' => 17,
                'light' => true,
                'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, SRTM | Style &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
            ],
        ];
    }
}
