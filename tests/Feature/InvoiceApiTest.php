<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_list_own_invoices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Invoice::query()->create($this->invoicePayload($user->id));
        Invoice::query()->create($this->invoicePayload($other->id, 'INV-OTHER-001'));

        Sanctum::actingAs($user);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_view_another_users_invoice(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $invoice = Invoice::query()->create($this->invoicePayload($other->id));

        Sanctum::actingAs($user);

        $this->getJson('/api/invoices/'.$invoice->id)
            ->assertNotFound();
    }

    public function test_invoice_number_generation_is_sequential(): void
    {
        config(['invoice.number_prefix' => 'INV']);
        $user = User::factory()->create();

        $service = app(\App\Services\InvoiceService::class);
        $first = $service->generateInvoiceNumber();
        Invoice::query()->create(array_merge($this->invoicePayload($user->id), ['invoice_number' => $first]));
        $second = $service->generateInvoiceNumber();

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('INV-', $second);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(int $userId, string $number = 'INV-TEST-000001'): array
    {
        return [
            'user_id' => $userId,
            'invoice_number' => $number,
            'order_id' => 'ord_test_1',
            'amount' => 10000,
            'tax_amount' => 2100,
            'total_amount' => 12100,
            'currency' => 'eur',
            'payment_status' => Invoice::PAYMENT_PAID,
            'status' => Invoice::STATUS_PAID,
            'billing_name' => 'Test User',
            'billing_email' => 'test@example.com',
            'product_description' => 'Premium — Monthly plan',
            'plan_interval' => 'monthly',
            'quantity' => 1,
            'payment_method' => 'Stripe',
            'paid_at' => now(),
        ];
    }
}
