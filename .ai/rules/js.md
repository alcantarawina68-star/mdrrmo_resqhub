---
paths:
  - resources/js/map.js
---

# Js

## Base layers come from App\Support\MapLayers, not hardcoded tiles
Tile layer definitions live only in `app/Support/MapLayers.php`. Views pass `@js(MapLayers::all())` into `createIncidentMap()`/`createLocationPicker()` and the `<x-map-type-switch>` renders the same list. The `mapLayer` Alpine store + `setMapBaseLayer()` keep every map and every switch in sync, and `resqhub:themechange` (dispatched by the darkMode store) re-applies the colour-scheme default only when the visitor has not picked a layer. Do not hardcode a `L.tileLayer` URL in a view or inline script.

## Heat layer is hand-rolled; legend CSS mirrors its gradient
The live map heatmap is a dependency-free canvas layer (L.Layer) defined in map.js — there is no leaflet.heat package. `DEFAULT_HEAT_GRADIENT` (map.js) must stay in sync with `.heat-legend` (resources/css/app.css) so the sidebar legend matches the drawn rgba stops. Hot spots are bucketed client-side in the mapPage Alpine component via controller.hotSpots(); they only reflect incidents visible on screen.
