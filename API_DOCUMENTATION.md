# Events Map API Documentation

## Overview

The Events Map API provides endpoints for discovering events with location-based search, filtering by categories/subcategories, and pagination. All data is returned in JSON format.

## Base URL
```
http://your-domain.com/api/v1
```

## Authentication
Currently no authentication is required (public API).

## Response Format

### Success Response
```json
{
  "success": true,
  "data": [...],
  "meta": {  // Only for paginated endpoints
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description"
}
```

---

## Categories API

### Get All Categories with Subcategories
```http
GET /api/v1/categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Music",
      "slug": "music",
      "subcategories": [
        {
          "id": 1,
          "name": "Jazz",
          "slug": "jazz"
        },
        {
          "id": 2,
          "name": "Rock",
          "slug": "rock"
        }
      ]
    },
    {
      "id": 2,
      "name": "Dance",
      "slug": "dance",
      "subcategories": [...]
    }
  ]
}
```

### Get Single Category
```http
GET /api/v1/categories/{slug}
```

**Example:** `/api/v1/categories/music`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Music",
    "slug": "music",
    "subcategories": [
      {
        "id": 1,
        "name": "Jazz",
        "slug": "jazz"
      }
    ]
  }
}
```

### Get Subcategories by Category
```http
GET /api/v1/categories/{slug}/subcategories
```

**Example:** `/api/v1/categories/music/subcategories`

**Response:**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Music",
      "slug": "music"
    },
    "subcategories": [
      {
        "id": 1,
        "name": "Jazz",
        "slug": "jazz"
      },
      {
        "id": 2,
        "name": "Rock",
        "slug": "rock"
      }
    ]
  }
}
```

---

## Events API

### List Events with Filters
```http
GET /api/v1/events
```

#### Query Parameters (All Optional)

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `search` | string | Search in title and description | `search=jazz` |
| `lat` | float | Latitude for geo search | `lat=52.3676` |
| `lng` | float | Longitude for geo search | `lng=4.9376` |
| `radius` | float | Radius in km (requires lat/lng) | `radius=10` |
| `from_date` | date | Events from this date | `from_date=2025-01-01` |
| `to_date` | date | Events until this date | `to_date=2025-01-31` |
| `min_price` | decimal | Minimum price | `min_price=10` |
| `max_price` | decimal | Maximum price | `max_price=100` |
| `category` | string | Category slug | `category=music` |
| `subcategory` | string | Subcategory slug | `subcategory=jazz` |
| `live_now` | string | Only live events | `live_now=true` |
| `page` | integer | Page number | `page=1` |
| `per_page` | integer | Items per page (1-100) | `per_page=20` |

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Amsterdam Jazz Night",
      "description": "An evening of smooth jazz...",
      "category_id": 1,
      "subcategory_id": 1,
      "price": "35.00",
      "dresscode": "smart casual",
      "min_age": 18,
      "start_datetime": "2025-01-15T20:00:00.000000Z",
      "end_datetime": "2025-01-16T01:00:00.000000Z",
      "city": "Amsterdam",
      "address": "Weteringsschans 6-8, 1017 SG Amsterdam",
      "is_published": true,
      "is_live_now": true,
      "latitude": 52.3622,
      "longitude": 4.8839,
      "distance_km": 2.5,
      "created_at": "2025-01-15T18:00:00.000000Z",
      "updated_at": "2025-01-15T18:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

#### Example Requests

**Search Events:**
```http
GET /api/v1/events?search=jazz
```

**Filter by Category and Location:**
```http
GET /api/v1/events?category=music&subcategory=jazz&lat=52.3676&lng=4.9376&radius=10
```

**Live Events in Price Range:**
```http
GET /api/v1/events?live_now=true&min_price=0&max_price=50
```

**Date Range with Pagination:**
```http
GET /api/v1/events?from_date=2025-01-01&to_date=2025-01-31&page=1&per_page=10
```

### Get Single Event
```http
GET /api/v1/events/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Amsterdam Jazz Night",
    "description": "An evening of smooth jazz...",
    "category_id": 1,
    "subcategory_id": 1,
    "price": "35.00",
    "dresscode": "smart casual",
    "min_age": 18,
    "start_datetime": "2025-01-15T20:00:00.000000Z",
    "end_datetime": "2025-01-16T01:00:00.000000Z",
    "city": "Amsterdam",
    "address": "Weteringsschans 6-8, 1017 SG Amsterdam",
    "is_published": true,
    "is_live_now": true,
    "latitude": 52.3622,
    "longitude": 4.8839,
    "created_at": "2025-01-15T18:00:00.000000Z",
    "updated_at": "2025-01-15T18:00:00.000000Z"
  }
}
```

---

## Special Fields

### is_live_now
A computed boolean field that indicates if an event is currently running based on the current server time and the event's start/end datetime.

### distance_km
Only present when using geo filters (lat/lng/radius). Distance from the search point in kilometers, rounded to 2 decimal places.

### latitude/longitude
Extracted from PostGIS location data for easier frontend use.

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 404 | Resource not found (invalid event ID or category slug) |
| 422 | Validation error (invalid parameters) |
| 500 | Server error |

---

## Rate Limiting
Currently no rate limiting is implemented.

## Tips for Frontend Implementation

1. **Caching Categories**: Categories rarely change, consider caching the `/api/v1/categories` response for 24 hours.

2. **Debouncing Search**: Implement debouncing for the search parameter to avoid excessive API calls.

3. **Progressive Loading**: Start with basic filters, then apply more specific ones as user interacts.

4. **Geo Search**: For map views, use the lat/lng/radius parameters with the user's viewport bounds.

5. **Live Updates**: For "Live Now" functionality, refresh events every minute when displaying live events.

6. **Pagination**: Use the meta object to implement infinite scroll or pagination controls.

7. **Error Handling**: Always check the `success` field and display appropriate error messages.

8. **Date Formatting**: Dates are in ISO 8601 format. Use appropriate date libraries for display.

## Example Frontend Implementation (React)

```javascript
// API Service
class EventsAPI {
  constructor(baseURL) {
    this.baseURL = baseURL;
  }

  async getEvents(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const response = await fetch(`${this.baseURL}/events?${queryString}`);
    return response.json();
  }

  async getCategories() {
    const response = await fetch(`${this.baseURL}/categories`);
    return response.json();
  }

  async getEvent(id) {
    const response = await fetch(`${this.baseURL}/events/${id}`);
    return response.json();
  }
}

// Usage Example
const api = new EventsAPI('http://your-domain.com/api/v1');

// Get live music events in Amsterdam
api.getEvents({
  category: 'music',
  lat: 52.3676,
  lng: 4.9376,
  radius: 10,
  live_now: true,
  per_page: 20
}).then(data => {
  console.log(data.data); // Events array
  console.log(data.meta); // Pagination info
});
```
