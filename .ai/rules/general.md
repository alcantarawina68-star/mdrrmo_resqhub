---
paths:
  - '**/*'
---

# General

## Incident detail editing has its own wider role gate
Editing recorded incident details (route `dashboard.incidents.update`, form in `resources/views/dashboard/show.blade.php`) is a field correction, not an operational decision, so it uses the wider `UserRole::incidentEditorRoles()` (superadmin, admin, encoder, responder) while verify/status/notify stay behind `operationsRoles()`. Both the route middleware and the Blade gate read that one enum helper so they cannot drift. Latitude/longitude are `nullable` so a text-only correction saves without a pin, but any value that *is* submitted must be numeric and in range. Keep `reauthenticate` on the update route.
