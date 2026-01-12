# Super-Admin Panel Blueprint
## Part 2: Event Management Module (Core)

---

# 3. EVENT MANAGEMENT MODULE (CORE)

## 3.1 Event Lifecycle States

```
                    ┌──────────┐
                    │  DRAFT   │ (Initial state)
                    └────┬─────┘
                         │ publish()
                         ▼
                    ┌──────────┐
         ┌─────────│ PUBLISHED│─────────┐
         │         └────┬─────┘         │
         │              │               │
    unpublish()    feature()       cancel()
         │              │               │
         ▼              ▼               ▼
    ┌──────────┐  ┌──────────┐   ┌──────────┐
    │  DRAFT   │  │ FEATURED │   │ CANCELLED│
    └──────────┘  └────┬─────┘   └────┬─────┘
                       │              │
                  unfeature()     archive()
                       │              │
                       ▼              ▼
                  ┌──────────┐   ┌──────────┐
                  │ PUBLISHED│   │ ARCHIVED │
                  └──────────┘   └──────────┘
```

## 3.2 Event Status Definitions

| Status | Description | Public Visibility | Editable |
|--------|-------------|-------------------|----------|
| `draft` | Work in progress | Hidden | Full |
| `published` | Live on platform | Visible | Full |
| `featured` | Highlighted/promoted | Visible + Featured | Full |
| `cancelled` | Event cancelled | Visible (marked) | Limited |
| `archived` | Past/removed | Hidden | Read-only |

## 3.3 Admin Capabilities

| Action | Description | Status Transitions |
|--------|-------------|-------------------|
| **Create Event** | New event in draft | → draft |
| **Edit Event** | Modify event details | (no change) |
| **Duplicate Event** | Copy event as new draft | → draft |
| **Publish** | Make event public | draft → published |
| **Unpublish** | Return to draft | published/featured → draft |
| **Feature** | Promote event | published → featured |
| **Unfeature** | Remove promotion | featured → published |
| **Cancel** | Mark as cancelled | published/featured → cancelled |
| **Postpone** | Update dates + notify | (no status change) |
| **Archive** | Move to archive | any → archived |
| **Delete** | Soft delete | (soft_deleted_at set) |
| **Restore** | Undo soft delete | (soft_deleted_at null) |

---

## 3.4 Edit Event UI - Tab Structure

### Tab 1: Overview

**Purpose:** Core event identification and status control.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `title` | text | Yes | min:3, max:200 | Admin | Primary event name |
| `slug` | text | Yes | unique, regex:^[a-z0-9-]+$ | Admin | Auto-generated, editable |
| `status` | select | Yes | enum values | Admin | Status dropdown |
| `category_id` | select | Yes | exists:categories | Admin | Primary category |
| `subcategory_id` | select | No | exists:subcategories | Admin | Optional subcategory |
| `is_featured` | toggle | No | boolean | Admin | Feature flag |
| `is_visible` | toggle | No | boolean | Admin | Visibility toggle |
| `event_type` | select | Yes | enum:in_person,virtual,hybrid | Admin | Event format |
| `id` | text | - | - | System | Read-only UUID |
| `created_at` | datetime | - | - | System | Read-only |
| `updated_at` | datetime | - | - | System | Read-only |

**UI Components:**
- Title input with character counter
- Slug input with auto-generate button
- Status dropdown with color indicators
- Category cascading selects
- Toggle switches for flags

---

### Tab 2: About

**Purpose:** Event description and detailed content.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `short_description` | textarea | Yes | min:10, max:300 | Admin | Excerpt/teaser |
| `description` | richtext | Yes | min:50 | Admin | Full HTML description |
| `highlights` | array[text] | No | max:10 items | Admin | Repeatable bullet points |
| `requirements` | array[text] | No | max:10 items | Admin | Entry requirements |
| `additional_info` | richtext | No | - | Admin | Extra information |
| `age_restriction` | select | No | enum:all,18+,21+ | Admin | Age limit |
| `dress_code` | text | No | max:100 | Admin | Dress requirements |
| `accessibility_info` | textarea | No | max:500 | Admin | Accessibility details |

**Repeatable Sections:**
- **Highlights:** Add/remove/reorder bullet points
- **Requirements:** Add/remove/reorder requirements

