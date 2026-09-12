---
paths:
  - app/Http/Controllers/UserController.php
  - app/Http/Controllers/AuthController.php
---

# Controllers

## Superadmin privilege hierarchy and guards
Superadmin is the highest UserRole and is added to every role gate (role:superadmin,admin,...) so it inherits all admin abilities. Only a superadmin can create/modify/delete superadmin accounts or assign the superadmin role; the last active superadmin cannot be demoted, deactivated, or deleted. Session management routes are superadmin-only (`role:superadmin`) and gated by `reauthenticate`.

## Remember-me stays disabled by design
Do not re-enable "Remember me" on login. SessionSecurityTest asserts both that the login view omits the checkbox and that Auth::attempt never gets remember=true. It pairs with the single-session (session_id) enforcement; remember cookies would let a session persist across browsers and bypass the one-device rule.

## Password reset kills all sessions and never auto-logs-in
Password reset intentionally does NOT auto-login: it redirects to the login page with a status banner so normal login checks (active + single-session) still apply. On success it also deletes all sessions rows for the user and clears session_id, logging the user out of every device. Keep password rules consistent with RegisterRequest (min 8, confirmed).
