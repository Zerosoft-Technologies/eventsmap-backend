<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_all_invoices(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        Invoice::query()->create($this->invoicePayload($customer->id, 'INV-A-001'));
        Invoice::query()->create($this->invoicePayload($otherCustomer->id, 'INV-B-002'));

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/admin/invoices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.invoices');
    }

    public function test_regular_admin_cannot_access_invoices(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/invoices')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_super_admin_can_view_invoice_stats_and_detail(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $customer = User::factory()->create();
        $invoice = Invoice::query()->create($this->invoicePayload($customer->id));

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/admin/invoices/stats')
            ->assertOk()
            ->assertJsonPath('data.total_invoices', 1)
            ->assertJsonPath('data.paid_invoices', 1);

        $this->getJson('/api/admin/invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.user.email', $customer->email);
    }

    public function test_invoice_list_search_filters_by_invoice_number(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $customer = User::factory()->create();

        Invoice::query()->create($this->invoicePayload($customer->id, 'INV-MATCH-001'));
        Invoice::query()->create($this->invoicePayload($customer->id, 'INV-OTHER-002'));

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/admin/invoices?search=INV-MATCH')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.invoices.0.invoice_number', 'INV-MATCH-001');
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(int $userId, string $number = 'INV-TEST-000099'): array
    {
        return [
            'user_id' => $userId,
            'invoice_number' => $number,
            'order_id' => 'ord_'.$number,
            'amount' => 10000,
            'tax_amount' => 2100,
            'total_amount' => 12100,
            'currency' => 'eur',
            'payment_status' => Invoice::PAYMENT_PAID,
            'status' => Invoice::STATUS_PAID,
            'billing_name' => 'Test User',
            'billing_email' => 'billing@example.com',
            'product_description' => 'Premium — Monthly plan',
            'plan_interval' => 'monthly',
            'quantity' => 1,
            'payment_method' => 'Stripe',
            'paid_at' => now(),
        ];
    }
}
