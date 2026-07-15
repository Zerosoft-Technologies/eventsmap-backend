<?php

namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteRecurringSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm' => 'required|accepted',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('confirm') && $this->query('confirm') !== null) {
            $this->merge(['confirm' => $this->query('confirm')]);
        }

        if ($this->has('confirm')) {
            $this->merge([
                'confirm' => filter_var($this->input('confirm'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    protected function failedValidation($validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Irreversible confirmation is required to delete this recurring series.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
