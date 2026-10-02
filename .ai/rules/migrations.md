---
paths:
  - 'database/migrations/**'
---

# Migrations

## Inline enum values in migrations; deleting an enum referenced by a run migration breaks fresh migrate
Migrations here call enum helpers (e.g. `IncidentStatus::values()`) to build columns. Those calls execute at `migrate` time, so deleting an enum that a committed migration references breaks fresh installs and `RefreshDatabase`, even if the migration already ran on the live DB. When retiring an enum, grep the whole `database/migrations/` directory and inline the literal values in any referencing migration. The `drop_priority_column_from_incidents_table` migration is self-contained for the same reason.

## Enum-narrowing migrations must remap existing rows first
Tests migrate a fresh `resqhub_test` DB with no seeded rows, so a migration that narrows a MySQL enum looks green while it hard-fails on the populated live DB (MySQL refuses to truncate a surviving value: `SQLSTATE[01000] 1265 Data truncated`). This is how `remove_barangay_official_role_from_users_table` shipped broken. Before any `->change()` that removes an enum value, UPDATE existing rows onto a surviving value first, and run the migration against the live DB before considering it done.
