# Database Seeding Guide

This guide explains how to populate your database with sample data for the Event Details feature.

## Seeders Overview

### 1. TalentSeeder
- Creates 10 sample talents/artists
- Includes names, bios, images, and social media links
- Artists include: DJ Shadow, The Midnight, Armin van Buuren, Deadmau5, Carly Rae Jepsen, The Weeknd, Martin Garrix, Dua Lipa, David Guetta, Billie Eilish

### 2. EventDetailsSeeder
- Updates existing events with extended fields
- Generates slugs for all events
- Adds pricing ranges, venue details, contact info
- Creates about sections with accessibility info, rules, and tips
- Generates location details with directions and parking info
- Adds booking information and social media links
- Creates 3-8 images per event
- Assigns 2-5 talents to each event with roles

## Running Seeders

### Fresh Database (Recommended)
```bash
# Drop all tables and re-run migrations
php artisan migrate:fresh --seed
```

### Existing Database
```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=TalentSeeder
php artisan db:seed --class=EventDetailsSeeder
```

## What Gets Created

### Talents Table
- 10 active talents with complete profiles
- Each talent has:
  - Name and slug
  - Profile image (using Picsum for placeholders)
  - Biography
  - Social media links (Spotify, Instagram, Website)

### Event Images Table
- 3-8 images per event
- First image marked as primary
- Random alt text and captions
- Proper sort ordering

### Event Talent Pivot Table
- 2-5 talents assigned to each event
- Roles: Headliner, Supporting Act, Opening Act, Special Guest, DJ Set
- Proper sort ordering

### Extended Event Fields
All existing events will be updated with:
- SEO-friendly slugs
- Price ranges (min/max based on existing price)
- Venue names (random NYC venues)
- Contact information
- Cover images and video URLs
- Complete about sections
- Detailed location information
- Booking configurations
- Social media links
- Meta tags and descriptions
- Random view counts
- Tags (3-8 per event)

## Sample Data Highlights

### Venues
- Madison Square Garden
- Central Park Amphitheater
- Brooklyn Bowl
- Terminal 5
- Radio City Music Hall
- And more...

### Organizers
- Live Nation Entertainment
- AEG Presents
- Event Productions Inc.
- NYC Concert Promotions
- And more...

### Tags
Music, festival, concert, live, outdoor, indoor, summer, winter, electronic, rock, pop, jazz, dance, party, nightlife, family, all-ages, 21+, vip, weekend, holiday, special-event, charity

## Notes

- All images use Picsum Photos with unique seeds for variety
- Contact info uses generated but realistic email formats
- View counts are randomized between 100-5000
- 30% of events are marked as featured
- All events are published and not cancelled
- Ticket prices range from $25-1000 based on event type

## Refreshing Data

To reseed with fresh random data:
```bash
php artisan migrate:fresh --seed
```

This will:
1. Drop all tables
2. Re-run all migrations
3. Populate with fresh seed data
