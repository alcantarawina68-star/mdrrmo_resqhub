---
paths:
  - app/Support/MapLayers.php
  - app/Support/BarangayLocations.php
---

# Support

## No dark basemap — dark mode dims light layers with CSS instead
Map imagery is Standard / Satellite / Terrain only; the user asked to drop the dark basemap. Dark mode therefore stays a CSS concern (`.dark .leaflet-container.map-tiles-light` filter) rather than swapping tiles, so the layer no longer follows the colour scheme and there is no `resqhub:themechange` wiring. If a dark basemap is ever wanted back, add it to `all()` and re-check the `light` flags.

## Barangay nearest-match is duplicated in JS on purpose — keep them in sync
Nearest-centroid matching for the map pin picker exists twice: `BarangayLocations::nearest()` (reference, tested) and `nearestBarangay()` in `resources/js/map.js` (runtime, no JS test runner in this repo). Same equirectangular approximation, same `METRES_PER_DEGREE = 111320`. Any change to one must be made in the other, or a pin resolves to a different barangay in the browser than in PHP. `suggest()` == JS with `MAX_SUGGESTION_METRES` passed as the third arg. Verify parity by importing map.js in node with a stubbed global `L` and comparing against PHP output over a grid of points.
