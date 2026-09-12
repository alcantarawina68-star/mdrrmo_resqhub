---
paths:
  - 'resources/views/**'
---

# Views

## No inline @php(...) in views; group data once at the top
Blade trap: an inline @php(...) directive (single-line with parens) followed later by more @-directives gets mangled by Blade's paren-balancing pass — the rest of the template compiles to literal text and throws "Undefined variable". Symptom: compiled view keeps raw @foreach/@php text. Avoid inline @php(...) and per-loop @php blocks in views entirely; compute data once in one top-level @php block or in the controller (see dashboard/sessions.blade.php for the working pattern).
