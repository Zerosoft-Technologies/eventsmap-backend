<?php

namespace App\Rules;

use App\Models\EventV2;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class ValidRecurringSourceEvent implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $eventId = (int) $value;
        $event = EventV2::query()->find($eventId);

        if (! $event) {
            $fail('The selected source event does not exist.');

            return;
        }

        $user = Auth::user();
        if (! $user || ((int) $event->user_id !== (int) $user->id && ! $user->isAdmin())) {
            $fail('You can only use your own events as a recurring series template.');

            return;
        }

        if ($event->isSeriesInstance()) {
            $fail('Series instances cannot be used as templates. Select a standalone event.');
        }
    }
}
