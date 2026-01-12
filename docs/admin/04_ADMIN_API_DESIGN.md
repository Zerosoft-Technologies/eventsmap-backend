# Super-Admin Panel Blueprint
## Part 4: Admin API Design

---

# 7. ADMIN API DESIGN

## 7.1 API Namespace & Versioning

```
Public APIs:  /api/v1/*     (Read-only, existing)
Admin APIs:   /api/admin/*  (Full CRUD, new)
```

**Base URL:** `https://api.domain.com`

## 7.2 Authentication Headers

```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
X-Request-ID: {uuid}  (for audit trailing)
```

## 7.3 Standard Response Format

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "request_id": "uuid",
    "timestamp": "ISO8601"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human readable message",
    "details": {
      "field_name": ["Error message"]
    }
  },
  "meta": {
    "request_id": "uuid",
    "timestamp": "ISO8601"
  }
}
```

**Paginated Response:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "has_more": true
  },
  "meta": { ... }
}
```

---

## 7.4 Admin API Endpoints

### 7.4.1 Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/auth/login` | Admin login |
| POST | `/api/admin/auth/logout` | Admin logout |
| POST | `/api/admin/auth/refresh` | Refresh token |
| GET | `/api/admin/auth/me` | Current admin info |
| POST | `/api/admin/auth/password/forgot` | Request password reset |
| POST | `/api/admin/auth/password/reset` | Reset password |

**Login Request:**
```json
{
  "email": "admin@example.com",
  "password": "password",
  "remember": true
}
```

**Login Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "name": "Admin Name",
      "email": "admin@example.com",
      "role": "super_admin"
    },
    "token": "jwt_token",
    "expires_at": "ISO8601"
  }
}
```

---

### 7.4.2 Events Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/events` | List events (paginated, filterable) |
| GET | `/api/admin/events/{id}` | Get single event |
| POST | `/api/admin/events` | Create event |
| PUT | `/api/admin/events/{id}` | Update event (full) |
| PATCH | `/api/admin/events/{id}` | Update event (partial) |
| DELETE | `/api/admin/events/{id}` | Soft delete event |
| POST | `/api/admin/events/{id}/restore` | Restore deleted event |
| POST | `/api/admin/events/{id}/duplicate` | Duplicate event |

**Status Toggle Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/events/{id}/publish` | Publish event |
| POST | `/api/admin/events/{id}/unpublish` | Unpublish event |
| POST | `/api/admin/events/{id}/feature` | Feature event |
| POST | `/api/admin/events/{id}/unfeature` | Unfeature event |
| POST | `/api/admin/events/{id}/cancel` | Cancel event |
| POST | `/api/admin/events/{id}/archive` | Archive event |

**Event Relationships:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/events/{id}/talents` | Get event talents |
| POST | `/api/admin/events/{id}/talents` | Attach talents |
| DELETE | `/api/admin/events/{id}/talents/{talentId}` | Detach talent |
| PATCH | `/api/admin/events/{id}/talents/reorder` | Reorder talents |
| GET | `/api/admin/events/{id}/media` | Get event media |
| POST | `/api/admin/events/{id}/media` | Attach media |
| DELETE | `/api/admin/events/{id}/media/{mediaId}` | Detach media |
| PATCH | `/api/admin/events/{id}/media/reorder` | Reorder media |
| POST | `/api/admin/events/{id}/media/primary` | Set primary image |

**Query Parameters (GET /events):**

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page (max 100) |
| `sort` | string | Sort field (prefix - for desc) |
| `status` | string | Filter by status |
| `category_id` | uuid | Filter by category |
| `search` | string | Search title, description |
| `start_date_from` | date | Filter start date >= |
| `start_date_to` | date | Filter start date <= |
| `is_featured` | bool | Filter featured only |
| `trashed` | bool | Include soft-deleted |

