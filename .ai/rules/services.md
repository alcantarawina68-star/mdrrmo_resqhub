---
paths:
  - app/Services/ReportService.php
  - app/Services/BackupService.php
---

# Services

## Derive date bounds from the app clock, not the database
Never mix a stored column with MySQL `NOW()` in date arithmetic: the connection timezone is UTC while the app writes datetimes in the app timezone, so ages come out shifted by the offset (a 30-minute-old row read as ~8.5h). Compute thresholds in PHP (`now()->copy()->subHours($n)`) and pass them as bindings, the same way `summary()` compares two columns instead. When bucketing by age, bounds are ages, so timestamps invert the comparison: age [min,max) means `reported_at >= now-max AND < now-min`, and an open-ended "oldest" bucket is `reported_at < now-min`. Also note `max('reported_at')` returns the *newest* row; use `min()` for the oldest.

## Backups are built in PHP, not via mysqldump
The dump is generated in PHP on purpose: `mysqldump`/`mysql` are not guaranteed on the host. Values go through the live PDO handle so escaping always matches the producing server, and every statement is replayed in a test to prove the dump restores rows byte for byte. Never shell out to a database client to reintroduce a host dependency.
