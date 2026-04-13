<?php

namespace App\Http\Requests\V2\Concerns;

trait NormalizesVenueOpeningHoursInput
{
    protected function prepareOpeningHoursForValidation(): void
    {
        if (! array_key_exists('opening_hours', $this->all())) {
            return;
        }

        $raw = $this->input('opening_hours');

        if (is_array($raw)) {
            return;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            $this->merge([
                'opening_hours' => $trimmed === '' ? [] : [$trimmed],
            ]);

            return;
        }

        if ($raw === null || $raw === '') {
            $this->merge(['opening_hours' => []]);

            return;
        }

        $this->merge(['opening_hours' => [(string) $raw]]);
    }
}
