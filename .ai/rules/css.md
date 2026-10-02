---
paths:
  - 'resources/css/**'
  - resources/css/app.css
---

# Css

## Tailwind v4: @apply component classes fails; @utility must be top-level
In Tailwind v4 you cannot `@apply` a custom component class (e.g. `.btn { @apply btn ... }` inside a sibling class) — only real utilities. To compose, define the base class with `@utility btn { @apply ... }`. `@utility` blocks cannot be nested inside `@layer components`; put them at the top level of app.css. We hit both errors during `npm run build` for the ResQHub design system (btn, input used by .btn-primary/.btn-secondary/.select).

## Mobile overlays layer above .bottom-nav and contain overscroll
Modal surfaces on mobile must layer above `.bottom-nav` (rendered with z-50 from the app layout), and must contain their own scroll. `.bottom-sheet-overlay` is z-[55] and `.bottom-sheet` is z-[60] so the tab bar cannot paint over the open sheet or stay tappable through the scrim; the sheet carries `overscroll-contain` and the `bottomSheet` store toggles `body.bottom-sheet-open` (overflow hidden + overscroll-behavior none). The live map is exactly one viewport tall, so leftover document scroll is what drags the map out from under the sheet — any new overlay that hides page content behind it needs the same two properties. UiPolishTest guards this contract.
