---
paths:
  - routes/web.php
---

# Routes

## Static sub-routes must be registered before `{param}` routes
A route like `/incidents/{incident}` registered before `/incidents/export` swallows it: the binding fails and the user gets a 404 instead of the export. Register static export/download paths above the dynamic sibling, and prove it with `php artisan route:list --path=incidents`.
