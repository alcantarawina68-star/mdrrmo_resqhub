---
paths:
  - app/Models/EvidenceFile.php
---

# Models

## Evidence image bytes live in evidence_files, never on the public disk
Evidence bytes are stored in the `evidence_files` table (LONGBLOB on MySQL via a driver-guarded ALTER, longText elsewhere) and streamed by the public `evidence.image` route, because cPanel cannot be relied on to create the `public/storage` symlink. Keep blobs in that separate table, never as a column on `evidence`, so ordinary evidence queries do not pull megabytes. New uploads must not call `->store()`. `evidence.file_path` keeps the generated name for traceability only. Legacy rows still on disk are migrated with `php artisan evidence:store-in-database`. BackupService's `files/` section no longer contains new uploads because the bytes are already in dump.sql.
