# Super-Admin Panel Blueprint
## Part 7: Data Ownership & Control Matrix

---

# 10. DATA OWNERSHIP & CONTROL MATRIX

## 10.1 Ownership Categories

| Category | Definition | Examples |
|----------|------------|----------|
| **Admin-Controlled** | Data entered/modified by admin users | title, description, status |
| **System-Controlled** | Data computed/set by the system | id, created_at, location_point |
| **Frontend-Only** | Transient UI state, not persisted | form validation state, UI filters |

---

## 10.2 Event Fields Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `id` | System | ❌ | Auto-generated UUID | Primary key |
| `title` | Admin | ✅ | Admin input | Required |
| `slug` | Admin | ✅ | Auto-generated, editable | Unique |
| `status` | Admin | ✅ | Status transitions | Enum |
| `category_id` | Admin | ✅ | Category selector | FK |
| `subcategory_id` | Admin | ✅ | Subcategory selector | FK, nullable |
| `event_type` | Admin | ✅ | Dropdown | Enum |
| `is_featured` | Admin | ✅ | Toggle | Boolean |
| `is_visible` | Admin | ✅ | Toggle | Boolean |
| `short_description` | Admin | ✅ | Textarea | Required |
| `description` | Admin | ✅ | Rich text editor | Required |
| `highlights` | Admin | ✅ | Repeatable input | JSON array |
| `requirements` | Admin | ✅ | Repeatable input | JSON array |
| `additional_info` | Admin | ✅ | Rich text editor | Nullable |
| `age_restriction` | Admin | ✅ | Dropdown | Enum |
| `dress_code` | Admin | ✅ | Text input | Nullable |
| `accessibility_info` | Admin | ✅ | Textarea | Nullable |
| `start_date` | Admin | ✅ | DateTime picker | Required |
| `end_date` | Admin | ✅ | DateTime picker | Required |
| `timezone` | Admin | ✅ | Timezone selector | Required |
| `is_all_day` | Admin | ✅ | Toggle | Boolean |
| `is_recurring` | Admin | ✅ | Toggle | Boolean |
| `recurrence_rule` | Admin | ✅ | RRULE builder | JSON |
| `venue_name` | Admin | ✅ | Text input | Conditional |
| `venue_address` | Admin | ✅ | Address input | Conditional |
| `city` | Admin | ✅ | Text input | Conditional |
| `state` | Admin | ✅ | Text input | Nullable |
| `country` | Admin | ✅ | Country selector | Conditional |
| `postal_code` | Admin | ✅ | Text input | Nullable |
| `latitude` | Admin | ✅ | Map picker / auto | Conditional |
| `longitude` | Admin | ✅ | Map picker / auto | Conditional |
| `location_point` | System | ❌ | Computed from lat/lng | PostGIS |
| `virtual_url` | Admin | ✅ | URL input | Conditional |
| `virtual_platform` | Admin | ✅ | Text input | Nullable |
| `primary_image_id` | Admin | ✅ | Media picker | FK |
| `is_ticketed` | Admin | ✅ | Toggle | Boolean |
| `is_free` | Admin | ✅ | Toggle | Boolean |
| `capacity` | Admin | ✅ | Number input | Nullable |
| `registration_url` | Admin | ✅ | URL input | Nullable |
| `registration_deadline` | Admin | ✅ | DateTime picker | Nullable |
| `meta_title` | Admin | ✅ | Text input | Nullable |
| `meta_description` | Admin | ✅ | Textarea | Nullable |
| `meta_keywords` | Admin | ✅ | Tag input | JSON array |
| `og_title` | Admin | ✅ | Text input | Nullable |
| `og_description` | Admin | ✅ | Textarea | Nullable |
| `og_image_id` | Admin | ✅ | Media picker | FK |
| `canonical_url` | Admin | ✅ | URL input | Nullable |
| `no_index` | Admin | ✅ | Toggle | Boolean |
| `structured_data` | System | ❌ | Auto-generated | JSON-LD |
| `internal_notes` | Admin | ✅ | Textarea | Nullable |
| `external_id` | Admin | ✅ | Text input | Nullable |
| `source` | Admin | ✅ | Text input | Nullable |
| `contact_email` | Admin | ✅ | Email input | Nullable |
| `contact_phone` | Admin | ✅ | Phone input | Nullable |
| `website_url` | Admin | ✅ | URL input | Nullable |
| `social_links` | Admin | ✅ | Social links form | JSON |
| `tags` | Admin | ✅ | Tag input | JSON array |
| `custom_fields` | Admin | ✅ | Key-value editor | JSON |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |
| `deleted_at` | System | ❌ | Soft delete | Timestamp |
| `published_at` | System | ❌ | Set on publish | Timestamp |
| `featured_at` | System | ❌ | Set on feature | Timestamp |
| `cancelled_at` | System | ❌ | Set on cancel | Timestamp |
| `archived_at` | System | ❌ | Set on archive | Timestamp |

---

## 10.3 Talent Fields Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `id` | System | ❌ | Auto-generated UUID | Primary key |
| `name` | Admin | ✅ | Text input | Required |
| `slug` | Admin | ✅ | Auto-generated, editable | Unique |
| `type` | Admin | ✅ | Dropdown | Enum |
| `bio` | Admin | ✅ | Rich text editor | Nullable |
| `short_bio` | Admin | ✅ | Textarea | Nullable |
| `profile_image_id` | Admin | ✅ | Media picker | FK |
| `cover_image_id` | Admin | ✅ | Media picker | FK |
| `website_url` | Admin | ✅ | URL input | Nullable |
| `social_links` | Admin | ✅ | Social links form | JSON |
| `genres` | Admin | ✅ | Tag input | JSON array |
| `is_active` | Admin | ✅ | Toggle | Boolean |
| `is_verified` | Admin | ✅ | Toggle | Boolean |
| `contact_email` | Admin | ✅ | Email input | Nullable |
| `management_info` | Admin | ✅ | Textarea | Nullable |
| `internal_notes` | Admin | ✅ | Textarea | Nullable |
| `meta_title` | Admin | ✅ | Text input | Nullable |
| `meta_description` | Admin | ✅ | Textarea | Nullable |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |
| `deleted_at` | System | ❌ | Soft delete | Timestamp |

