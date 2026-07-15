<?php

namespace App\Rules;

use App\Support\Recurrence\RecurrenceRulesSchema;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates recurrence_rules JSON against the recurrence_type on the same request.
 *
 * Usage (future FormRequest):
 *   'recurrence_rules' => ['required', 'array', new RecurrenceRulesMatchType()],
 *   'recurrence_type' => ['required', ...],
 */
class RecurrenceRulesMatchType implements ValidationRule
{
    public function __construct(
        private readonly ?string $recurrenceType = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be an object (array).');

            return;
        }

        $type = $this->recurrenceType;
        if ($type === null && function_exists('request')) {
            $type = request()->input('recurrence_type');
        }

        if (! is_string($type) || $type === '') {
            $fail('recurrence_type must be set to validate recurrence_rules.');

            return;
        }

        $errors = RecurrenceRulesSchema::validateForType($type, $value);
        foreach ($errors as $message) {
            $fail($message);
        }
    }
}
