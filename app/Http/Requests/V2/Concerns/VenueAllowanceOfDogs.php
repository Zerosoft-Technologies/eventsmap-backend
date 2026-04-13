<?php

namespace App\Http\Requests\V2\Concerns;

final class VenueAllowanceOfDogs
{
    /**
     * Values sent by the venue form (must match frontend & stay DB-safe as slugs).
     */
    public const VALUES = [
        'all-dogs',
        'small-dogs',
        'no-dogs-assistance',
        'no-dogs-included',
        'no-dogs',
    ];
}
