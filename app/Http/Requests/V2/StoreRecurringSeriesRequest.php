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

class StoreRecurringSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RecurringSeries::class) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Only premium users can create recurring series.',
        ], 403));
    }

    protected function prepareForValidation(): void
    {
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
            RecurringSeriesValidation::v2CreateRules(),
            [
                'source_event_id' => ['required_without:event_template', 'nullable', 'integer', 'exists:events_v2,id', new ValidRecurringSourceEvent],
                'event_template' => ['required_without:source_event_id', 'nullable', 'array'],
            ],
            RecurringEventTemplateValidation::rules('event_template'),
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
