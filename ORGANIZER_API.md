# Organizer API Documentation

## Base URL
```
http://localhost:8001/api/organizer
```

## Authentication
All endpoints (except login) require authentication using Bearer tokens.

### Login
```http
POST /api/organizer/auth/login
Content-Type: application/json

{
  "email": "john@organizer.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Organizer",
      "email": "john@organizer.com",
      "roles": ["organizer"]
    },
    "token": "1|abc123...",
    "expires_at": "2026-02-27T12:16:42.000000Z"
  },
  "message": "Login successful"
}
```

## Event Management

### List Events
```http
GET /api/organizer/events?page=1&per_page=24&status=published&search=party&category_id=1&sort=start_datetime&order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (1-100, default: 24)
- `status` (string): Filter by status (draft, published, featured, cancelled, archived)
- `search` (string): Search in title, description, city
- `category_id` (integer): Filter by category
- `sort` (string): Sort field (created_at, start_datetime, title, view_count)
- `order` (string): Sort order (asc, desc)
- `start_date_from` (date): Filter events from this date
- `start_date_to` (date): Filter events until this date
- `is_featured` (boolean): Filter featured events

### Create Event
```http
POST /api/organizer/events
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Summer Music Festival",
  "description": "Join us for an amazing music festival",
  "short_description": "Best music event of the summer",
  "category_id": 1,
  "subcategory_id": 2,
  "start_datetime": "2026-06-15T18:00:00Z",
  "end_datetime": "2026-06-15T23:00:00Z",
  "venue_name": "Central Park",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "capacity": 5000,
  "price": 50.00,
  "currency": "USD",
  "highlights": ["Live bands", "Food trucks", "Family friendly"],
  "tags": ["music", "festival", "outdoor"]
}
```

### Get Event
```http
GET /api/organizer/events/{id}
Authorization: Bearer {token}
```

### Update Event
```http
PUT /api/organizer/events/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Event Title",
  "description": "Updated description",
  // ... other fields
}
```

### Delete Event
```http
DELETE /api/organizer/events/{id}
Authorization: Bearer {token}
```

## Status Actions

### Publish Event
```http
POST /api/organizer/events/{id}/publish
Authorization: Bearer {token}
```

### Unpublish Event
```http
POST /api/organizer/events/{id}/unpublish
Authorization: Bearer {token}
```

### Feature Event
```http
POST /api/organizer/events/{id}/feature
Authorization: Bearer {token}
```

### Cancel Event
```http
POST /api/organizer/events/{id}/cancel
Authorization: Bearer {token}
```

### Archive Event
```http
POST /api/organizer/events/{id}/archive
Authorization: Bearer {token}
```

## Bulk Operations

### Bulk Delete
```http
POST /api/organizer/events/bulk/delete
Authorization: Bearer {token}
Content-Type: application/json

{
  "event_ids": [1, 2, 3]
}
```

### Bulk Archive
```http
POST /api/organizer/events/bulk/archive
Authorization: Bearer {token}
Content-Type: application/json

{
  "event_ids": [1, 2, 3]
}
```

## Talent Management

### Get Event Talents
```http
GET /api/organizer/events/{id}/talents
Authorization: Bearer {token}
```

### Attach Talents
```http
POST /api/organizer/events/{id}/talents
Authorization: Bearer {token}
Content-Type: application/json

{
  "talents": [
    {
      "id": 1,
      "role": "Headliner",
      "sort_order": 1
    },
    {
      "id": 2,
      "role": "Opening Act",
      "sort_order": 2
    }
  ]
}
```

### Detach Talent
```http
DELETE /api/organizer/events/{id}/talents/{talentId}
Authorization: Bearer {token}
```

### Reorder Talents
```http
PATCH /api/organizer/events/{id}/talents/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
  "talents": [
    {
      "id": 1,
      "sort_order": 2
    },
    {
      "id": 2,
      "sort_order": 1
    }
  ]
}
```

## Media Management

### Get Event Media
```http
GET /api/organizer/events/{id}/media
Authorization: Bearer {token}
```

### Attach Media
```http
POST /api/organizer/events/{id}/media
Authorization: Bearer {token}
Content-Type: application/json

{
  "media_ids": [1, 2, 3]
}
```

### Detach Media
```http
DELETE /api/organizer/events/{id}/media/{mediaId}
Authorization: Bearer {token}
```

### Reorder Media
```http
PATCH /api/organizer/events/{id}/media/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
  "media": [
    {
      "id": 1,
      "sort_order": 2
    },
    {
      "id": 2,
      "sort_order": 1
    }
  ]
}
```

### Set Primary Media
```http
POST /api/organizer/events/{id}/media/primary
Authorization: Bearer {token}
Content-Type: application/json

{
  "media_id": 1
}
```

## Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated. Please login."
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Access denied. Organizer role required."
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Event not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "start_datetime": ["The start datetime must be a date after now."]
  }
}
```

## Notes

1. All datetime fields should be in ISO 8601 format
2. Organizers can only access/modify their own events
3. `internal_notes` field is excluded from organizer responses
4. Soft deletes are used - deleted events can be restored by admins
5. All write operations use database transactions for data integrity

## Running the Seeder

To create sample organizer accounts:

```bash
php artisan db:seed --class=OrganizerSeeder
```

Sample accounts created:
- john@organizer.com / password
- jane@organizer.com / password  
- mike@organizer.com / password
