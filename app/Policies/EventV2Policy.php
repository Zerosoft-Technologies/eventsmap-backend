<?php

namespace App\Policies;

use App\Models\EventV2;
use App\Models\User;

/**
 * EventV2Policy - Authorization policy for V2 Events.
 *
 * Defines access control rules for event operations:
 * - Owners can update/delete their own events
 * - Admins can perform all operations
 * - Only admins can restore, force delete, or moderate events
 */
class EventV2Policy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EventV2 $event): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Owner or Admin can update.
     */
    public function update(User $user, EventV2 $event): bool
    {
        return $event->isOwner($user) || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete (soft delete) the model.
     * Owner or Admin can soft delete.
     */
    public function delete(User $user, EventV2 $event): bool
    {
        return $event->isOwner($user) || $user->isAdmin();
    }

    /**
     * Determine whether the user can restore a soft-deleted model.
     * Only Admin can restore.
     */
    public function restore(User $user, EventV2 $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only Admin can force delete.
     */
    public function forceDelete(User $user, EventV2 $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can moderate (approve/suspend/cancel) the model.
     * Only Admin can moderate.
     */
    public function moderate(User $user, EventV2 $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view trashed models.
     * Only Admin can view trashed.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->isAdmin();
    }
}
