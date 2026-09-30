# Entity Relationship Diagram

Mermaid ER diagram of the CEIRIS database schema. Renders natively on GitHub, GitLab, and in Mermaid-supported editors. A standalone `.mmd` version is also available as [er-diagram.mmd](./er-diagram.mmd).

```mermaid
erDiagram
    users ||--o{ incidents : "reports"
    users ||--o{ status_logs : "logs"
    users ||--o{ announcements : "publishes"
    users ||--o{ sessions : "has"
    incidents ||--o{ evidence : "has"
    incidents ||--o{ status_logs : "tracked by"

    users {
        bigint id PK
        varchar name "VARCHAR(120)"
        varchar email "VARCHAR(190) UNIQUE"
        timestamp email_verified_at
        varchar password
        enum role "admin|encoder|barangay_official|responder|community_user"
        varchar contact_number "VARCHAR(20)"
        varchar barangay "VARCHAR(100)"
        enum status "active|inactive|suspended"
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }

    incidents {
        bigint id PK
        bigint user_id FK
        enum incident_type "see IncidentType"
        text description
        decimal latitude "DECIMAL(10,7)"
        decimal longitude "DECIMAL(10,7)"
        varchar location_label "VARCHAR(255)"
        enum source "online|caller_based"
        varchar caller_name "VARCHAR(120)"
        varchar caller_contact "VARCHAR(20)"
        boolean is_anonymous
        enum status "under_verification|verified|ongoing|closed|rejected"
        varchar assigned_unit "VARCHAR(120)"
        timestamp reported_at
        timestamp verified_at
        timestamp resolved_at
        timestamp created_at
        timestamp updated_at
    }

    evidence {
        bigint id PK
        bigint incident_id FK
        varchar file_path
        varchar file_type "VARCHAR(50)"
        varchar original_name
        unsignedint file_size
        timestamp uploaded_at
    }

    status_logs {
        bigint id PK
        bigint incident_id FK
        bigint user_id FK
        varchar old_status "VARCHAR(30)"
        varchar new_status "VARCHAR(30)"
        text note
        timestamp created_at
    }

    announcements {
        bigint id PK
        bigint user_id FK
        varchar title "VARCHAR(200)"
        text content
        enum category "advisory|warning|safety_info|incident_update"
        enum severity "info|caution|urgent"
        timestamp published_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    sms_messages {
        bigint id PK
        varchar phone "VARCHAR(20)"
        text message
        varchar status "VARCHAR(15) DEFAULT pending"
        tinyint attempts "TINYINT UNSIGNED DEFAULT 1"
        text error
        timestamp sent_at
        timestamp created_at
    }

    sessions {
        varchar id PK
        bigint user_id FK
        varchar ip_address "VARCHAR(45)"
        text user_agent
        longtext payload
        int last_activity
    }

    password_reset_tokens {
        varchar email PK "VARCHAR(190)"
        varchar token
        timestamp created_at
    }

    personal_access_tokens {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        varchar name
        varchar token "VARCHAR(64) UNIQUE"
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    cache {
        varchar key PK
        mediumtext value
        bigint expiration
    }

    cache_locks {
        varchar key PK
        varchar owner
        bigint expiration
    }

    jobs {
        bigint id PK
        varchar queue
        longtext payload
        smallint attempts "SMALLINT UNSIGNED"
        unsignedint reserved_at
        unsignedint available_at
        unsignedint created_at
    }

    job_batches {
        varchar id PK
        varchar name
        int total_jobs
        int pending_jobs
        int failed_jobs
        longtext failed_job_ids
        mediumtext options
        int cancelled_at
        int created_at
        int finished_at
    }

    failed_jobs {
        bigint id PK
        varchar uuid UNIQUE
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
    }
```

## Cardinality

| Relationship | Cardinality | Meaning |
| --- | --- | --- |
| `users → incidents` | 1 : 0..N | A user reports many incidents; an incident belongs to one user (RESTRICT). |
| `incidents → evidence` | 1 : 0..N | An incident has many evidence files (CASCADE). |
| `incidents → status_logs` | 1 : 0..N | An incident has many status log entries (CASCADE). |
| `users → status_logs` | 1 : 0..N | A user records many status changes (RESTRICT). |
| `users → announcements` | 1 : 0..N | A user publishes many announcements (RESTRICT). |
| `users → sessions` | 1 : 0..N | A user has many sessions. |
| `personal_access_tokens.tokenable` | polymorphic | Token belongs to any model (morph-to). |
