# Recurring Events — Phase 1 Database Architecture

**Status:** Implemented (database + models + validation only — no APIs).  
**Date:** 2026-07-06

---

## Overview

Phase 1 introduces a **series + instances** model:

- **`recurring_series`** — stores the recurrence definition (type, rules, timezone, date bounds).
- **`events_v2`** — existing event rows remain unchanged when `series_id` is `NULL`; recurring instances link via `series_id`.

This matches the architecture review **Option C** and keeps map, search, invitations, and likes keyed to normal `events_v2` rows.

---

## Design decisions

### 1. `events_v2` instead of legacy `events`

The platform’s active model is **`EventV2` / `events_v2`**. All V2 APIs, map discovery, invitations, and wishlist use this table. Extending `events_v2` preserves backward compatibility: existing rows have `series_id = NULL` and `is_modified = false`.

The legacy `events` table is not modified in Phase 1.

### 2. `organizer_id` on `recurring_series`

Aligns with `events_v2.user_id` (event owner). The series owner is the organiser who defines recurrence. Named `organizer_id` per product vocabulary; FK to `users.id`.

### 3. `recurrence_type` + `recurrence_rules` (JSON)

**Why split?**

- `recurrence_type` is a stable discriminator for queries and indexes (`weekly`, `biweekly`, `monthly`, `yearly`).
- `recurrence_rules` holds type-specific payload without new columns per type.

**Phase 1 example (weekly):**

```json
{
  "weekdays": [1, 3, 5]
}
```

ISO-8601 weekdays: **1 = Monday … 7 = Sunday**.

**Future bi-weekly (no migration):**

```json
{
  "weekdays": [2, 4],
  "interval": 2
}
```

**Future monthly:**

```json
{
  "day_of_month": 15
}
```

**Future yearly:**

```json
{
  "month": 6,
  "day_of_month": 15
}
```

Validation is driven by `RecurrenceRulesSchema` — not hardcoded only in weekly code paths.

### 4. IANA timezone only

`timezone` stores identifiers such as `Europe/Amsterdam`. UTC offsets (`+02:00`, `UTC+2`) are rejected by `App\Rules\IanaTimezone`. DST is handled when materializing instances in application code (Phase 2+).

### 5. `start_date` / `end_date` on the series

- `start_date` — first calendar day the series is active (in series timezone).
- `end_date` — optional cap; `NULL` = open-ended (horizon limits enforced in app layer when generating instances).

Instance-specific dates remain on `events_v2.event_date` / `start_datetime`.

### 6. `is_modified` on `events_v2`

When an organiser edits a **single occurrence** (exception), `is_modified = true` marks that row as detached from the series template. Unmodified instances stay `false`.

### 7. `nullOnDelete` for `series_id`

If a series row is removed, instances keep existing data with `series_id` set to `NULL` (FK `nullOnDelete`). Prevents accidental mass delete of published events; Phase 2 services should define explicit delete-series semantics.

### 8. Future multiple time slots (not implemented)

Optional key in `recurrence_rules`:

```json
{
  "weekdays": [1, 3, 5],
  "time_slots": [
    { "start_time": "10:00", "end_time": "12:00" },
    { "start_time": "18:00", "end_time": "21:00" }
  ]
}
```

When `time_slots` is absent, times continue to live on each `events_v2` row (`start_time`, `end_time`, `start_datetime`, `end_datetime`). No schema change required to add multi-slot support later.

### 9. Audit columns

`created_by` and `updated_by` nullable FKs to `users` for admin/organiser audit trails.

---

## Database schema

### Table: `recurring_series`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `organizer_id` | FK → `users` | cascade on delete |
| `recurrence_type` | string(32) | `weekly`, `biweekly`, `monthly`, `yearly` |
| `recurrence_rules` | json | Type-specific rules |
| `timezone` | string(64) | IANA only |
| `start_date` | date | |
| `end_date` | date nullable | |
| `created_by` | FK → `users` nullable | |
| `updated_by` | FK → `users` nullable | |
| `created_at`, `updated_at` | timestamps | |

