<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitation received by the authenticated user with full {@see EventV2} payload.
 *
 * @mixin \App\Models\EventInvitation
 */
class ReceivedInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invitation_id' => $this->id,
            'event_id' => $this->event_id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'receiver_type' => $this->receiver_type,
            'status' => $this->status,
            'invited_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'email' => $this->sender->email,
                ];
            }),
            'event' => $this->when(
                $this->relationLoaded('event') && $this->event,
                fn () => (new EventResource($this->event))->toArray($request)
            ),
        ];
    }
}
