---
paths:
  - app/Http/Controllers/UserController.php
---

# Controllers

## Superadmin privilege hierarchy and guards
Superadmin is the highest UserRole and is added to every role gate (role:superadmin,admin,...) so it inherits all admin abilities. Only a superadmin can create/modify/delete superadmin accounts or assign the superadmin role; the last active superadmin cannot be demoted, deactivated, or deleted. Session management routes are superadmin-only (`role:superadmin`) and gated by `reauthenticate`.
