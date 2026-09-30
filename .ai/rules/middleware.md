---
paths:
  - app/Http/Middleware/RequireReauthentication.php
---

# Middleware

## Reauthentication gate: session timestamp + redirect confirm
Sensitive actions are gated by RequireReauthentication middleware ('reauthenticate' alias), which stores a `auth.password_confirmed_at` session timestamp with a 15-minute window. For POST requests it redirects to the password confirmation page, and the confirm controller redirects back to the referer so the user can re-submit the form (POST bodies can't survive a redirect). Add this middleware to any new sensitive write route; register the alias in bootstrap/app.php.

## Defer state-changing requests instead of discarding them
All 13 `reauthenticate` routes are POST/DELETE, so this middleware defers them: it stashes method + `getRequestUri()` + payload under `auth.pending_request`, and `PasswordConfirmController@resume` replays it via a self-submitting form (a real GET route, not a kernel re-dispatch, so middleware/validation/`back()` behave normally). `auth.pending_request_consumed` prevents a validation failure that bounces back to the resume page from resubmitting in a loop. Uploads and non-scalar payloads are deliberately NOT deferred and fall back to redirecting to `auth.redirect_to`. Session driver must stay `database` — the deferred payload (descriptions up to 10k chars) would overflow a cookie session.
