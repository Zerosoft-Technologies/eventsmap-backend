<?php

namespace App\Services\V2;

use App\Models\User;
use App\Models\UserChatBlock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ChatBlockService
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
    ) {}

    /**
     * @return array{
     *   blocked: list<int>,
     *   blocked_by: list<int>,
     *   blocked_users: list<array{id: int, name: string, profile_type: string|null, avatar: string|null}>
     * }
     */
    public function listForUser(User $user): array
    {
        $blockedIds = UserChatBlock::query()
            ->where('blocker_id', $user->id)
            ->pluck('blocked_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $blockedBy = UserChatBlock::query()
            ->where('blocked_id', $user->id)
            ->pluck('blocker_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $blockedUsers = [];
        if ($blockedIds !== []) {
            $columns = ['id', 'name', 'profile_type'];
            if (Schema::hasColumn('users', 'profile_image')) {
                $columns[] = 'profile_image';
            }

            $blockedUsers = User::query()
                ->whereIn('id', $blockedIds)
                ->get($columns)
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'profile_type' => $u->profile_type,
                    'avatar' => Schema::hasColumn('users', 'profile_image')
                        ? ($u->profile_image ?? null)
                        : null,
                ])
                ->values()
                ->all();
        }

        return [
            'blocked' => $blockedIds,
            'blocked_by' => $blockedBy,
            'blocked_users' => $blockedUsers,
        ];
    }

    public function isMessagingBlocked(int $userA, int $userB): bool
    {
        if ($userA === $userB) {
            return false;
        }

        return UserChatBlock::query()
            ->where(function ($query) use ($userA, $userB) {
                $query->where(function ($inner) use ($userA, $userB) {
                    $inner->where('blocker_id', $userA)->where('blocked_id', $userB);
                })->orWhere(function ($inner) use ($userA, $userB) {
                    $inner->where('blocker_id', $userB)->where('blocked_id', $userA);
                });
            })
            ->exists();
    }

    public function block(User $blocker, int $blockedUserId): void
    {
        if ($blockedUserId === $blocker->id) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot block yourself.'],
            ]);
        }

        if (! User::query()->whereKey($blockedUserId)->exists()) {
            throw new ModelNotFoundException('User not found.');
        }

        DB::transaction(function () use ($blocker, $blockedUserId) {
            UserChatBlock::query()->firstOrCreate([
                'blocker_id' => $blocker->id,
                'blocked_id' => $blockedUserId,
            ]);
        });

        $this->firebaseService->syncChatBlock($blocker->id, $blockedUserId, true);
    }

    public function unblock(User $blocker, int $blockedUserId): void
    {
        UserChatBlock::query()
            ->where('blocker_id', $blocker->id)
            ->where('blocked_id', $blockedUserId)
            ->delete();

        $this->firebaseService->syncChatBlock($blocker->id, $blockedUserId, false);
    }
}
