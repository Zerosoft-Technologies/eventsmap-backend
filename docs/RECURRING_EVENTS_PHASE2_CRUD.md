# Recurring Events — Phase 2 CRUD

Phase 2 delivers REST CRUD for `recurring_series`, Vue management UI, and admin moderation. **Event instance generation is out of scope.**

## New files

### Backend (`eventsmap-backend`)

| File | Purpose |
|------|---------|
| `database/migrations/2026_07_06_100002_add_approval_to_recurring_series_table.php` | `is_approved`, `approved_at`, `approved_by` |
| `app/Support/Premium/PremiumAccess.php` | Shared premium entitlement checks |
| `app/Policies/RecurringSeriesPolicy.php` | Owner-only update/delete; premium create |
| `app/Services/V2/RecurringSeriesService.php` | Create/update/delete in DB transactions |
| `app/Http/Controllers/V2/RecurringSeriesController.php` | User-facing REST API |
| `app/Http/Controllers/Admin/AdminRecurringSeriesController.php` | Admin list/view/delete/approve |
| `app/Http/Requests/V2/StoreRecurringSeriesRequest.php` | Create validation + authorize |
| `app/Http/Requests/V2/UpdateRecurringSeriesRequest.php` | Update validation + authorize |
| `app/Http/Resources/V2/RecurringSeriesResource.php` | V2 JSON shape |
| `app/Http/Resources/Admin/AdminRecurringSeriesResource.php` | Admin JSON shape |

### Frontend Vue (`eventsmap-frontend`)

| File | Purpose |
|------|---------|
| `src/api/recurringSeries.ts` | API client |
| `src/components/recurring/RecurringSeriesFormFields.vue` | Shared form fields |
| `src/pages/packages/RecurringSeriesList.vue` | Series listing + delete confirm |
| `src/pages/packages/RecurringSeriesForm.vue` | Create / edit form |

### Admin React (`zst_eventsmap_admin_panel`)

| File | Purpose |
|------|---------|
| `src/services/api/recurringSeriesService.js` | Admin API client |
| `src/views/recurring-series/RecurringSeriesList.js` | List, filter, approve, delete |
| `src/views/recurring-series/RecurringSeriesDetail.js` | Detail view + moderation |

## Modified files

### Backend

- `app/Models/RecurringSeries.php` — approval fields, `isOwner()`, scopes
- `app/Validation/Recurring/RecurringSeriesValidation.php` — `v2CreateRules()`, `v2UpdateRules()`
- `app/Providers/AppServiceProvider.php` — policy registration
- `routes/v2.php` — recurring series routes
- `routes/admin.php` — admin recurring series routes
- `app/Http/Controllers/GalleryImageController.php` — uses `PremiumAccess` (no duplicated checks)

### Frontend

- `src/router/index.ts` — routes under `/create-event-premium/recurring-series`
- `src/pages/packages/CreateEventPremium.vue` — sidebar “Recurring” nav item

### Admin

- `src/services/api/index.js`
- `src/routes.js`
- `src/_nav.js`

## API endpoints

### V2 (auth: `auth:sanctum`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/v2/recurring-series` | List current user's series (paginated) |
| `POST` | `/api/v2/recurring-series` | Create series (**+ `premium.active` middleware**) |
| `GET` | `/api/v2/recurring-series/{id}` | View one series (owner) |
| `PUT` | `/api/v2/recurring-series/{id}` | Update series (owner) |
| `DELETE` | `/api/v2/recurring-series/{id}` | Delete series (owner) |

### Admin (auth: `auth:sanctum`, `admin`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin/recurring-series` | List all series |
| `GET` | `/api/admin/recurring-series/stats` | Dashboard counts |
| `GET` | `/api/admin/recurring-series/{id}` | View detail |
| `DELETE` | `/api/admin/recurring-series/{id}` | Delete |
| `POST` | `/api/admin/recurring-series/{id}/approve` | Approve |
| `POST` | `/api/admin/recurring-series/{id}/unapprove` | Unapprove |

### Response envelope (V2)

```json
{
  "success": true,
  "message": "...",
  "data": { }
}
```

Validation errors: `422` with `{ success: false, message, errors }`.

## Validation rules

**Create (`v2CreateRules`)**

| Field | Rules |
|-------|--------|
| `recurrence_type` | required; one of `weekly`, `biweekly`, `monthly`, `yearly` |
| `recurrence_rules` | required array; shape validated by `RecurrenceRulesMatchType` |
| `timezone` | required IANA string (`IanaTimezone` rule) |
| `start_date` | required date |
| `end_date` | optional date, `after_or_equal:start_date` |

`organizer_id` is set server-side from the authenticated user.

**Update (`v2UpdateRules`)** — same fields as create, all `sometimes`; `organizer_id` not accepted.

**Weekly example**

```json
{
  "recurrence_type": "weekly",
  "recurrence_rules": { "weekdays": [1, 3, 5] },
  "timezone": "Europe/Amsterdam",
  "start_date": "2026-07-01",
  "end_date": null
}
```

Weekdays: `1` = Monday … `7` = Sunday.

## Authorization flow

```
Create:
  auth:sanctum → premium.active (payment) → StoreRecurringSeriesRequest::authorize()
    → RecurringSeriesPolicy::create() → PremiumAccess::isEntitled()

View list:
  auth:sanctum → scoped to organizer_id = auth user id

View / Update / Delete:
  auth:sanctum → RecurringSeriesPolicy (owner only on V2)

Admin:
  auth:sanctum + admin middleware → full list, approve, delete (no owner check)
```

## Frontend flow (Vue)

1. Premium user opens **Create Event Premium** sidebar → **Recurring**.
2. **List** (`/create-event-premium/recurring-series`) loads `GET /v2/recurring-series`.
3. **Create** (`/create-event-premium/recurring-series/create`) posts to `POST /v2/recurring-series`.
4. **Edit** (`/create-event-premium/recurring-series/:id/edit`) loads `GET` then `PUT`.
5. **Delete** uses `ConfirmDialog` → `DELETE /v2/recurring-series/{id}`.

Routes use `meta: { requiresAuth, requiresPremium }`.

## Admin flow (React)

1. Nav **Recurring Series** → `/recurring-series`.
2. List with search (organizer email/name), approval filter, stats cards.
3. Row actions: View, Approve/Unapprove, Delete.
4. Detail page shows schedule, rules JSON, organizer, moderation actions.

## Migrations

Run with database available:

```bash
php artisan migrate
```

Applies Phase 1 tables (if not yet run) plus `2026_07_06_100002_add_approval_to_recurring_series_table`.

## Explicitly not implemented

- Event instance materialization / generation job
- Linking new series to `events_v2` rows at create time
- Bulk admin approve/delete for recurring series
