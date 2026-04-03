# Fix for subcategory_ids Storage Issue

## Summary
Fixed the issue where `subcategory_ids` was not being stored in the `events_v2.subcategory_ids` column and was only using the pivot table.

## Changes Made

### 1. EventService.php
- **Create Method**: Added `subcategory_ids`, `invited_talents`, `invited_organisers`, and `invited_venues` to `$optionalFields` array
- **Create Method**: Removed `sync()` operation for subcategories (now stored directly in column)
- **Update Method**: Removed `sync()` operation for subcategories
- **Added Helper Method**: `loadEventWithSubcategories()` to properly load events with subcategories from IDs
- **Updated Return Statements**: All methods now use the helper to load subcategories from IDs

### 2. EventV2 Model
- **Added Accessor**: `getSubcategoriesFromIdsAttribute()` method to fetch subcategories based on IDs stored in the column
- **Existing Configuration**: 
  - `subcategory_ids` is already in `$fillable`
  - Already cast as `array` in `$casts`

### 3. Controllers Updated
- **EventController**:
  - Removed `subcategories` from `with()` queries
  - Added manual loading using `setRelation('subcategories', $event->subcategories_from_ids)`
- **PublicEventController**:
  - Updated `show()` and `showBySlug()` methods
- **WishlistController**:
  - Updated to manually load subcategories for each event
- **AdminEventV2Controller**:
  - Removed `subcategories` from `with()` query

### 4. EventResource.php
- Updated `subcategories` attribute to handle both loaded relations and fallback to IDs
- Now properly displays subcategories whether loaded from relation or fetched from IDs

## How It Works Now

1. **CREATE/UPDATE**: `subcategory_ids` array is stored directly in the `events_v2.subcategory_ids` JSON column
2. **READ**: Subcategories are fetched using `whereIn('id', $event->subcategory_ids)` 
3. **Response**: Both `subcategory_ids` (array) and `subcategories` (objects) are returned in API responses

## Database Schema
- Column: `events_v2.subcategory_ids`
- Type: JSON
- Stores: `[1, 2, 3]`

## API Response Format
```json
{
  "subcategory_ids": [1, 2],
  "subcategories": [
    { "id": 1, "name": "Alternative", "slug": "alternative" },
    { "id": 2, "name": "Rock", "slug": "rock" }
  ]
}
```

## Benefits
- No longer dependent on pivot table for subcategory storage
- Simpler database structure
- Faster queries (no JOIN needed for basic subcategory IDs)
- Maintains backward compatibility in API responses
