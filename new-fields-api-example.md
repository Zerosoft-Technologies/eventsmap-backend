// Example API Request - POST /api/admin/events
{
  "title": "Tech Conference 2026",
  "slug": "tech-conference-2026",
  "status": "published",
  "description": "Annual technology conference featuring the latest innovations",
  "short_description": "Join us for the biggest tech event of the year",
  "category_id": 1,
  "subcategory_id": 2,
  "price": "299.99",
  "min_price": "199.99",
  "max_price": "499.99",
  "currency": "USD",
  "dresscode": "Business casual",
  "age_restriction": "All ages",
  "min_age": null,
  "max_age": null,
  "start_datetime": "2026-03-15T09:00:00Z",
  "end_datetime": "2026-03-17T18:00:00Z",
  "timezone": "America/New_York",
  "is_all_day": false,
  "is_recurring": false,
  "venue_name": "Convention Center",
  "venue_address": "123 Main St",
  "city": "New York",
  "state": "New York",
  "postal_code": "10001",
  "country": "USA",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "organizer_name": "Tech Events Inc",
  "contact_email": "info@techevents.com",
  "contact_phone": "+1-555-0123",
  "contact_info": {
    "email": "info@techevents.com",
    "phone": "+1-555-0123",
    "website": "https://techevents.com"
  },
  "cover_image": "events/tech-conference-cover.jpg",
  "video_url": "https://youtube.com/watch?v=example",
  "about": {
    "description": "About the conference"
  },
  "location_details": {
    "parking": "Available",
    "public_transport": "Subway, Bus"
  },
  "booking": {
    "website": "https://techevents.com/register",
    "phone": "+1-555-0123"
  },
  "social_links": {
    "facebook": "https://facebook.com/techconf2026",
    "twitter": "https://twitter.com/techconf2026"
  },
  "highlights": [
    "50+ Speakers",
    "100+ Workshops",
    "Networking opportunities",
    "Exhibition hall"
  ],
  "requirements": [
    "Registration required",
    "Valid ID for check-in"
  ],
  "additional_info": "Additional conference details",
  "accessibility_info": "Wheelchair accessible venue",
  "is_ticketed": true,
  "is_free": false,
  "capacity": 5000,
  "registration_url": "https://techevents.com/register",
  "registration_deadline": "2026-03-10T23:59:59Z",
  "meta_keywords": [
    "technology",
    "conference",
    "innovation",
    "networking",
    "tech"
  ],
  "internal_notes": "VIP speakers need special handling. Parking arrangements for sponsors.",
  "custom_fields": {
    "sponsor_level": "platinum",
    "catering": "included"
  },
  "meta_title": "Tech Conference 2026 - Register Now",
  "meta_description": "Join Tech Conference 2026 for 3 days of innovation and networking",
  "tags": ["tech", "conference", "innovation"],
  "morning": true,
  "afternoon": true,
  "evening": false,
  "night": false
}

// Example API Response - GET /api/admin/events/91
{
  "success": true,
  "data": {
    "id": 91,
    "title": "Tech Conference 2026",
    "slug": "tech-conference-2026",
    "description": "Annual technology conference featuring the latest innovations",
    "short_description": "Join us for the biggest tech event of the year",
    "status": "published",
    
    "category": {
      "id": 1,
      "name": "Technology",
      "slug": "technology"
    },
    "category_id": 1,
    
    "subcategory": {
      "id": 2,
      "name": "Conference",
      "slug": "conference"
    },
    "subcategory_id": 2,
    
    "price": "299.99",
    "min_price": "199.99",
    "max_price": "499.99",
    "currency": "USD",
    
    "start_datetime": "2026-03-15T09:00:00.000000Z",
    "end_datetime": "2026-03-17T18:00:00.000000Z",
    "timezone": "America/New_York",
    
    "venue_name": "Convention Center",
    "address": "123 Main St",
    "city": "New York",
    "state": "New York",
    "postal_code": "10001",
    "country": "USA",
    "latitude": 40.7128,
    "longitude": -74.0060,
    
    "dresscode": "Business casual",
    "age_restriction": "All ages",
    "min_age": null,
    "max_age": null,
    
    "organizer_name": "Tech Events Inc",
    "organizer_id": 5,
    "contact_info": {
      "email": "info@techevents.com",
      "phone": "+1-555-0123",
      "website": "https://techevents.com"
    },
    
    "cover_image": "events/tech-conference-cover.jpg",
    "video_url": "https://youtube.com/watch?v=example",
    "images": [],
    
    "talents": [],
    
    "about": {
      "description": "About the conference"
    },
    "location_details": {
      "parking": "Available",
      "public_transport": "Subway, Bus"
    },
    "booking": {
      "website": "https://techevents.com/register",
      "phone": "+1-555-0123"
    },
    "social_links": {
      "facebook": "https://facebook.com/techconf2026",
      "twitter": "https://twitter.com/techconf2026"
    },
    
    "highlights": [
      "50+ Speakers",
      "100+ Workshops",
      "Networking opportunities",
      "Exhibition hall"
    ],
    "requirements": [
      "Registration required",
      "Valid ID for check-in"
    ],
    "additional_info": "Additional conference details",
    "accessibility_info": "Wheelchair accessible venue",
    
    "is_ticketed": true,
    "is_free": false,
    "capacity": 5000,
    "registration_url": "https://techevents.com/register",
    "registration_deadline": "2026-03-10T23:59:59.000000Z",
    "meta_keywords": [
      "technology",
      "conference",
      "innovation",
      "networking",
      "tech"
    ],
    "internal_notes": "VIP speakers need special handling. Parking arrangements for sponsors.",
    "custom_fields": {
      "sponsor_level": "platinum",
      "catering": "included"
    },
    
    "is_published": true,
    "is_featured": false,
    "is_cancelled": false,
    "is_archived": false,
    "is_live_now": false,
    
    "meta_title": "Tech Conference 2026 - Register Now",
    "meta_description": "Join Tech Conference 2026 for 3 days of innovation and networking",
    "tags": ["tech", "conference", "innovation"],
    
    "view_count": 150,
    
    "morning": true,
    "afternoon": true,
    "evening": false,
    "night": false,
    
    "published_at": "2026-01-28T10:00:00.000000Z",
    "featured_at": null,
    "cancelled_at": null,
    "archived_at": null,
    
    "created_at": "2026-01-28T09:00:00.000000Z",
    "updated_at": "2026-01-28T10:00:00.000000Z",
    "deleted_at": null
  }
}