**Create Event Request:**
```json
{
  "title": "Summer Music Festival",
  "slug": "summer-music-festival-2026",
  "category_id": "uuid",
  "event_type": "in_person",
  "short_description": "Annual summer music festival",
  "description": "<p>Full HTML description...</p>",
  "start_date": "2026-07-15T18:00:00Z",
  "end_date": "2026-07-15T23:00:00Z",
  "timezone": "America/New_York",
  "venue_name": "Central Park",
  "venue_address": "Central Park, New York",
  "city": "New York",
  "country": "United States",
  "latitude": 40.785091,
  "longitude": -73.968285
}
```

---

### 7.4.3 Talents Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/talents` | List talents |
| GET | `/api/admin/talents/{id}` | Get single talent |
| POST | `/api/admin/talents` | Create talent |
| PUT | `/api/admin/talents/{id}` | Update talent |
| PATCH | `/api/admin/talents/{id}` | Partial update |
| DELETE | `/api/admin/talents/{id}` | Soft delete |
| POST | `/api/admin/talents/{id}/restore` | Restore |
| POST | `/api/admin/talents/{id}/activate` | Activate |
| POST | `/api/admin/talents/{id}/deactivate` | Deactivate |
| GET | `/api/admin/talents/{id}/events` | Get talent's events |

**Create Talent Request:**
```json
{
  "name": "John Doe",
  "slug": "john-doe",
  "type": "artist",
  "short_bio": "Award-winning musician",
  "bio": "<p>Full biography...</p>",
  "genres": ["rock", "alternative"],
  "is_active": true,
  "social_links": {
    "instagram": "https://instagram.com/johndoe",
    "twitter": "https://twitter.com/johndoe"
  }
}
```

---

### 7.4.4 Categories Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/categories` | List categories (tree or flat) |
| GET | `/api/admin/categories/{id}` | Get single category |
| POST | `/api/admin/categories` | Create category |
| PUT | `/api/admin/categories/{id}` | Update category |
| DELETE | `/api/admin/categories/{id}` | Delete category |
| POST | `/api/admin/categories/{id}/activate` | Activate |
| POST | `/api/admin/categories/{id}/deactivate` | Deactivate |
| PATCH | `/api/admin/categories/reorder` | Bulk reorder |
| GET | `/api/admin/categories/{id}/subcategories` | Get subcategories |

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `format` | string | `tree` or `flat` |
| `include_inactive` | bool | Include disabled categories |
| `parent_id` | uuid | Filter by parent (null for root) |

**Reorder Request:**
```json
{
  "items": [
    { "id": "uuid1", "display_order": 1 },
    { "id": "uuid2", "display_order": 2 },
    { "id": "uuid3", "display_order": 3 }
  ]
}
```

---

### 7.4.5 Media Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/media` | List media (paginated) |
| GET | `/api/admin/media/{id}` | Get single media |
| POST | `/api/admin/media/upload` | Upload single file |
| POST | `/api/admin/media/upload-bulk` | Upload multiple files |
| PUT | `/api/admin/media/{id}` | Update metadata |
| DELETE | `/api/admin/media/{id}` | Delete media |
| GET | `/api/admin/media/{id}/usage` | Get usage info |
| POST | `/api/admin/media/{id}/move` | Move to folder |

**Upload Request (multipart/form-data):**
```
file: (binary)
folder: "events"
alt_text: "Event cover image"
title: "Summer Festival 2026"
```