**UI Components:**
- Rich text editor (CKEditor/TinyMCE)
- Repeatable field groups with drag-drop ordering
- Character counters

---

### Tab 3: Date & Location

**Purpose:** When and where the event takes place.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `start_date` | datetime | Yes | after:now (for new) | Admin | Event start |
| `end_date` | datetime | Yes | after:start_date | Admin | Event end |
| `timezone` | select | Yes | valid timezone | Admin | Event timezone |
| `is_all_day` | toggle | No | boolean | Admin | All-day event flag |
| `is_recurring` | toggle | No | boolean | Admin | Recurring flag |
| `recurrence_rule` | json | No | valid rrule | Admin | iCal RRULE format |
| `venue_name` | text | Yes* | min:2, max:200 | Admin | *Required if in_person |
| `venue_address` | text | Yes* | min:5, max:500 | Admin | Full address |
| `city` | text | Yes* | max:100 | Admin | City name |
| `state` | text | No | max:100 | Admin | State/province |
| `country` | text | Yes* | max:100 | Admin | Country |
| `postal_code` | text | No | max:20 | Admin | ZIP/postal code |
| `latitude` | number | Yes* | between:-90,90 | Admin | Geo coordinate |
| `longitude` | number | Yes* | between:-180,180 | Admin | Geo coordinate |
| `location_point` | geography | - | - | System | PostGIS point (computed) |
| `virtual_url` | url | Yes** | valid URL | Admin | **Required if virtual |
| `virtual_platform` | text | No | max:100 | Admin | Platform name |

**Conditional Requirements:**
- If `event_type` = `in_person` or `hybrid`: venue fields required
- If `event_type` = `virtual` or `hybrid`: virtual_url required

**UI Components:**
- DateTime pickers with timezone selector
- Google Maps integration for location picker
- Address autocomplete
- Lat/Lng auto-populated from address
- Conditional field visibility based on event_type

---

### Tab 4: Talents

**Purpose:** Manage performers, speakers, and participants.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `talents` | relation | No | - | Admin | Many-to-many |

**Pivot Table Fields (event_talent):**

| Field | Type | Required | Validation | Notes |
|-------|------|----------|------------|-------|
| `event_id` | uuid | Yes | exists:events | FK to events |
| `talent_id` | uuid | Yes | exists:talents | FK to talents |
| `role` | text | No | max:100 | Role in this event |
| `billing_order` | integer | Yes | min:1 | Display order |
| `is_headliner` | boolean | No | - | Headline act flag |
| `performance_time` | time | No | - | Scheduled time |
| `notes` | text | No | max:500 | Internal notes |

**UI Components:**
- Searchable talent selector (autocomplete)
- Drag-drop ordering for billing order
- Inline role assignment
- Headliner toggle
- Add new talent modal (quick create)

---

### Tab 5: Media

**Purpose:** Event images, videos, and media gallery.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `primary_image_id` | uuid | Yes | exists:media | Admin | Main event image |
| `gallery_images` | array[uuid] | No | max:20 | Admin | Gallery images |
| `video_urls` | array[object] | No | max:5 | Admin | Video embeds |

**Media Object Structure:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `id` | uuid | System | - |
| `file_path` | text | System | - |
| `file_name` | text | System | - |
| `mime_type` | text | System | - |
| `file_size` | integer | System | - |
| `alt_text` | text | No | max:200 |
| `caption` | text | No | max:500 |
| `display_order` | integer | Yes | min:1 |

**Video URL Structure:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `url` | url | Yes | valid URL, youtube/vimeo |
| `title` | text | No | max:200 |
| `display_order` | integer | Yes | min:1 |

**UI Components:**
- Image uploader with drag-drop
- Image grid with reordering
- Primary image selector (radio)
- Alt text / caption inline edit
- Video URL input with platform detection
- Preview thumbnails

---

### Tab 6: Booking & Capacity

**Purpose:** Ticketing and attendance configuration (Future-Ready).

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `is_ticketed` | toggle | No | boolean | Admin | Has tickets |
| `is_free` | toggle | No | boolean | Admin | Free event flag |
| `capacity` | number | No | min:1 | Admin | Max attendees |
| `registration_url` | url | No | valid URL | Admin | External registration |
| `registration_deadline` | datetime | No | before:start_date | Admin | Cutoff date |
| `tickets` | array[object] | No | - | Admin | Ticket types |

