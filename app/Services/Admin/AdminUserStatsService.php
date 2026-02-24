<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminUserStatsService
{
    /**
     * Get user statistics using optimized single query.
     */
    public function getStats(): array
    {
        $stats = User::query()
            ->select([
                DB::raw('COUNT(*) as total_users'),
                DB::raw("SUM(CASE WHEN account_type = 'free' THEN 1 ELSE 0 END) as free_users"),
                DB::raw("SUM(CASE WHEN account_type = 'premium' AND status = 'active' THEN 1 ELSE 0 END) as premium_active"),
                DB::raw("SUM(CASE WHEN account_type = 'premium' AND status = 'pending_payment' THEN 1 ELSE 0 END) as premium_pending"),
                DB::raw("SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended"),
            ])
            ->first();

        return [
            'total_users' => (int) $stats->total_users,
            'free_users' => (int) $stats->free_users,
            'premium_active' => (int) $stats->premium_active,
            'premium_pending' => (int) $stats->premium_pending,
            'suspended' => (int) $stats->suspended,
        ];
    }
}
