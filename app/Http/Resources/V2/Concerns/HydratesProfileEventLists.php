<?php

namespace App\Http\Resources\V2\Concerns;

use App\Http\Resources\V2\EventResource;
use Illuminate\Support\Collection;

trait HydratesProfileEventLists
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function hydratedEventListAttribute(string $attribute): array
    {
        $raw = $this->resource->getAttribute($attribute);

        if ($raw instanceof Collection) {
            $raw = $raw->values()->all();
        }

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $first = $raw[0] ?? null;
        if (is_array($first) && array_key_exists('id', $first)) {
            return array_values($raw);
        }

        return EventResource::collection($raw)->resolve(request());
    }
}
