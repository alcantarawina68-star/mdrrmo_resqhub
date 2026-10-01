---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Incident alerts fan out by role now, by unit later
v1 recipients are active `UserRole::operationsRoles()` (admin/encoder/superadmin) minus the acting user, resolved in `IncidentService::notifyOperations()`. Unit-targeted alerts are deliberately deferred: `incidents.assigned_unit` is still free-text varchar with no units or membership model, so there is nothing addressable to notify. When units land, `IncidentAssigned` should additionally target the assigned unit's members. SMS delivery failures are a separate concern and must not be coupled to in-app alerts.
