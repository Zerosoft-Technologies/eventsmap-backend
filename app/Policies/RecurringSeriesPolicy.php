<?php

namespace App\Policies;

use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\Premium\PremiumAccess;

/**
 * RecurringSeriesPolicy — V2 user API authorization.
 *
 * Create requires premium entitlement. Update/delete are owner-only on V2 routes.
 * Admins use dedicated admin endpoints.
 */
class RecurringSeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecurringSeries $series): bool
    {
        return $series->isOwner($user);
    }

    public function create(User $user): bool
    {
        return PremiumAccess::isEntitled($user);
    }

    public function update(User $user, RecurringSeries $series): bool
    {
        return $series->isOwner($user);
    }

    public function delete(User $user, RecurringSeries $series): bool
    {
        return $series->isOwner($user);
    }
}
