---
paths:
  - app/Http/Middleware/RequireReauthentication.php
---

# Middleware

## Reauthentication gate: session timestamp + redirect confirm
Sensitive actions are gated by RequireReauthentication middleware ('reauthenticate' alias), which stores a `auth.password_confirmed_at` session timestamp with a 15-minute window. For POST requests it redirects to the password confirmation page, and the confirm controller redirects back to the referer so the user can re-submit the form (POST bodies can't survive a redirect). Add this middleware to any new sensitive write route; register the alias in bootstrap/app.php.
