<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

trait HasMetaKeywords
{
    /**
     * Set the meta_keywords attribute.
     * Accepts both array and comma-separated string.
     *
     * @param array|string $value
     * @return void
     */
    public function setMetaKeywordsAttribute($value)
    {
        if (is_string($value)) {
            // Convert comma-separated string to array
            $keywords = array_map('trim', explode(',', $value));
            $keywords = array_filter($keywords); // Remove empty values
            $this->attributes['meta_keywords'] = json_encode(array_values($keywords));
        } elseif (is_array($value)) {
            $this->attributes['meta_keywords'] = json_encode(array_values($value));
        } else {
            $this->attributes['meta_keywords'] = null;
        }
    }
    
    /**
     * Get the meta_keywords attribute.
     * Always returns as array.
     *
     * @param string $value
     * @return array
     */
    public function getMetaKeywordsAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }
}

// Usage in Event Model:
/*
class Event extends Model
{
    use HasFactory, SoftDeletes, HasMetaKeywords;
    
    // ... rest of the model
}
*/
