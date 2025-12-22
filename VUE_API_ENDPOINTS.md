# Vue Frontend API Endpoints

## Base URL
```
http://your-domain.com/api/v1
```

## Categories Endpoints

### GET /categories
Get all categories with subcategories

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
    }
  ]
}
```

### GET /categories/{slug}
Get single category with subcategories

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

### GET /categories/{slug}/subcategories
Get subcategories for a category

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
      }
    ]
  }
}
```

## Events Endpoints

### GET /events
List events with filters

**Query Parameters:**
- `search` - Search in title/description
- `lat` - Latitude
- `lng` - Longitude
- `radius` - Radius in km
- `from_date` - YYYY-MM-DD
- `to_date` - YYYY-MM-DD
- `min_price` - Decimal
- `max_price` - Decimal
- `category` - Category slug
- `subcategory` - Subcategory slug
- `sessions` - Comma-separated session names (morning,afternoon,evening,night)
- `morning` - Boolean (true/false) - Alternative to sessions parameter
- `afternoon` - Boolean (true/false) - Alternative to sessions parameter
- `evening` - Boolean (true/false) - Alternative to sessions parameter
- `night` - Boolean (true/false) - Alternative to sessions parameter
- `live_now` - true/false
- `page` - Page number
- `per_page` - Items per page (1-100)

**Response:**
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
      "morning": false,
      "afternoon": false,
      "evening": true,
      "night": false,
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

### GET /events/{id}
Get single event

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
    "morning": false,
    "afternoon": false,
    "evening": true,
    "night": false,
    "latitude": 52.3622,
    "longitude": 4.8839,
    "created_at": "2025-01-15T18:00:00.000000Z",
    "updated_at": "2025-01-15T18:00:00.000000Z"
  }
}
```

## Important Notes

1. **Category/Subcategory**: Use slugs, not IDs
2. **Live Events**: Refresh every 60 seconds when `live_now=true`
3. **Distance**: `distance_km` only appears with geo filters
4. **Pagination**: Use `meta` object for pagination controls
5. **Date Format**: ISO 8601, convert for display
6. **Price**: String format, null = Free event
