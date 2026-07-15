<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Rules\ValidRecurringSourceEvent;
use App\Support\Recurrence\RecurringEventTemplateFromEvent;
use App\Validation\Recurring\RecurringEventTemplateValidation;
use App\Validation\Recurring\RecurringSeriesValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRecurringSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $series = RecurringSeries::find($this->route('id'));

        if (! $series) {
            return false;
        }

        return $this->user()?->can('update', $series) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You are not authorized to update this recurring series.',
        ], 403));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('recurrence_type')) {
            $series = RecurringSeries::find($this->route('id'));
            if ($series) {
                $this->merge(['recurrence_type' => $series->recurrence_type]);
            }
        }

        if ($this->filled('source_event_id') && ! $this->has('event_template')) {
            $event = EventV2::query()
                ->with(['organisers', 'talents'])
                ->find($this->input('source_event_id'));

            if ($event) {
                $this->merge([
                    'event_template' => RecurringEventTemplateFromEvent::extract($event),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return array_merge(
            RecurringSeriesValidation::v2UpdateRules(),
            [
                'source_event_id' => ['sometimes', 'nullable', 'integer', 'exists:events_v2,id', new ValidRecurringSourceEvent],
            ],
            RecurringEventTemplateValidation::updateRules('event_template'),
            ['apply_to_future' => ['sometimes', 'boolean']],
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
