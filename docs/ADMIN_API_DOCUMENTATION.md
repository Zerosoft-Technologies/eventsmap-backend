# Admin API Documentation

## Overview

The Admin API provides full CRUD operations for managing events, talents, categories, and admin users. All endpoints require authentication using Laravel Sanctum tokens.

## Base URL

```
/api/admin
```

## Authentication

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Login

**POST** `/api/admin/auth/login`

```json
{
  "email": "admin@eventsmap.com",
  "password": "password",
  "remember": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@eventsmap.com",
      "role": "admin"
    },
    "token": "1|abc123...",
    "expires_at": "2025-01-13T15:00:00+00:00"
  }
}
```

### Logout

**POST** `/api/admin/auth/logout`

### Get Current User

**GET** `/api/admin/auth/me`

### Refresh Token

**POST** `/api/admin/auth/refresh`

---

## Events API

### List Events

**GET** `/api/admin/events`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page (max 100) |
| `sort` | string | Sort field (prefix `-` for desc) |
| `status` | string | draft, published, featured, cancelled, archived |
| `category_id` | int | Filter by category |
| `search` | string | Search in title/description |
| `start_date_from` | date | Filter start date >= |
| `start_date_to` | date | Filter start date <= |
| `is_featured` | bool | Filter featured only |
| `trashed` | bool | Include soft-deleted |

### Get Single Event

**GET** `/api/admin/events/{id}`

### Create Event

**POST** `/api/admin/events`

```json
{
  "title": "Summer Music Festival",
  "description": "An amazing event...",
  "category_id": 1,
  "subcategory_id": null,
  "price": 50.00,
  "start_datetime": "2025-07-15T18:00:00Z",
  "end_datetime": "2025-07-15T23:00:00Z",
  "timezone": "America/New_York",
  "city": "New York",
  "venue_name": "Central Park",
  "address": "Central Park, New York",
  "latitude": 40.785091,
  "longitude": -73.968285
}
```

### Update Event (Full)

**PUT** `/api/admin/events/{id}`

### Update Event (Partial)

**PATCH** `/api/admin/events/{id}`

### Delete Event

**DELETE** `/api/admin/events/{id}`

### Restore Event

**POST** `/api/admin/events/{id}/restore`

### Duplicate Event

**POST** `/api/admin/events/{id}/duplicate`

---

## Event Status Actions

### Publish Event

**POST** `/api/admin/events/{id}/publish`

### Unpublish Event

**POST** `/api/admin/events/{id}/unpublish`

### Feature Event

**POST** `/api/admin/events/{id}/feature`

### Unfeature Event

**POST** `/api/admin/events/{id}/unfeature`

### Cancel Event

**POST** `/api/admin/events/{id}/cancel`

### Archive Event

**POST** `/api/admin/events/{id}/archive`

---

## Event Talents

### Get Event Talents

**GET** `/api/admin/events/{id}/talents`

### Attach Talents

**POST** `/api/admin/events/{id}/talents`

```json
{
  "talents": [
    { "id": 1, "role": "Headliner", "sort_order": 1 },
    { "id": 2, "role": "Opening Act", "sort_order": 2 }
  ]
}
```

### Detach Talent

**DELETE** `/api/admin/events/{id}/talents/{talentId}`

### Reorder Talents

**PATCH** `/api/admin/events/{id}/talents/reorder`

```json
{
  "items": [
    { "id": 1, "sort_order": 2 },
    { "id": 2, "sort_order": 1 }
  ]
}
```

---

## Event Media

### Get Event Media

**GET** `/api/admin/events/{id}/media`

### Attach Media

**POST** `/api/admin/events/{id}/media`

```json
{
  "media": [
    {
      "url": "https://example.com/image.jpg",
      "alt_text": "Event image",
      "caption": "Main stage",
      "is_primary": true,
      "sort_order": 0
    }
  ]
}
```

### Remove Media

**DELETE** `/api/admin/events/{id}/media/{mediaId}`

### Reorder Media

**PATCH** `/api/admin/events/{id}/media/reorder`

### Set Primary Image

**POST** `/api/admin/events/{id}/media/primary`

```json
{
  "media_id": 1
}
```

---

## Bulk Operations

### Bulk Delete

**POST** `/api/admin/events/bulk/delete`

```json
{
  "ids": [1, 2, 3]
}
```

### Bulk Publish

**POST** `/api/admin/events/bulk/publish`

### Bulk Unpublish

**POST** `/api/admin/events/bulk/unpublish`

### Bulk Archive

**POST** `/api/admin/events/bulk/archive`

### Bulk Update Category

**POST** `/api/admin/events/bulk/category`

```json
{
  "ids": [1, 2, 3],
  "category_id": 5
}
```

### Bulk Feature

**POST** `/api/admin/events/bulk/feature`

### Bulk Unfeature

**POST** `/api/admin/events/bulk/unfeature`

---

## Talents API

### List Talents

**GET** `/api/admin/talents`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page |
| `sort` | string | Sort field |
| `search` | string | Search name/bio |
| `is_active` | bool | Filter active only |
| `type` | string | artist, speaker, performer, dj, band, other |
| `trashed` | bool | Include soft-deleted |

### Get Single Talent

