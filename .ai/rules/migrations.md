---
paths:
  - 'database/migrations/**'
---

# Migrations

## Inline enum values in migrations; deleting an enum referenced by a run migration breaks fresh migrate
Migrations here call enum helpers (e.g. `IncidentStatus::values()`) to build columns. Those calls execute at `migrate` time, so deleting an enum that a committed migration references breaks fresh installs and `RefreshDatabase`, even if the migration already ran on the live DB. When retiring an enum, grep the whole `database/migrations/` directory and inline the literal values in any referencing migration. The `drop_priority_column_from_incidents_table` migration is self-contained for the same reason.
