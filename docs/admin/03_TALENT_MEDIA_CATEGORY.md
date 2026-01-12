# Super-Admin Panel Blueprint
## Part 3: Talent, Media & Category Management

---

# 4. TALENT MANAGEMENT MODULE

## 4.1 Talent Entity Definition

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `id` | uuid | - | - | System | Primary key |
| `name` | text | Yes | min:2, max:200 | Admin | Display name |
| `slug` | text | Yes | unique, regex | Admin | URL slug |
| `type` | select | Yes | enum:artist,speaker,performer,dj,band,other | Admin | Talent type |
| `bio` | richtext | No | max:5000 | Admin | Biography |
| `short_bio` | textarea | No | max:300 | Admin | Short description |
| `profile_image_id` | uuid | No | exists:media | Admin | Profile photo |
| `cover_image_id` | uuid | No | exists:media | Admin | Cover/banner |
| `website_url` | url | No | valid URL | Admin | Official site |
| `social_links` | json | No | - | Admin | Social media |
| `genres` | array[text] | No | max:10 | Admin | Music/topic genres |
| `is_active` | toggle | Yes | boolean | Admin | Active status |
| `is_verified` | toggle | No | boolean | Admin | Verified badge |
| `contact_email` | email | No | valid email | Admin | Contact info |
| `management_info` | text | No | max:500 | Admin | Agent/manager |
| `internal_notes` | textarea | No | max:2000 | Admin | Admin notes |
| `meta_title` | text | No | max:70 | Admin | SEO title |
| `meta_description` | textarea | No | max:160 | Admin | SEO description |
| `created_at` | datetime | - | - | System | - |
| `updated_at` | datetime | - | - | System | - |
| `deleted_at` | datetime | - | - | System | Soft delete |

## 4.2 Admin Actions

| Action | Description | Validation |
|--------|-------------|------------|
| **Create Talent** | Add new talent profile | Required fields valid |
| **Edit Talent** | Update talent info | - |
| **Activate** | Enable talent | - |
| **Deactivate** | Disable talent | Warn if attached to published events |
| **Delete** | Soft delete | Warn if attached to any events |
| **Restore** | Undo delete | - |
| **Attach to Event** | Link talent to event | Both must exist |
| **Detach from Event** | Remove link | - |
| **Reorder in Event** | Change billing order | Via pivot table |

## 4.3 Event-Talent Pivot Behavior

```
event_talent pivot table:
├── event_id (uuid, FK)
├── talent_id (uuid, FK)
├── role (varchar, nullable) - "Headliner", "Opening Act", "Guest Speaker"
├── billing_order (integer) - Display ordering
├── is_headliner (boolean) - Featured talent flag
├── performance_time (time, nullable)
├── notes (text, nullable) - Internal notes
├── created_at (timestamp)
└── updated_at (timestamp)
```

**Reuse Across Events:**
- Same talent can be attached to multiple events
- Each attachment has independent role and billing order
- Talent profile updates reflect across all event appearances

## 4.4 Validation Rules

| Rule | Description |
|------|-------------|
| Unique slug | Slug must be unique across all talents |
| Active for publish | Talent must be active to attach to published events |
| Cascade warning | Warn before deactivating talent with event attachments |
| Image validation | Profile image must be square (recommended 400x400) |

---

# 5. MEDIA MANAGEMENT MODULE

## 5.1 Media Entity Definition

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `id` | uuid | - | - | System | Primary key |
| `file_name` | text | - | - | System | Original filename |
| `file_path` | text | - | - | System | Storage path |
| `disk` | text | - | - | System | Storage disk |
| `mime_type` | text | - | - | System | File MIME type |
| `file_size` | integer | - | - | System | Size in bytes |
| `width` | integer | - | - | System | Image width (px) |
| `height` | integer | - | - | System | Image height (px) |
| `alt_text` | text | No | max:200 | Admin | Accessibility text |
| `caption` | text | No | max:500 | Admin | Display caption |
| `title` | text | No | max:200 | Admin | Media title |
| `folder` | text | No | max:100 | Admin | Organization folder |
| `tags` | array[text] | No | max:10 | Admin | Searchable tags |
| `uploaded_by` | uuid | - | - | System | Admin user ID |
| `created_at` | datetime | - | - | System | Upload time |
| `updated_at` | datetime | - | - | System | - |

## 5.2 File Constraints

| Constraint | Value |
|------------|-------|
| **Max File Size** | 10 MB (images), 100 MB (videos) |
| **Allowed Image Types** | jpg, jpeg, png, gif, webp, svg |
| **Allowed Video Types** | mp4, mov, avi, webm |
| **Image Dimensions** | Min: 200x200, Max: 8000x8000 |
| **Naming Convention** | UUID-based with original extension |

