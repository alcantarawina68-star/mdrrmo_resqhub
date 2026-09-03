---
paths:
  - 'resources/css/**'
---

# Css

## Tailwind v4: @apply component classes fails; @utility must be top-level
In Tailwind v4 you cannot `@apply` a custom component class (e.g. `.btn { @apply btn ... }` inside a sibling class) — only real utilities. To compose, define the base class with `@utility btn { @apply ... }`. `@utility` blocks cannot be nested inside `@layer components`; put them at the top level of app.css. We hit both errors during `npm run build` for the ResQHub design system (btn, input used by .btn-primary/.btn-secondary/.select).
