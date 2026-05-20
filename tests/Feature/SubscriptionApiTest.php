<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_requires_authentication(): void
    {
        $this->getJson('/api/subscription')->assertUnauthorized();
    }

    public function test_free_user_gets_subscription_overview_without_stripe_record(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_FREE,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.account_type', 'free')
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.actions.can_upgrade', true)
            ->assertJsonPath('data.actions.can_retry_payment', false);
    }

    public function test_premium_user_gets_active_subscription(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'premium_started_at' => now(),
        ]);

        Subscription::query()->create([
            'user_id' => $user->id,
            'account_id' => $user->id,
            'email' => $user->email,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'subscription_status' => 'active',
            'plan_name' => 'Premium Account',
            'billing_interval' => 'monthly',
            'billing_mode' => Subscription::BILLING_MODE_SUBSCRIPTION,
            'currency' => 'eur',
            'amount' => 1999,
            'quantity' => 1,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'payment_method_brand' => 'visa',
            'payment_method_last4' => '4242',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.account.is_premium', true)
            ->assertJsonPath('data.account.is_premium_active', true)
            ->assertJsonPath('data.subscription.plan_name', 'Premium Account')
            ->assertJsonPath('data.subscription.subscription_status', 'active')
            ->assertJsonPath('data.subscription.billing_interval', 'monthly')
            ->assertJsonPath('data.actions.can_upgrade', false)
            ->assertJsonPath('data.actions.can_view_invoices', true);
    }
}