**Ticket Object Structure (Future):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | text | Yes | max:100 |
| `description` | text | No | max:500 |
| `price` | decimal | Yes | min:0 |
| `currency` | text | Yes | ISO 4217 |
| `quantity` | integer | Yes | min:1 |
| `sale_start` | datetime | No | - |
| `sale_end` | datetime | No | - |
| `is_active` | boolean | Yes | - |

**UI Components:**
- Capacity input with validation
- Repeatable ticket type section
- Price/currency inputs
- Date range for ticket sales

---

### Tab 7: SEO & Meta

**Purpose:** Search engine optimization and social sharing.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `meta_title` | text | No | max:70 | Admin | Override title tag |
| `meta_description` | textarea | No | max:160 | Admin | Meta description |
| `meta_keywords` | array[text] | No | max:10 | Admin | Keywords list |
| `og_title` | text | No | max:95 | Admin | Open Graph title |
| `og_description` | textarea | No | max:200 | Admin | OG description |
| `og_image_id` | uuid | No | exists:media | Admin | Social share image |
| `canonical_url` | url | No | valid URL | Admin | Canonical URL override |
| `no_index` | toggle | No | boolean | Admin | Block search indexing |
| `structured_data` | json | - | - | System | Auto-generated JSON-LD |

**UI Components:**
- Character counters with SEO recommendations
- Preview cards (Google, Facebook, Twitter)
- Image selector for OG image
- JSON-LD preview (read-only)

---

### Tab 8: Settings

**Purpose:** Event-specific configuration and internal notes.

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `internal_notes` | textarea | No | max:2000 | Admin | Admin-only notes |
| `external_id` | text | No | max:100 | Admin | External system ID |
| `source` | text | No | max:100 | Admin | Data source reference |
| `contact_email` | email | No | valid email | Admin | Event contact |
| `contact_phone` | text | No | max:20 | Admin | Event phone |
| `website_url` | url | No | valid URL | Admin | Official website |
| `social_links` | json | No | - | Admin | Social media links |
| `tags` | array[text] | No | max:20 | Admin | Searchable tags |
| `custom_fields` | json | No | - | Admin | Extensible data |

**Social Links Structure:**

| Field | Type | Required |
|-------|------|----------|
| `facebook` | url | No |
| `twitter` | url | No |
| `instagram` | url | No |
| `linkedin` | url | No |
| `youtube` | url | No |
| `tiktok` | url | No |

**UI Components:**
- Textarea for internal notes
- Social links input group
- Tags input with autocomplete
- Custom fields key-value editor

---

## 3.5 Event Field Summary

### Admin-Editable Fields (42 fields)

```
Overview: title, slug, status, category_id, subcategory_id, is_featured, 
          is_visible, event_type

About: short_description, description, highlights[], requirements[], 
       additional_info, age_restriction, dress_code, accessibility_info

Date/Location: start_date, end_date, timezone, is_all_day, is_recurring, 
               recurrence_rule, venue_name, venue_address, city, state, 
               country, postal_code, latitude, longitude, virtual_url, 
               virtual_platform

Talents: talents[] (via pivot)

Media: primary_image_id, gallery_images[], video_urls[]

Booking: is_ticketed, is_free, capacity, registration_url, 
         registration_deadline, tickets[]

SEO: meta_title, meta_description, meta_keywords[], og_title, 
     og_description, og_image_id, canonical_url, no_index

Settings: internal_notes, external_id, source, contact_email, contact_phone, 
          website_url, social_links, tags[], custom_fields
```

### System-Computed Fields (Read-Only)

```
id, created_at, updated_at, deleted_at, location_point, structured_data, 
published_at, featured_at, cancelled_at, archived_at
```

---

## 3.6 Event Tab → Field Mapping Summary

| Tab | Fields Count | Repeatable | Ordering Support |
|-----|--------------|------------|------------------|
| Overview | 10 | No | No |
| About | 8 | Yes (2) | Yes |
| Date & Location | 16 | No | No |
| Talents | 1 (pivot: 7) | Yes | Yes |
| Media | 3 | Yes | Yes |
| Booking & Capacity | 6 | Yes (tickets) | No |
| SEO & Meta | 9 | Yes (keywords) | No |
| Settings | 9 | Yes (tags) | No |
