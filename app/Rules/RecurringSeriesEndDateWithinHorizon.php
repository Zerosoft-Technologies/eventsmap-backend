<?php

namespace App\Rules;

use App\Support\Recurrence\RecurringSeriesHorizon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class RecurringSeriesEndDateWithinHorizon implements ValidationRule, DataAwareRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $startDate = $this->data['start_date'] ?? null;
        if (! is_string($startDate) || $startDate === '') {
            return;
        }

        $timezone = is_string($this->data['timezone'] ?? null) ? $this->data['timezone'] : null;

        if (! RecurringSeriesHorizon::isEndDateWithinHorizon($startDate, (string) $value, $timezone)) {
            $max = RecurringSeriesHorizon::maxEndDateString($startDate, $timezone);
            $fail("Series end date cannot be later than {$max} (maximum ".RecurringSeriesHorizon::horizonLabel().' from series start).');
        }
    }
}
