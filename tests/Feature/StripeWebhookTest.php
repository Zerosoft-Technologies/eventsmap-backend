<?php

namespace Tests\Feature;

use App\Models\SubscriptionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
    }

    private function signPayload(string $payload): string
    {
        $t = time();
        $signed = $t.'.'.$payload;
        $sig = hash_hmac('sha256', $signed, 'whsec_test_secret');

        return "t={$t},v1={$sig}";
    }

    private function customerUpdatedPayload(string $eventId = 'evt_test_1'): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2022-11-15',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                    'object' => 'customer',
                    'email' => 'a@b.com',
                    'name' => 'A',
                ],
            ],
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => 'customer.updated',
        ], JSON_UNESCAPED_SLASHES);
    }

    public function test_rejects_invalid_signature(): void
    {
        $payload = $this->customerUpdatedPayload();

        $this->call(
            'POST',
            '/api/webhook/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
            ],
            $payload
        )->assertStatus(400);
    }

    public function test_accepts_valid_customer_updated_webhook(): void
    {
        $payload = $this->customerUpdatedPayload();

        $this->call(
            'POST',
            '/api/webhook/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
            ],
            $payload
        )->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('subscription_events', ['stripe_event_id' => 'evt_test_1']);
    }

    public function test_duplicate_event_delivery_is_idempotent(): void
    {
        $payload = $this->customerUpdatedPayload('evt_dup_1');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
        ];

        $this->call('POST', '/api/webhook/stripe', [], [], [], $headers, $payload)->assertOk();

        $payload2 = $this->customerUpdatedPayload('evt_dup_1');
        $headers2 = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload2),
        ];

        $this->call('POST', '/api/webhook/stripe', [], [], [], $headers2, $payload2)->assertOk();

        $this->assertSame(1, SubscriptionEvent::query()->where('stripe_event_id', 'evt_dup_1')->count());
    }
}
