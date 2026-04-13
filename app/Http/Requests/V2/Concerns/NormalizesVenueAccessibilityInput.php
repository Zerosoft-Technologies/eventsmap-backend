<?php

namespace App\Http\Requests\V2\Concerns;

trait NormalizesVenueAccessibilityInput
{
    protected function prepareVenueAccessibilityForValidation(): void
    {
        $merge = [];

        if ($this->has('accessible_parking') && ! $this->has('parking')) {
            $merge['parking'] = $this->input('accessible_parking');
        }
        if ($this->has('valet_parking') && ! $this->has('valet')) {
            $merge['valet'] = $this->input('valet_parking');
        }
        if ($this->has('childrens_play_area') && ! $this->has('play_area')) {
            $merge['play_area'] = $this->input('childrens_play_area');
        }

        if ($this->filled('allowance_of_dogs') && ! $this->has('allow_dogs')) {
            $policy = (string) $this->input('allowance_of_dogs');
            $merge['allow_dogs'] = in_array($policy, ['all-dogs', 'small-dogs'], true);
        }

        foreach (['allow_dogs', 'wheelchair_accessible', 'parking', 'valet', 'play_area'] as $field) {
            $raw = array_key_exists($field, $merge) ? $merge[$field] : $this->input($field);
            $normalized = $this->normalizeYesNoToBool($raw);
            if ($normalized !== null) {
                $merge[$field] = $normalized;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @param  mixed  $value
     */
    protected function normalizeYesNoToBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['yes', 'true', '1', 'on'], true)) {
            return true;
        }
        if (in_array($v, ['no', 'false', '0', 'off'], true)) {
            return false;
        }

        return null;
    }
}
