---
paths:
  - resources/views/components/layouts/dashboard.blade.php
---

# Layouts

## Superadmin excludes caller/announcements/reports
Caller Report, Announcements, and Reports & Analytics are removed for the superadmin only. Gate the sidebar/nav on $isOperator (Admin+Encoder) not $isOperations, and keep those routes in a role:admin,encoder group in routes/web.php. The superadmin overview (dashboard.index) instead shows user + session analytics built by DashboardController::superadminAnalytics(). Other roles keep the features.