**Indexes:** `organizer_id`, `recurrence_type`, `start_date`, `end_date`, `(organizer_id, recurrence_type)`, `(start_date, end_date)`.

### Table: `events_v2` (added columns)

| Column | Type | Notes |
|--------|------|--------|
| `series_id` | FK → `recurring_series` nullable | `nullOnDelete` |
| `is_modified` | boolean default `false` | Exception instance flag |

**Indexes:** `series_id`, `(series_id, event_date)`, `(series_id, is_modified)`.

---

## Model relationships

```
RecurringSeries
  belongsTo User (organizer)
  belongsTo User (creator)   — created_by
  belongsTo User (updater)   — updated_by
  hasMany    EventV2 (events) — foreign key series_id

EventV2
  belongsTo RecurringSeries (recurringSeries) — series_id
```

**Note:** Product docs refer to “Event”; the implementation uses **`EventV2`** on table **`events_v2`**.

### Scopes

**RecurringSeries:** `forOrganizer`, `type`, `weekly`, `activeOn`, `overlapping`

**EventV2:** `partOfSeries`, `standalone`, `modifiedInstances`, `unmodifiedInstances`

---

## Files created

| File | Purpose |
|------|---------|
| `database/migrations/2026_07_06_100000_create_recurring_series_table.php` | Series table |
| `database/migrations/2026_07_06_100001_add_recurring_series_to_events_v2_table.php` | Instance FK + flag |
| `app/Models/RecurringSeries.php` | Eloquent model |
| `app/Support/Recurrence/RecurrenceType.php` | Type constants |
| `app/Support/Recurrence/RecurrenceRulesSchema.php` | JSON shape validation |
| `app/Rules/IanaTimezone.php` | IANA timezone rule |
| `app/Rules/RecurrenceRulesMatchType.php` | Rules matched to type |
| `app/Validation/Recurring/RecurringSeriesValidation.php` | Reusable rule sets |
| `docs/RECURRING_EVENTS_PHASE1_DATABASE.md` | This document |

## Files modified

| File | Change |
|------|--------|
| `app/Models/EventV2.php` | `series_id`, `is_modified`, relationship, scopes, helpers |

---

## Validation architecture (reusable)

Use in future FormRequests without duplicating logic:

```php
use App\Validation\Recurring\RecurringSeriesValidation;

public function rules(): array
{
    return RecurringSeriesValidation::createRules();
}
```

Components:

- **`IanaTimezone`** — rejects offsets; validates against `DateTimeZone::listIdentifiers()`.
- **`RecurrenceRulesMatchType`** — validates `recurrence_rules` for the request’s `recurrence_type`.
- **`RecurrenceRulesSchema`** — central schema per type (usable outside HTTP).
- **`RecurringSeriesValidation`** — `createRules()`, `updateRules()`, `eventInstanceRules()`.

---

## Potential future expansion (no redesign needed)

| Feature | How |
|---------|-----|
| Bi-weekly / monthly / yearly | New `recurrence_type` + rules keys; extend `RecurrenceRulesSchema` |
| Multiple time slots | `recurrence_rules.time_slots[]` or per-instance times on `events_v2` |
| Series template payload | Optional JSON column on series **or** reuse first instance as template (Phase 2 decision) |
| Materialization job | Read series + rules → insert/update `events_v2` rows |
| Exception edits | Set `is_modified = true` on touched instance |
| Admin moderation | Filter `recurring_series` by organiser/type; approve instances as today |
| Performance | Indexes on `(series_id, event_date)` for map/search date windows |

---

## Running migrations

```bash
php artisan migrate
```

Rollback:

```bash
php artisan migrate:rollback --step=2
```

---

## Out of scope (Phase 1)

- HTTP APIs / controllers
- Instance materialization service
- Scheduled jobs
- Frontend changes
- Admin React UI

---

## Related documents

- `docs/RECURRING_EVENTS_CLIENT_CLARIFICATION.md`
- Architecture review (Option C: series + `events_v2` instances)
