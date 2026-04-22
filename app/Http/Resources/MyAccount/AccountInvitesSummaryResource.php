<?php

namespace App\Http\Resources\MyAccount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     events_count:int,
 *     talents:int,
 *     organisers:int,
 *     venues:int,
 *     total:int
 * }
 */
class AccountInvitesSummaryResource extends JsonResource
{
    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'events_count' => (int) $this->resource['events_count'],
            'talents' => (int) $this->resource['talents'],
            'organisers' => (int) $this->resource['organisers'],
            'venues' => (int) $this->resource['venues'],
            'total' => (int) $this->resource['total'],
        ];
    }
}
