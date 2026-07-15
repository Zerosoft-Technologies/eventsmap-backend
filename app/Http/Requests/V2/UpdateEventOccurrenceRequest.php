<?php

namespace App\Http\Requests\V2;

use App\Models\EventV2;

class UpdateEventOccurrenceRequest extends UpdateEventRequest
{
    public function authorize(): bool
    {
        $event = EventV2::find($this->route('id'));

        if (! $event || ! $event->isSeriesInstance()) {
            return false;
        }

        $user = $this->user();

        return $user && ($event->isOwner($user) || $user->isAdmin());
    }

    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You are not authorized to update this event occurrence.',
        ], 403));
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'series_id' => 'prohibited',
            'is_modified' => 'prohibited',
        ]);
    }
}
