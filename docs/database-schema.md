# Database Schema

CEIRIS (Community Emergency Incident Reporting and Information System) — MySQL schema generated from Laravel migrations in `database/migrations`.

- [ER Diagram](./er-diagram.md)
- All foreign keys use `constrained()`, which references the `id` column of the target table.
- Timestamps (`created_at` / `updated_at`) use the standard Laravel `TIMESTAMP` type.

---

## Table: `users`

User accounts for all system roles.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| name | VARCHAR(120) | No | | |
| email | VARCHAR(190) | No | | UNIQUE |
| email_verified_at | TIMESTAMP | Yes | `NULL` | |
| password | VARCHAR(255) | No | | |
| role | ENUM | No | `community_user` | See [User Role enum](#user-role) |
| contact_number | VARCHAR(20) | Yes | `NULL` | |
| barangay | VARCHAR(100) | Yes | `NULL` | |
| status | ENUM | No | `active` | See [User Status enum](#user-status) |
| remember_token | VARCHAR(100) | Yes | `NULL` | |
| created_at | TIMESTAMP | Yes | | |
| updated_at | TIMESTAMP | Yes | | |

**Indexes:** `role`, `users_email_unique`.

---

## Table: `password_reset_tokens`

Password reset tokens for users.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| email | VARCHAR(190) | No | | PRIMARY KEY |
| token | VARCHAR(255) | No | | |
| created_at | TIMESTAMP | Yes | `NULL` | |

---

## Table: `sessions`

Session storage for web users.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | VARCHAR(255) | No | | PRIMARY KEY |
| user_id | BIGINT (FK) | Yes | `NULL` | → `users.id` |
| ip_address | VARCHAR(45) | Yes | `NULL` | |
| user_agent | TEXT | Yes | `NULL` | |
| payload | LONGTEXT | No | | |
| last_activity | INT | No | | |

**Indexes:** `sessions_user_id_index`, `sessions_last_activity_index`.

---

## Table: `incidents`

Emergency incidents reported by community users or by phone (caller-based).

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| user_id | BIGINT (FK) | No | | → `users.id`, RESTRICT on delete |
| incident_type | ENUM | No | | See [Incident Type enum](#incident-type) |
| description | TEXT | No | | |
| latitude | DECIMAL(10,7) | No | | |
| longitude | DECIMAL(10,7) | No | | |
| location_label | VARCHAR(255) | Yes | `NULL` | Human-readable address |
| source | ENUM | No | | See [Incident Source enum](#incident-source) |
| caller_name | VARCHAR(120) | Yes | `NULL` | Caller-based incidents |
| caller_contact | VARCHAR(20) | Yes | `NULL` | Caller-based incidents |
| is_anonymous | BOOLEAN | No | `0` | |
| status | ENUM | No | `under_verification` | See [Incident Status enum](#incident-status) |
| assigned_unit | VARCHAR(120) | Yes | `NULL` | Assigned responder/unit |
| reported_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |
| verified_at | TIMESTAMP | Yes | `NULL` | |
| resolved_at | TIMESTAMP | Yes | `NULL` | Set only when the status becomes `closed` |
| created_at | TIMESTAMP | Yes | | |
| updated_at | TIMESTAMP | Yes | | |

**Indexes:** `user_id`, `incident_type`, `status`, `source`, `reported_at`, `latitude` + `longitude`.

**Relationships:** one-to-many → `evidence`, `status_logs`.

---

## Table: `evidence`

Media files (photos, videos, documents) attached to an incident.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| incident_id | BIGINT (FK) | No | | → `incidents.id`, CASCADE on delete |
| file_path | VARCHAR(255) | No | | Stored file path |
| file_type | VARCHAR(50) | No | | MIME type |
| original_name | VARCHAR(255) | No | | |
| file_size | INT UNSIGNED | No | | Bytes |
| uploaded_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |

**Indexes:** `incident_id`.

---

## Table: `status_logs`

Audit trail of status changes for each incident.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| incident_id | BIGINT (FK) | No | | → `incidents.id`, CASCADE on delete |
| user_id | BIGINT (FK) | No | | → `users.id`, RESTRICT on delete |
| old_status | VARCHAR(30) | No | | Previous status |
| new_status | VARCHAR(30) | No | | New status |
| note | TEXT | Yes | `NULL` | |
| created_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |

**Indexes:** `incident_id`.

---

## Table: `announcements`

Public announcements/advisories published to the community.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| user_id | BIGINT (FK) | No | | → `users.id`, RESTRICT on delete |
| title | VARCHAR(200) | No | | |
| content | TEXT | No | | |
| category | ENUM | No | | See [Announcement Category enum](#announcement-category) |
| severity | ENUM | No | `info` | See [Severity enum](#severity) |
| published_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |
| expires_at | TIMESTAMP | Yes | `NULL` | |
| created_at | TIMESTAMP | Yes | | |
| updated_at | TIMESTAMP | Yes | | |

**Indexes:** `published_at`.

---

## Table: `sms_messages`

Outbound SMS queue for notifying residents.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| phone | VARCHAR(20) | No | | Recipient number |
| message | TEXT | No | | |
| status | VARCHAR(15) | No | `pending` | e.g. `pending`, `sent`, `failed` |
| attempts | TINYINT UNSIGNED | No | `1` | |
| error | TEXT | Yes | `NULL` | Last error message |
| sent_at | TIMESTAMP | Yes | `NULL` | |
| created_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |

**Indexes:** `phone`, `status`, `created_at`.

---

## Table: `cache`

Laravel cache store.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| key | VARCHAR(255) | No | | PRIMARY KEY |
| value | MEDIUMTEXT | No | | |
| expiration | BIGINT | No | | |

---

## Table: `cache_locks`

Laravel cache locks.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| key | VARCHAR(255) | No | | PRIMARY KEY |
| owner | VARCHAR(255) | No | | |
| expiration | BIGINT | No | | |

---

## Table: `jobs`

Laravel queue jobs.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| queue | VARCHAR(255) | No | | |
| payload | LONGTEXT | No | | |
| attempts | SMALLINT UNSIGNED | No | | |
| reserved_at | INT UNSIGNED | Yes | `NULL` | |
| available_at | INT UNSIGNED | No | | |
| created_at | INT UNSIGNED | No | | |

---

## Table: `job_batches`

Laravel batch jobs.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | VARCHAR(255) | No | | PRIMARY KEY |
| name | VARCHAR(255) | No | | |
| total_jobs | INT | No | | |
| pending_jobs | INT | No | | |
| failed_jobs | INT | No | | |
| failed_job_ids | LONGTEXT | No | | |
| options | MEDIUMTEXT | Yes | `NULL` | |
| cancelled_at | INT | Yes | `NULL` | |
| created_at | INT | No | | |
| finished_at | INT | Yes | `NULL` | |

---

## Table: `failed_jobs`

Laravel failed job records.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| uuid | VARCHAR(255) | No | | UNIQUE |
| connection | TEXT | No | | |
| queue | TEXT | No | | |
| payload | LONGTEXT | No | | |
| exception | LONGTEXT | No | | |
| failed_at | TIMESTAMP | No | `CURRENT_TIMESTAMP` | |

---

## Table: `personal_access_tokens`

Laravel Sanctum API tokens.

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | BIGINT (PK, auto) | No | | |
| tokenable_type | VARCHAR(255) | No | | Morph (polymorphic) |
| tokenable_id | BIGINT UNSIGNED | No | | Morph (polymorphic) |
| name | VARCHAR(255) | No | | |
| token | VARCHAR(64) | No | | UNIQUE, hashed |
| abilities | TEXT | Yes | `NULL` | |
| last_used_at | TIMESTAMP | Yes | `NULL` | |
| expires_at | TIMESTAMP | Yes | `NULL` | |
| created_at | TIMESTAMP | Yes | | |
| updated_at | TIMESTAMP | Yes | | |

**Indexes:** `tokenable_type` + `tokenable_id`, `expires_at`, `personal_access_tokens_token_unique`.

---

## Enums

### User Role
| Value | Label |
| --- | --- |
| `admin` | Administrator |
| `encoder` | Encoder / Dispatcher |
| `barangay_official` | Barangay Official |
| `responder` | Responder |
| `community_user` | Community User |

### User Status
| Value | Label |
| --- | --- |
| `active` | Active |
| `inactive` | Inactive |
| `suspended` | Suspended |

### Incident Type
Types are grouped by category in every selector. The `typhoon_flood` value is
legacy: it is still stored and displayed for historical records, but it cannot
be selected or submitted.

| Value | Label | Category | Selectable |
| --- | --- | --- | --- |
| `typhoon` | Typhoon | Disaster Risk | Yes |
| `flood` | Flood | Disaster Risk | Yes |
| `earthquake` | Earthquake | Disaster Risk | Yes |
| `landslide` | Landslide | Disaster Risk | Yes |
| `vehicular_accident` | Vehicular Accident | Incidents | Yes |
| `fire` | Fire | Incidents | Yes |
| `drowning` | Drowning | Incidents | Yes |
| `hazmat` | Hazardous Materials | Incidents | Yes |
| `ems` | Emergency Medical Services | Incidents | Yes |
| `patient_transport` | Patient Transport | Incidents | Yes |
| `others` | Others | Others | Yes |
| `typhoon_flood` | Typhoon / Flood | Disaster Risk | No (legacy) |

### Incident Status
`new` and `resolved` were retired. `closed` is the terminal status and is the
only status that populates `resolved_at`.

| Value | Label | Public Map |
| --- | --- | --- |
| `under_verification` | Under Verification | No |
| `verified` | Verified | Yes |
| `ongoing` | Ongoing | Yes |
| `closed` | Closed | Yes |
| `rejected` | Rejected | No |

### Incident Source
| Value | Label |
| --- | --- |
| `online` | Online |
| `caller_based` | Caller-Based |

### Announcement Category
| Value | Label |
| --- | --- |
| `advisory` | Advisory |
| `warning` | Warning |
| `safety_info` | Safety Information |
| `incident_update` | Incident Update |

### Severity
| Value | Label |
| --- | --- |
| `info` | Info |
| `caution` | Caution |
| `urgent` | Urgent |

---

## Relationships Summary

| From | To | Type | On Delete |
| --- | --- | --- | --- |
| `incidents.user_id` | `users.id` | Many-to-One | RESTRICT |
| `evidence.incident_id` | `incidents.id` | Many-to-One | CASCADE |
| `status_logs.incident_id` | `incidents.id` | Many-to-One | CASCADE |
| `status_logs.user_id` | `users.id` | Many-to-One | RESTRICT |
| `announcements.user_id` | `users.id` | Many-to-One | RESTRICT |
| `sessions.user_id` | `users.id` | Many-to-One | — |
| `personal_access_tokens.tokenable` | polymorphic | Morph-To | — |