---

## 10.4 Event-Talent Pivot Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `event_id` | System | ❌ | Relation | FK |
| `talent_id` | Admin | ✅ | Talent selector | FK |
| `role` | Admin | ✅ | Text input | Nullable |
| `billing_order` | Admin | ✅ | Drag-drop reorder | Integer |
| `is_headliner` | Admin | ✅ | Toggle | Boolean |
| `performance_time` | Admin | ✅ | Time picker | Nullable |
| `notes` | Admin | ✅ | Textarea | Nullable |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |

---

## 10.5 Category Fields Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `id` | System | ❌ | Auto-generated UUID | Primary key |
| `name` | Admin | ✅ | Text input | Required |
| `slug` | Admin | ✅ | Auto-generated, editable | Unique |
| `description` | Admin | ✅ | Textarea | Nullable |
| `icon` | Admin | ✅ | Icon picker | Nullable |
| `image_id` | Admin | ✅ | Media picker | FK |
| `color` | Admin | ✅ | Color picker | Nullable |
| `display_order` | Admin | ✅ | Drag-drop reorder | Integer |
| `is_active` | Admin | ✅ | Toggle | Boolean |
| `is_featured` | Admin | ✅ | Toggle | Boolean |
| `parent_id` | Admin | ✅ | Category selector | FK |
| `meta_title` | Admin | ✅ | Text input | Nullable |
| `meta_description` | Admin | ✅ | Textarea | Nullable |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |

---

## 10.6 Media Fields Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `id` | System | ❌ | Auto-generated UUID | Primary key |
| `file_name` | System | ❌ | From upload | String |
| `file_path` | System | ❌ | Generated path | String |
| `disk` | System | ❌ | Config | String |
| `mime_type` | System | ❌ | Detected | String |
| `file_size` | System | ❌ | Computed | Integer |
| `width` | System | ❌ | Extracted | Integer |
| `height` | System | ❌ | Extracted | Integer |
| `alt_text` | Admin | ✅ | Text input | Nullable |
| `caption` | Admin | ✅ | Text input | Nullable |
| `title` | Admin | ✅ | Text input | Nullable |
| `folder` | Admin | ✅ | Folder selector | Nullable |
| `tags` | Admin | ✅ | Tag input | JSON array |
| `uploaded_by` | System | ❌ | Current admin | FK |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |

---

## 10.7 Admin User Fields Ownership

| Field | Owner | Editable | Source | Notes |
|-------|-------|----------|--------|-------|
| `id` | System | ❌ | Auto-generated UUID | Primary key |
| `name` | Admin | ✅ | Text input | Required |
| `email` | Admin | ✅ | Email input | Required, unique |
| `password` | Admin | ✅ | Password input | Hashed |
| `role` | Admin | ✅ | Role selector | Enum |
| `is_active` | Admin | ✅ | Toggle | Boolean |
| `last_login_at` | System | ❌ | Login event | Timestamp |
| `last_login_ip` | System | ❌ | Login event | String |
| `created_at` | System | ❌ | Auto timestamp | Timestamp |
| `updated_at` | System | ❌ | Auto timestamp | Timestamp |

---

## 10.8 Frontend-Only State (Not Persisted)

| State | Location | Purpose |
|-------|----------|---------|
| `formErrors` | Component | Validation error messages |
| `isSubmitting` | Component | Form submission state |
| `selectedRows` | Component | Table row selection |
| `activeTab` | Component | Current tab index |
| `sortColumn` | URL params | Table sort state |
| `filterValues` | URL params | Table filter state |
| `currentPage` | URL params | Pagination state |
| `searchQuery` | URL params | Search input value |
| `sidebarVisible` | Redux | Sidebar toggle state |
| `toasts` | Context | Toast notification queue |
| `modalState` | Component | Modal visibility |
| `dragState` | Component | Drag-drop temporary state |

---

## 10.9 Computed/Derived Fields

| Field | Entity | Computation | When Computed |
|-------|--------|-------------|---------------|
| `location_point` | Event | ST_MakePoint(lng, lat) | On save |
| `structured_data` | Event | JSON-LD from event fields | On save |
| `published_at` | Event | current_timestamp | On publish action |
| `featured_at` | Event | current_timestamp | On feature action |
| `cancelled_at` | Event | current_timestamp | On cancel action |
| `archived_at` | Event | current_timestamp | On archive action |
| `event_count` | Category | COUNT events in category | Dashboard query |
| `talent_count` | Event | COUNT talents attached | Detail query |
| `media_count` | Event | COUNT media attached | Detail query |

---

## 10.10 Data Flow Summary

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Admin     │     │   System    │     │  Frontend   │
│  Controlled │     │  Controlled │     │    Only     │
├─────────────┤     ├─────────────┤     ├─────────────┤
│ • Content   │     │ • IDs       │     │ • UI State  │
│ • Settings  │────▶│ • Timestamps│────▶│ • Filters   │
│ • Relations │     │ • Computed  │     │ • Selection │
│ • Status    │     │ • Audit     │     │ • Validation│
└─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │
      ▼                   ▼                   ▼
┌─────────────────────────────────────────────────────┐
│                    Database                         │
│         (Persisted Admin + System data)             │
└─────────────────────────────────────────────────────┘
```
