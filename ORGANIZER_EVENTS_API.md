# Organizer Events API Documentation

## Overview
The Organizer Events API provides a separate table `events_organizer` for managing organizer-submitted events, distinct from the main `events` table.

## Base URLs
- **Admin Organizer Events**: `http://localhost:8001/api/admin/organizer/events`
- **Regular Events**: `http://localhost:8001/api/admin/events`

## Database Schema
The `events_organizer` table has the exact same structure as the `events` table, including:
- All core event fields
- Extended fields (highlights, requirements, etc.)
- Geographic location support (PostGIS for PostgreSQL)
- Soft deletes support
- All indexes for performance

## API Endpoints

### 1. List Organizer Events
```http
GET /api/admin/organizer/events?page=1&per_page=20&status=published&organizer_id=1
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (1-100, default: 20)
- `status` (string): Filter by status (draft, published, featured, cancelled, archived)
- `category_id` (integer): Filter by category
- `organizer_id` (integer): Filter by organizer
- `search` (string): Search in title, description, city
- `sort` (string): Sort field (created_at, start_datetime, title, view_count)
- `order` (string): Sort order (asc, desc)
- `start_date_from` (date): Filter events from this date
- `start_date_to` (date): Filter events until this date
- `is_featured` (boolean): Filter featured events
- `trashed` (boolean): Include soft deleted events

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "John Organizer's Event 1",
      "slug": "john-organizers-event-1-abc123",
      "status": "published",
      "description": "Detailed description...",
      "short_description": "Brief description...",
      "category": {
        "id": 1,
        "name": "Music",
        "slug": "music"
      },
      "category_id": 1,
      "price": "99.99",
      "min_price": "49.99",
      "max_price": "199.99",
      "currency": "USD",
      "start_datetime": "2026-03-15T18:00:00.000000Z",
      "end_datetime": "2026-03-15T22:00:00.000000Z",
      "venue_name": "Venue 5",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "latitude": 40.7128,
      "longitude": -74.0060,
      "organizer_name": "John Organizer",
      "organizer_id": 1,
      "contact_email": "john@organizer.com",
      "is_published": true,
      "is_featured": false,
      "capacity": 500,
      "registration_url": "https://example.com/register",
      "meta_keywords": ["event", "music", "nyc"],
      "view_count": 1250,
      "created_at": "2026-02-14T10:00:00.000000Z",
      "updated_at": "2026-02-14T10:00:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45,
    "from": 1,
    "to": 20
  }
}
```

### 2. Create Organizer Event
```http
POST /api/admin/organizer/events
Authorization: Bearer {admin_token}
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
  "address": "123 Park Ave",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "latitude": 40.7829,
  "longitude": -73.9654,
  "organizer_name": "John Organizer",
  "organizer_id": 1,
  "contact_email": "john@organizer.com",
  "contact_phone": "+1-555-0101",
  "capacity": 5000,
  "price": 50.00,
  "currency": "USD",
  "highlights": ["Live bands", "Food trucks", "Family friendly"],
  "requirements": ["Valid ID required", "No outside food"],
  "meta_keywords": ["music", "festival", "outdoor"],
  "tags": ["music", "festival", "nyc"],
  "status": "published"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 46,
    "title": "Summer Music Festival",
    "slug": "summer-music-festival-xyz789",
    "status": "published",
    // ... full event data
  },
  "message": "Organizer event created successfully"
}
```

### 3. Get Single Organizer Event
```http
GET /api/admin/organizer/events/{id}
Authorization: Bearer {admin_token}
```

### 4. Update Organizer Event
```http
PUT /api/admin/organizer/events/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "title": "Updated Event Title",
  "description": "Updated description",
  // ... other fields
}
```

### 5. Delete Organizer Event
```http
DELETE /api/admin/organizer/events/{id}
Authorization: Bearer {admin_token}
```

## Key Differences from Regular Events API

1. **Separate Table**: All data is stored in `events_organizer` table
2. **Same Validation**: Identical validation rules as regular events
3. **Same Response Format**: Uses the same `AdminEventResource` for consistency
4. **Independent Operations**: No impact on the main `events` table

## Running the Setup

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Run Seeders
```bash
# First create organizer users
php artisan db:seed --class=OrganizerSeeder

# Then create sample organizer events
php artisan db:seed --class=EventOrganizerSeeder
```

### 3. Test the APIs
```bash
# List organizer events
curl -X GET "http://localhost:8001/api/admin/organizer/events" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# Create a new organizer event
curl -X POST "http://localhost:8001/api/admin/organizer/events" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test Event","category_id":1,"city":"New York","start_datetime":"2026-03-15T18:00:00Z","end_datetime":"2026-03-15T22:00:00Z"}'
```

## Model Usage

```php
// Query organizer events
$events = EventOrganizer::with(['category', 'subcategory'])
    ->where('organizer_id', 1)
    ->paginate(20);

// Create new organizer event
$event = EventOrganizer::create([
    'title' => 'New Event',
    'organizer_id' => 1,
    // ... other fields
]);

// Geographic queries
$nearbyEvents = EventOrganizer::withinRadius(40.7128, -74.0060, 10)
    ->get();
```

## Notes

1. The `events_organizer` table is completely independent from `events`
2. All features from the regular events are available (geographic search, soft deletes, etc.)
3. The same `AdminEventResource` is used for consistent API responses
4. PostGIS spatial queries are supported when using PostgreSQL
5. All validation rules remain the same as regular events
