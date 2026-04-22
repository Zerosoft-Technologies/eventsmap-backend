<?php

namespace App\Http\Resources\MyAccount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin array{
 *     summary: array<string, int>,
 *     invites: Collection<int, array<string, mixed>>
 * }
 */
class AccountInvitesIndexResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => (new AccountInvitesSummaryResource($this->resource['summary']))->resolve(),
            'invites' => $this->resource['invites']
                ->map(fn (array $row): array => (new AccountInviteResource($row))->resolve())
                ->values()
                ->all(),
        ];
    }
}
