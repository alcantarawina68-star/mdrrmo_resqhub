---
paths:
  - app/Support/MapLayers.php
---

# Support

## No dark basemap — dark mode dims light layers with CSS instead
Map imagery is Standard / Satellite / Terrain only; the user asked to drop the dark basemap. Dark mode therefore stays a CSS concern (`.dark .leaflet-container.map-tiles-light` filter) rather than swapping tiles, so the layer no longer follows the colour scheme and there is no `resqhub:themechange` wiring. If a dark basemap is ever wanted back, add it to `all()` and re-check the `light` flags.
