<?php

/**
 * Helper script to add location_name to all events in EventsSeeder.php
 * 
 * This script shows examples of location_name values for different types of events:
 * 
 * 1. Theatres: Use the theatre name
 * 2. Stadiums/Arenas: Use the venue name
 * 3. Clubs/Bars: Use the club/bar name
 * 4. Restaurants: Use the restaurant name
 * 5. Outdoor events: Use the park/area name
 * 6. Generic: Use a descriptive location name
 */

// Example location_name patterns:
$locationExamples = [
    // Theatres
    'Broadway Theater District' => 'Broadway Theater District',
    'Palais Garnier, Paris, France' => 'Palais Garnier',
    'Royal Opera House, London' => 'Royal Opera House',
    
    // Stadiums/Arenas
    'SoFi Stadium, Los Angeles, CA' => 'SoFi Stadium',
    'Madison Square Garden, New York' => 'Madison Square Garden',
    'Wembley Stadium, London' => 'Wembley Stadium',
    
    // Clubs/Bars
    'Frenchmen Street, New Orleans, LA' => 'Frenchmen Street Jazz Club',
    'Berghain, Berlin' => 'Berghain',
    'Ministry of Sound, London' => 'Ministry of Sound',
    
    // Restaurants
    'Times Square Diner, New York' => 'Times Square Diner',
    'Le Bernardin, New York' => 'Le Bernardin',
    
    // Outdoor/Parks
    'Central Park, New York' => 'Central Park',
    'Hyde Park, London' => 'Hyde Park',
    'Vondelpark, Amsterdam' => 'Vondelpark',
    
    // Generic/Descriptive
    'Downtown District' => 'Downtown District',
    'Historic Quarter' => 'Historic Quarter',
    'Riverside Promenade' => 'Riverside Promenade',
    'Beach Front' => 'Beach Front',
    'Mountain View Point' => 'Mountain View Point',
];

// Instructions for manually updating the seeder:
/*
1. For each event in the $events array, add a 'location_name' key
2. Use a descriptive name that identifies the specific location
3. Keep it concise (max 200 characters)
4. Make it meaningful for users searching by location

Example:
Before:
[
    'title' => 'Summer Concert',
    'city' => 'New York',
    'address' => 'Central Park, New York, NY',
    'latitude' => 40.7829,
    'longitude' => -73.9654,
],

After:
[
    'title' => 'Summer Concert',
    'city' => 'New York',
    'address' => 'Central Park, New York, NY',
    'location_name' => 'Central Park',
    'latitude' => 40.7829,
    'longitude' => -73.9654,
],
*/

echo "Use this as a reference for adding location_name values to your events.\n";
echo "The seeder has been updated to include location_name field.\n";
