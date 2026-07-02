# Recurring Events — Client Clarification (Technical Pointer)

**Status:** Pre-development — awaiting client sign-off  
**Full workbook:** [Recurring Events — Gap Analysis & Client Clarification Workbook](../../../../Docs/Issues%20Doc/30-06-2026/Recurring%20Events%20%E2%80%94%20Gap%20Analysis%20%26%20Client%20Clarification%20Workbook.md)  
**Concept source:** `Docs/Issues Doc/30-06-2026/Recurring events – Tem 29th of June.txt`

---

## Do not implement yet

Recurring events require client decisions on architecture, status model, invitations, map/SEO scope, and data model (Options A/B/C). See workbook Section 12 sign-off gates.

---

## Current state (backend)

| Item | Status |
|------|--------|
| `events_v2` single-row model | Production |
| `is_recurring` boolean | Stored; no generator |
| `recurring_series` table | **Missing** |
| `timezone` on `events_v2` | **Missing** |
| `/api/v2/recurring-series` | **Missing** |
| Instance generation service | **Missing** |
| Scheduled horizon / status jobs | **Missing** |
| Public map queries | `events_v2` via `PublicEventController` (limit 100, 2min cache) |

---

## Recommended direction (pending client approval)

**Data model:** Option C — `recurring_series` + `events_v2` rows as instances (`series_id`, `is_modified`, `timezone`).

**Public visibility:** `is_approved` + `publish_status=published` + `status` ∈ {upcoming, live} for public map.

**Phase 1 scope:** Series CRUD, weekly generator, instance edit, map/search — **exclude** SEO URLs and calendar view.

**Performance:** Raise map limit to 500 with bbox; index `(series_id, start_datetime)`; cache invalidation on series changes.

---

## Workshop priority questions (Q1–Q21)

See workbook Section 6.1. Block development until answered.

---

## Related code

- `app/Models/EventV2.php`
- `app/Services/V2/EventService.php`
- `app/Http/Controllers/V2/PublicEventController.php`
- `app/Http/Requests/V2/StoreEventRequest.php` (`is_recurring`)
