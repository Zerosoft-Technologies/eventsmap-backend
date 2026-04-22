<?php

namespace App\Http\Controllers;

use App\Http\Requests\MyAccount\RemoveAccountInviteRequest;
use App\Http\Resources\MyAccount\AccountInvitesIndexResource;
use App\Services\AccountInvitesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyAccountInvitesController extends Controller
{
    public function __construct(
        private readonly AccountInvitesService $accountInvitesService
    ) {}

    /**
     * GET /api/my-account/invites
     */
    public function index(Request $request): AccountInvitesIndexResource
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:talent,organiser,venue,organizer'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'] ?? null;
        if ($type === 'organizer') {
            $type = 'organiser';
        }

        $payload = $this->accountInvitesService->indexForOwner(
            (int) $request->user()->id,
            $type,
            isset($validated['event_id']) ? (int) $validated['event_id'] : null,
            $validated['search'] ?? null,
        );

        return new AccountInvitesIndexResource($payload);
    }

    /**
     * DELETE /api/my-account/invites
     */
    public function destroy(RemoveAccountInviteRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->accountInvitesService->removeInvite(
            (int) $request->user()->id,
            (int) $data['event_id'],
            (string) $data['type'],
            (int) $data['profile_id'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Invite removed successfully',
        ]);
    }
}
