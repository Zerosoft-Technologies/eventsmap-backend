# Event Details API Documentation

## Overview

This document describes the Event Details APIs for the frontend Event Details page (Overview, About, Date & Location, Talents tabs).

## Base URL

```
/api/v1
```

---

## Endpoints

### 1. Main Event Details

**GET** `/api/v1/events/{id}`

Returns full event overview data with all details.

#### Response Example

```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Summer Music Festival 2025",
    "description": "An amazing outdoor music festival featuring top artists.",
    "slug": "summer-music-festival-2025",
    "category": {
      "name": "Music",
      "slug": "music"
    },
    "price": "50.00",
    "min_price": 50.00,
    "max_price": 150.00,
    "currency": "USD",
    "start_datetime": "2025-07-15T18:00:00+00:00",
    "end_datetime": "2025-07-15T23:00:00+00:00",
    "timezone": "America/New_York",
    "venue_name": "Central Park",
    "address": "123 Park Avenue",
    "city": "New York",
    "country": "USA",
    "latitude": 40.7829,
    "longitude": -73.9654,
    "dresscode": "Casual",
    "min_age": 18,
    "max_age": null,
    "organizer_name": "Event Productions Inc.",
    "organizer_id": 5,
    "contact_info": {
      "email": "info@eventproductions.com",
      "phone": "+1-555-123-4567",
      "website": "https://eventproductions.com"
    },
    "images": [
      "https://example.com/images/event1-1.jpg",
      "https://example.com/images/event1-2.jpg"
    ],
    "cover_image": "https://example.com/images/event1-cover.jpg",
    "video_url": "https://youtube.com/watch?v=abc123",
    "talents": [
      {
        "id": 1,
        "name": "DJ Shadow",
        "image": "https://example.com/talents/dj-shadow.jpg",
        "role": "Headliner",
        "bio": "World-renowned DJ and producer.",
        "social_links": {
          "spotify": "https://spotify.com/artist/djshadow",
          "instagram": "https://instagram.com/djshadow",
          "website": "https://djshadow.com"
        }
      }
    ],
    "about": {
      "accessibility": {
        "wheelchair_accessible": true
      },
      "planning": {
        "ticket_required": true
      },
      "services": {
        "wifi": true
      },
      "amenities": {
        "bar": true
      },
      "children": {
        "suitable_for_children": false
      },
      "description": "Join us for an unforgettable night of music!",
      "rules": [
        "No outside food or beverages",
        "No professional cameras"
      ],
      "tips": [
        "Arrive early for parking",
        "Bring ear protection"
      ]
    },
    "location_details": {
      "full_address": "Central Park, 123 Park Avenue, New York, USA",
      "directions": "Take the A train to 59th Street",
      "public_transport": {
        "subway": "A, B, C to 59th Street",
        "bus": "M10, M20"
      },
      "parking_info": {
        "available": true,
        "price": "$25/day",
        "lots": ["Lot A - North Entrance", "Lot B - South Entrance"]
      },
      "map_url": "https://www.google.com/maps?q=40.7829,-73.9654",
      "venue_website": "https://centralpark.com"
    },
    "booking": {
      "required": true,
      "ticket_url": "https://tickets.example.com/event/1",
      "capacity": 5000,
      "waiting_list_available": true
    },
    "social_links": {
      "facebook": "https://facebook.com/event/123",
      "twitter": "https://twitter.com/summerfest",
      "instagram": "https://instagram.com/summerfest"
    },
    "is_live_now": false,
    "is_published": true,
    "is_featured": true,
    "is_cancelled": false,
    "meta_title": "Summer Music Festival 2025 | NYC",
    "meta_description": "Don't miss the biggest music festival of the summer!",
    "tags": ["music", "festival", "outdoor", "summer"],
    "view_count": 1542,
    "created_at": "2025-01-01T10:00:00+00:00",
    "updated_at": "2025-01-05T15:30:00+00:00"
  }
}
```

---

### 2. Event Talents

**GET** `/api/v1/events/{id}/talents`

Returns talents/performers for a specific event.

