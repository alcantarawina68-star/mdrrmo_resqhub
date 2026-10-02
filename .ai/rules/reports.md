---
paths:
  - 'app/Support/Reports/**'
---

# Reports

## Reports are objects behind one Report contract, rendered by ReportExporter
Every dataset is a class implementing `Report` (title, filenamePrefix, headings, rows) and nothing else renders it: `ReportExporter` owns CSV (BOM, uncapped) and PDF (capped at 5000 rows). Add a new export as a new Report class plus a thin controller action, never a bespoke PDF view. Never export password hashes, session ids or tokens.

## Whitelist report filters in PHP, never pass request input to a query
Each Report declares its own allowed filter keys and per-column search/date rules, so arbitrary query-string keys and unvalidated enums cannot reach the query builder. Date bounds are computed in PHP against the app clock, per `.ai/rules/services.md`.
