---
paths:
  - 'resources/views/**'
---

# Views

## No inline @php(...) in views; group data once at the top
Blade trap: an inline @php(...) directive (single-line with parens) followed later by more @-directives gets mangled by Blade's paren-balancing pass — the rest of the template compiles to literal text and throws "Undefined variable". Symptom: compiled view keeps raw @foreach/@php text. Avoid inline @php(...) and per-loop @php blocks in views entirely; compute data once in one top-level @php block or in the controller (see dashboard/sessions.blade.php for the working pattern).

## Coordinate inputs come from the shared Leaflet location picker
Any form that writes latitude/longitude uses the shared `ResqHub.createLocationPicker()` (click-to-pin + draggable marker) plus a "use my location" geolocation button — see report/create.blade.php, dashboard/caller.blade.php, and the incident Edit details card in dashboard/show.blade.php (`Alpine.data('incidentEditForm')`). Bind the visible coordinate inputs with `x-model` and re-centre via `@change="syncMarker"` on blur so the map does not jump while typing; round with `toFixed(7)`. Pass `@js(MapLayers::all())` and do not add a second `<x-map-type-switch>` when a page already has one — the `mapLayer` store syncs every registered map.

## Maps are contained with isolation: isolate, overlays stay at z-10
Never give a map overlay a z-index above 100. Leaflet renders its panes and controls at z-index 200-1000, so `.leaflet-container` carries `isolation: isolate` (app.css) to trap them inside the map — that is why the app chrome can stay at z-30/z-40/z-50. Anything positioned *over* a map (the `<x-map-type-switch>` corner overlay, loading skeletons, the mobile Filters button) is a sibling of the map, not a descendant, so it needs only `z-10`. Reaching for z-[1000] to beat Leaflet is what makes maps paint over the sticky header and the mobile dropdown menus. UiPolishTest guards both halves.