#### Response Example

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "DJ Shadow",
      "image": "https://example.com/talents/dj-shadow.jpg",
      "role": "Headliner",
      "bio": "World-renowned DJ and producer known for groundbreaking electronic music.",
      "social_links": {
        "spotify": "https://spotify.com/artist/djshadow",
        "instagram": "https://instagram.com/djshadow",
        "website": "https://djshadow.com"
      }
    },
    {
      "id": 2,
      "name": "The Midnight",
      "image": "https://example.com/talents/the-midnight.jpg",
      "role": "Opening Act",
      "bio": "Synthwave duo from Los Angeles.",
      "social_links": {
        "spotify": "https://spotify.com/artist/themidnight",
        "instagram": "https://instagram.com/themidnight",
        "website": null
      }
    }
  ]
}
```

---

### 3. Event About Information

**GET** `/api/v1/events/{id}/about`

Returns about/info section data for a specific event.

#### Response Example

```json
{
  "success": true,
  "data": {
    "accessibility": {
      "wheelchair_accessible": true
    },
    "planning": {
      "ticket_required": true
    },
    "services": {
      "wifi": true
    },
    "amenities": {
      "bar": true
    },
    "children": {
      "suitable_for_children": false
    },
    "description": "Join us for an unforgettable night of music featuring world-class artists and an amazing atmosphere.",
    "rules": [
      "No outside food or beverages",
      "No professional cameras",
      "No smoking inside venue"
    ],
    "tips": [
      "Arrive early for best parking spots",
      "Bring ear protection for loud areas",
      "Download the event app for real-time updates"
    ]
  }
}
```

---

### 4. Event Location Details

**GET** `/api/v1/events/{id}/location`

Returns location and venue details for a specific event.

#### Response Example

```json
{
  "success": true,
  "data": {
    "venue_name": "Central Park",
    "address": "123 Park Avenue",
    "city": "New York",
    "country": "USA",
    "latitude": 40.7829,
    "longitude": -73.9654,
    "full_address": "Central Park, 123 Park Avenue, New York, USA",
    "directions": "Take the A train to 59th Street, then walk 5 minutes north.",
    "public_transport": {
      "subway": "A, B, C to 59th Street",
      "bus": "M10, M20"
    },
    "parking_info": {
      "available": true,
      "price": "$25/day",
      "lots": ["Lot A - North Entrance", "Lot B - South Entrance"]
    },
    "map_url": "https://www.google.com/maps?q=40.7829,-73.9654",
    "venue_website": "https://centralpark.com"
  }
}
```

---

### 5. Event Images

**GET** `/api/v1/events/{id}/images`

Returns all images for a specific event.

#### Response Example

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "url": "https://example.com/images/event1-cover.jpg",
      "alt_text": "Main stage at Summer Music Festival",
      "caption": "The main stage before doors open",
      "is_primary": true,
      "sort_order": 0
    },
    {
      "id": 2,
      "url": "https://example.com/images/event1-crowd.jpg",
      "alt_text": "Crowd enjoying the festival",
      "caption": "Last year's amazing crowd",
      "is_primary": false,
      "sort_order": 1
    },
    {
      "id": 3,
      "url": "https://example.com/images/event1-venue.jpg",
      "alt_text": "Aerial view of the venue",
      "caption": null,
      "is_primary": false,
      "sort_order": 2
    }
  ]
}
```

---

## Error Responses

### 404 Not Found

```json
{
  "message": "No query results for model [App\\Models\\Event] 999"
}
```

### 422 Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message here"]
  }
}
```

---

## Database Schema

### Events Table Extensions

| Column | Type | Description |
|--------|------|-------------|
| slug | string | URL-friendly unique identifier |
| min_price | decimal | Minimum ticket price |
| max_price | decimal | Maximum ticket price |
| currency | string(3) | Currency code (default: USD) |
| timezone | string(50) | Event timezone |
| venue_name | string | Name of the venue |
| country | string(100) | Country |
| max_age | integer | Maximum age restriction |
| organizer_name | string | Organizer display name |
| organizer_id | bigint | FK to users table |
| contact_info | json | Contact details object |
| cover_image | string | Primary cover image URL |
| video_url | string | Video URL (YouTube, etc.) |
| about | json | About section data |
| location_details | json | Extended location info |
| booking | json | Booking configuration |
| social_links | json | Social media links |
| is_featured | boolean | Featured event flag |
| is_cancelled | boolean | Cancelled event flag |
| meta_title | string | SEO meta title |
| meta_description | text | SEO meta description |
| tags | json | Array of tag strings |
| view_count | bigint | Page view counter |

### Talents Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Talent name |
| slug | string | URL-friendly identifier |
| image | string | Profile image URL |
| bio | text | Biography |
| social_links | json | Social media links |
| is_active | boolean | Active status |

### Event Talent Pivot Table

| Column | Type | Description |
|--------|------|-------------|
| event_id | bigint | FK to events |
| talent_id | bigint | FK to talents |
| role | string | Role in the event |
| sort_order | integer | Display order |

### Event Images Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| event_id | bigint | FK to events |
| url | string | Image URL |
| alt_text | string | Alt text for accessibility |
| caption | string | Image caption |
| is_primary | boolean | Primary image flag |
| sort_order | integer | Display order |

---

## Running Migrations

```bash
php artisan migrate
```

This will create/update all necessary tables for the Event Details feature.
