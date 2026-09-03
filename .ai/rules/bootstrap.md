---
paths:
  - bootstrap/app.php
---

# Bootstrap

## API-first apps must override redirectGuestsTo
Laravel 13 defaults to redirectGuestsTo(fn () => route('login')). That closure runs EAGERLY inside Authenticate::unauthenticated() whenever a request is not expectsJson() — even for api/* routes — and blows up with "Route [login] not defined" until a named login route exists. Keep bootstrap/app.php set to redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login')) so unauthenticated API calls hit the custom ApiResponse 401 render instead.