**Upload Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "file_name": "original-name.jpg",
    "file_path": "events/uuid.jpg",
    "mime_type": "image/jpeg",
    "file_size": 245678,
    "width": 1920,
    "height": 1080,
    "urls": {
      "original": "https://cdn.domain.com/media/events/uuid.jpg",
      "large": "https://cdn.domain.com/media/events/uuid_large.jpg",
      "medium": "https://cdn.domain.com/media/events/uuid_medium.jpg",
      "small": "https://cdn.domain.com/media/events/uuid_small.jpg",
      "thumbnail": "https://cdn.domain.com/media/events/uuid_thumb.jpg"
    }
  }
}
```

---

### 7.4.6 Admin Users Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/users` | List admin users |
| GET | `/api/admin/users/{id}` | Get single user |
| POST | `/api/admin/users` | Create admin user |
| PUT | `/api/admin/users/{id}` | Update user |
| DELETE | `/api/admin/users/{id}` | Delete user |
| POST | `/api/admin/users/{id}/activate` | Activate |
| POST | `/api/admin/users/{id}/deactivate` | Deactivate |
| POST | `/api/admin/users/{id}/reset-password` | Force reset |

---

### 7.4.7 Settings Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/settings` | Get all settings |
| GET | `/api/admin/settings/{group}` | Get settings by group |
| PUT | `/api/admin/settings` | Update settings |
| POST | `/api/admin/settings/cache/clear` | Clear cache |

**Settings Response:**
```json
{
  "success": true,
  "data": {
    "general": {
      "site_name": "Events Platform",
      "timezone": "UTC"
    },
    "media": {
      "max_upload_size": 10485760,
      "allowed_types": ["jpg", "png", "gif", "webp"]
    },
    "seo": {
      "default_title": "Discover Events Near You",
      "default_description": "Find and explore events..."
    }
  }
}
```

---

### 7.4.8 Analytics Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/analytics/dashboard` | Dashboard stats |
| GET | `/api/admin/analytics/events` | Event analytics |
| GET | `/api/admin/analytics/categories` | Category stats |
| GET | `/api/admin/analytics/export/{type}` | Export report |

**Dashboard Stats Response:**
```json
{
  "success": true,
  "data": {
    "events": {
      "total": 1250,
      "by_status": {
        "draft": 45,
        "published": 890,
        "featured": 25,
        "cancelled": 30,
        "archived": 260
      }
    },
    "upcoming_events": 156,
    "talents_active": 342,
    "categories_active": 18,
    "media_storage_used": 5368709120
  }
}
```

---

### 7.4.9 Audit Log Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/audit-logs` | List audit logs |
| GET | `/api/admin/audit-logs/{id}` | Get log details |

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `admin_id` | uuid | Filter by admin |
| `entity_type` | string | Filter by entity type |
| `action` | string | Filter by action |
| `date_from` | date | Filter from date |
| `date_to` | date | Filter to date |

---

### 7.4.10 Bulk Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/events/bulk/delete` | Bulk delete events |
| POST | `/api/admin/events/bulk/publish` | Bulk publish |
| POST | `/api/admin/events/bulk/unpublish` | Bulk unpublish |
| POST | `/api/admin/events/bulk/archive` | Bulk archive |
| POST | `/api/admin/events/bulk/category` | Bulk update category |

**Bulk Request Format:**
```json
{
  "ids": ["uuid1", "uuid2", "uuid3"]
}
```

**Bulk Category Update:**
```json
{
  "ids": ["uuid1", "uuid2"],
  "category_id": "new-category-uuid"
}
```

---

## 7.5 Error Codes Reference

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Permission denied |
| `CONFLICT` | 409 | Resource conflict (duplicate slug) |
| `DEPENDENCY_ERROR` | 409 | Cannot delete due to dependencies |
| `RATE_LIMITED` | 429 | Too many requests |
| `SERVER_ERROR` | 500 | Internal server error |

---

## 7.6 Complete Admin API Summary

| Module | Endpoints Count |
|--------|-----------------|
| Authentication | 6 |
| Events (CRUD) | 8 |
| Events (Status) | 6 |
| Events (Relations) | 9 |
| Talents | 10 |
| Categories | 9 |
| Media | 8 |
| Admin Users | 8 |
| Settings | 4 |
| Analytics | 4 |
| Audit Logs | 2 |
| Bulk Operations | 5 |
| **TOTAL** | **79 endpoints** |