**GET** `/api/admin/talents/{id}`

### Create Talent

**POST** `/api/admin/talents`

```json
{
  "name": "DJ Shadow",
  "type": "dj",
  "short_bio": "World-renowned DJ",
  "bio": "Full biography...",
  "image": "https://example.com/dj-shadow.jpg",
  "social_links": {
    "instagram": "https://instagram.com/djshadow",
    "spotify": "https://spotify.com/artist/djshadow"
  },
  "is_active": true
}
```

### Update Talent

**PUT** `/api/admin/talents/{id}`

### Partial Update

**PATCH** `/api/admin/talents/{id}`

### Delete Talent

**DELETE** `/api/admin/talents/{id}`

### Restore Talent

**POST** `/api/admin/talents/{id}/restore`

### Activate Talent

**POST** `/api/admin/talents/{id}/activate`

### Deactivate Talent

**POST** `/api/admin/talents/{id}/deactivate`

### Get Talent's Events

**GET** `/api/admin/talents/{id}/events`

---

## Categories API

### List Categories

**GET** `/api/admin/categories`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `format` | string | `tree` or `flat` |
| `include_inactive` | bool | Include disabled categories |

### Get Single Category

**GET** `/api/admin/categories/{id}`

### Create Category/Subcategory

**POST** `/api/admin/categories`

```json
{
  "name": "Music",
  "description": "Music events",
  "icon": "music-icon",
  "color": "#FF5733",
  "display_order": 1,
  "is_active": true,
  "is_featured": false,
  "parent_id": null
}
```

To create a subcategory, include `parent_id`:

```json
{
  "name": "Rock",
  "parent_id": 1
}
```

### Update Category

**PUT** `/api/admin/categories/{id}`

### Delete Category

**DELETE** `/api/admin/categories/{id}`

### Activate Category

**POST** `/api/admin/categories/{id}/activate`

### Deactivate Category

**POST** `/api/admin/categories/{id}/deactivate`

### Reorder Categories

**PATCH** `/api/admin/categories/reorder`

```json
{
  "items": [
    { "id": 1, "display_order": 1 },
    { "id": 2, "display_order": 2 }
  ]
}
```

### Get Subcategories

**GET** `/api/admin/categories/{id}/subcategories`

### Update Subcategory

**PUT** `/api/admin/subcategories/{id}`

### Delete Subcategory

**DELETE** `/api/admin/subcategories/{id}`

---

## Admin Users API (Super Admin Only)

### List Admin Users

**GET** `/api/admin/users`

### Get Single User

**GET** `/api/admin/users/{id}`

### Create Admin User

**POST** `/api/admin/users`

```json
{
  "name": "New Admin",
  "email": "newadmin@eventsmap.com",
  "password": "SecurePassword123!",
  "role": "admin",
  "is_active": true
}
```

### Update User

**PUT** `/api/admin/users/{id}`

### Delete User

**DELETE** `/api/admin/users/{id}`

### Activate User

**POST** `/api/admin/users/{id}/activate`

### Deactivate User

**POST** `/api/admin/users/{id}/deactivate`

### Reset Password

**POST** `/api/admin/users/{id}/reset-password`

```json
{
  "password": "NewSecurePassword123!"
}
```

---

## Analytics API

### Dashboard Stats

**GET** `/api/admin/analytics/dashboard`

**Response:**
```json
{
  "success": true,
  "data": {
    "events": {
      "total": 150,
      "by_status": {
        "draft": 10,
        "published": 100,
        "featured": 15,
        "cancelled": 5,
        "archived": 20
      }
    },
    "upcoming_events": 45,
    "live_now_events": 3,
    "talents_active": 50,
    "talents_total": 60,
    "categories_active": 12,
    "categories_total": 15,
    "admin_users": 5,
    "total_views": 25000,
    "events_this_month": 20,
    "events_last_month": 15
  }
}
```

### Event Analytics

**GET** `/api/admin/analytics/events`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `period` | string | 7d, 30d, 90d, 1y |

### Category Analytics

**GET** `/api/admin/analytics/categories`

---

## Error Responses

### Standard Error Format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human readable message",
    "details": {
      "field_name": ["Error message"]
    }
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Permission denied |
| `DEPENDENCY_ERROR` | 409 | Cannot delete due to dependencies |
| `ALREADY_PUBLISHED` | 400 | Event is already published |
| `NOT_PUBLISHED` | 400 | Event must be published first |
| `NOT_DELETED` | 400 | Resource is not deleted |
| `SELF_DELETE` | 400 | Cannot delete own account |
| `SELF_DEACTIVATE` | 400 | Cannot deactivate own account |
| `ACCOUNT_DISABLED` | 403 | Account has been disabled |

---

## Roles

| Role | Description | Permissions |
|------|-------------|-------------|
| `super_admin` | Full access | All operations including user management |
| `admin` | Standard admin | All except user management |

---

## Setup Instructions

### 1. Install Sanctum

```bash
composer require laravel/sanctum
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Seed Admin Users

```bash
php artisan db:seed --class=AdminUserSeeder
```

### Default Credentials

- **Super Admin:** superadmin@eventsmap.com / password
- **Admin:** admin@eventsmap.com / password

> ⚠️ **Important:** Change these passwords in production!
