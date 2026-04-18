<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotent V2 demo accounts: one User per profile_type × account_type pair.
 *
 * The app uses a single {@see User} model with profile_type and account_type enums
 * (no separate EventV2/TalentV2 user tables).
 */
class AccountsV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->accounts() as $row) {
                User::updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name' => $row['name'],
                        // Cast on User hashes plaintext once (do not pass bcrypt output here).
                        'password' => $row['password'],
                        'role' => User::ROLE_USER,
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'profile_type' => $row['profile_type'],
                        'account_type' => $row['account_type'],
                        'status' => User::STATUS_ACTIVE,
                        'billing_type' => $row['billing_type'] ?? 'private',
                        'country' => $row['country'] ?? 'NL',
                        'city' => $row['city'] ?? 'Amsterdam',
                        'premium_started_at' => $row['account_type'] === User::ACCOUNT_PREMIUM
                            ? now()->subMonths(rand(1, 12))
                            : null,
                        'stripe_subscription_id' => $row['account_type'] === User::ACCOUNT_PREMIUM
                            ? 'sub_seed_'.Str::random(14)
                            : null,
                    ]
                );
            }
        });

        $this->command?->info('AccountsV2Seeder: 8 demo users ensured (event / organiser / talent / venue × free / premium).');
    }

    /**
     * @return list<array{
     *     email: string,
     *     password: string,
     *     name: string,
     *     profile_type: string,
     *     account_type: string,
     *     billing_type?: string,
     *     country?: string,
     *     city?: string
     * }>
     */
    private function accounts(): array
    {
        return [
            // Event creators (V2 events)
            [
                'email' => 'lafit97365@pmdeal.com',
                'password' => 'lafit97365@pmdeal.com',
                'name' => 'Demo Event Host (Free)',
                'profile_type' => User::PROFILE_EVENT,
                'account_type' => User::ACCOUNT_FREE,
            ],
            [
                'email' => 'xigobih514@pmdeal.com',
                'password' => 'xigobih514@pmdeal.com',
                'name' => 'Demo Event Host (Premium)',
                'profile_type' => User::PROFILE_EVENT,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
            // Organisers
            [
                'email' => 'bocoyir498@pmdeal.com',
                'password' => 'bocoyir498@pmdeal.com',
                'name' => 'Demo Organiser (Free)',
                'profile_type' => User::PROFILE_ORGANIZER,
                'account_type' => User::ACCOUNT_FREE,
            ],
            [
                'email' => 'tetasis895@pmdeal.com',
                'password' => 'tetasis895@pmdeal.com',
                'name' => 'Demo Organiser (Premium)',
                'profile_type' => User::PROFILE_ORGANIZER,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
            // Talents
            [
                'email' => 'xixibok340@mypethealh.com',
                'password' => 'xixibok340@mypethealh.com',
                'name' => 'Demo Talent (Free)',
                'profile_type' => User::PROFILE_TALENT,
                'account_type' => User::ACCOUNT_FREE,
            ],
            [
                'email' => 'yovaw11436@pmdeal.com',
                'password' => 'yovaw11436@pmdeal.com',
                'name' => 'Demo Talent (Premium)',
                'profile_type' => User::PROFILE_TALENT,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
            // Venues
            [
                'email' => 'lihey90743@mypethealh.com',
                'password' => 'lihey90743@mypethealh.com',
                'name' => 'Demo Venue (Free)',
                'profile_type' => User::PROFILE_VENUE,
                'account_type' => User::ACCOUNT_FREE,
            ],
            [
                'email' => 'fedox95328@pmdeal.com',
                'password' => 'fedox95328@pmdeal.com',
                'name' => 'Demo Venue (Premium)',
                'profile_type' => User::PROFILE_VENUE,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
        ];
    }
}