## 5.3 Image Processing Pipeline

```
Upload → Validate → Process → Store → Index
         │
         ├── Check file type
         ├── Check file size
         ├── Scan for malware (optional)
         │
         └── Generate variants:
             ├── thumbnail (150x150, crop)
             ├── small (400x400, fit)
             ├── medium (800x800, fit)
             ├── large (1200x1200, fit)
             └── original (preserved)
```

## 5.4 Storage Strategy

| Environment | Storage | Path Pattern |
|-------------|---------|--------------|
| Local Dev | local disk | /storage/app/media/{folder}/{uuid}.{ext} |
| Production | S3/CDN | /media/{folder}/{uuid}.{ext} |

**CDN Readiness:**
- All media URLs returned as configurable base URL + path
- Support for signed URLs (expiring links)
- Cache headers for browser caching
- Image transformation via query params (future)

## 5.5 Admin Actions

| Action | Description |
|--------|-------------|
| **Upload** | Single or bulk file upload |
| **Edit Metadata** | Update alt, caption, title, folder, tags |
| **Move** | Change folder organization |
| **Delete** | Remove file (check usage first) |
| **Set Primary** | Mark as primary for event/talent |
| **Reorder** | Change display order in gallery |
| **Copy URL** | Get direct link to media |
| **View Usage** | See where media is used |

## 5.6 Ordering Logic

```
Event Gallery Ordering:
├── Stored in pivot table (event_media)
├── display_order column (integer, 1-indexed)
├── Drag-drop updates order via PATCH
└── On delete: resequence remaining items
```

---

# 6. CATEGORY & TAXONOMY MANAGEMENT

## 6.1 Category Entity Definition

| Field | Type | Required | Validation | Editable | Notes |
|-------|------|----------|------------|----------|-------|
| `id` | uuid | - | - | System | Primary key |
| `name` | text | Yes | min:2, max:100 | Admin | Display name |
| `slug` | text | Yes | unique, regex | Admin | URL slug |
| `description` | textarea | No | max:500 | Admin | Category description |
| `icon` | text | No | max:50 | Admin | Icon class name |
| `image_id` | uuid | No | exists:media | Admin | Category image |
| `color` | text | No | hex color | Admin | Theme color |
| `display_order` | integer | Yes | min:1 | Admin | Sort order |
| `is_active` | toggle | Yes | boolean | Admin | Enabled/disabled |
| `is_featured` | toggle | No | boolean | Admin | Featured category |
| `parent_id` | uuid | No | exists:categories | Admin | Parent (for subcategory) |
| `meta_title` | text | No | max:70 | Admin | SEO title |
| `meta_description` | textarea | No | max:160 | Admin | SEO description |
| `created_at` | datetime | - | - | System | - |
| `updated_at` | datetime | - | - | System | - |

## 6.2 Hierarchy Structure

```
Categories (parent_id = null)
├── Music
│   ├── Concerts (parent_id = Music.id)
│   ├── Festivals
│   └── Live Shows
├── Sports
│   ├── Football
│   ├── Basketball
│   └── Tennis
├── Arts & Culture
│   ├── Theater
│   ├── Museums
│   └── Exhibitions
└── Conferences
    ├── Tech
    ├── Business
    └── Academic
```

## 6.3 Admin Actions

| Action | Description | Validation |
|--------|-------------|------------|
| **Create Category** | Add new category | Unique slug |
| **Create Subcategory** | Add under parent | Parent must exist |
| **Edit Category** | Update details | - |
| **Enable/Disable** | Toggle is_active | Warn if has events |
| **Reorder** | Change display_order | - |
| **Delete** | Remove category | Block if has events or subcategories |
| **Merge** | Combine categories | Reassign events first |

## 6.4 Slug Behavior

```
Auto-generation:
├── Created from name on first save
├── Lowercase, hyphenated
├── Remove special characters
├── Ensure uniqueness (append -1, -2, etc.)
├── Admin can override

Examples:
├── "Live Music" → "live-music"
├── "Arts & Culture" → "arts-culture"
└── "Tech Conferences" → "tech-conferences"
```

## 6.5 Dependency Validation

| Action | Check | Behavior |
|--------|-------|----------|
| Disable Category | Has published events? | Warning + confirm |
| Delete Category | Has any events? | Block deletion |
| Delete Category | Has subcategories? | Block deletion |
| Delete Subcategory | Has events? | Block deletion |

## 6.6 Visibility Rules

| Condition | Public API Behavior |
|-----------|---------------------|
| `is_active = false` | Hidden from public, events still visible if published |
| Category has 0 published events | Can be hidden optionally via setting |
| Parent disabled | Subcategories also hidden |
