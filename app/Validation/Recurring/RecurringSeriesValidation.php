<?php

namespace App\Validation\Recurring;

use App\Rules\IanaTimezone;
use App\Rules\RecurrenceRulesMatchType;
use App\Rules\RecurringSeriesEndDateWithinHorizon;
use App\Support\Recurrence\RecurrenceType;
use Illuminate\Validation\Rule;

/**
 * Reusable validation rule sets for recurring_series (Phase 1 — no HTTP layer yet).
 *
 * Import into FormRequest classes when APIs are implemented:
 *   public function rules(): array {
 *       return RecurringSeriesValidation::createRules();
 *   }
 */
final class RecurringSeriesValidation
{
    /**
     * Rules for creating a recurring series.
     *
     * @return array<string, mixed>
     */
    public static function createRules(): array
    {
        return [
            'organizer_id' => ['required', 'integer', 'exists:users,id'],
            'recurrence_type' => ['required', 'string', Rule::in(RecurrenceType::v2Supported())],
            'recurrence_rules' => ['required', 'array', new RecurrenceRulesMatchType],
            'timezone' => ['required', 'string', 'max:64', new IanaTimezone],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', new RecurringSeriesEndDateWithinHorizon],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * V2 user API — organizer is inferred from auth; not accepted from client.
     *
     * @return array<string, mixed>
     */
    public static function v2CreateRules(): array
    {
        return [
            'recurrence_type' => ['required', 'string', Rule::in(RecurrenceType::v2Supported())],
            'recurrence_rules' => ['required', 'array', new RecurrenceRulesMatchType],
            'timezone' => ['required', 'string', 'max:64', new IanaTimezone],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', new RecurringSeriesEndDateWithinHorizon],
        ];
    }

    /**
     * V2 user API — owner cannot reassign organizer via API.
     *
     * @return array<string, mixed>
     */
    public static function v2UpdateRules(): array
    {
        return [
            'recurrence_type' => ['sometimes', 'string', Rule::in(RecurrenceType::v2Supported())],
            'recurrence_rules' => ['sometimes', 'array', new RecurrenceRulesMatchType],
            'timezone' => ['sometimes', 'string', 'max:64', new IanaTimezone],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', new RecurringSeriesEndDateWithinHorizon],
        ];
    }

    /**
     * Rules for updating a recurring series.
     *
     * @return array<string, mixed>
     */
    public static function updateRules(): array
    {
        return [
            'organizer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'recurrence_type' => ['sometimes', 'string', Rule::in(RecurrenceType::all())],
            'recurrence_rules' => ['sometimes', 'array', new RecurrenceRulesMatchType],
            'timezone' => ['sometimes', 'string', 'max:64', new IanaTimezone],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', new RecurringSeriesEndDateWithinHorizon],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Rules for linking / updating a series instance on events_v2.
     *
     * @return array<string, mixed>
     */
    public static function eventInstanceRules(): array
    {
        return [
            'series_id' => ['nullable', 'integer', 'exists:recurring_series,id'],
            'is_modified' => ['sometimes', 'boolean'],
        ];
    }
}
