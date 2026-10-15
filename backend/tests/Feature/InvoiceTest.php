<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\Pdf\InvoicePdf;
use App\Services\Pdf\PdfRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
    }

    protected function order(string $method): Order
    {
        $base = "/api/tenants/{$this->tenant->slug}/orders";
        $token = $this->postJson($base, [
            'email' => 'leserin@example.com',
            'copies' => [['book_id' => $this->tenant->books()->first()->id, 'recipient_name' => 'Mia']],
        ])->json('token');
        $this->patchJson("$base/$token", [
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Müller', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => $method,
        ]);
        $this->postJson("$base/$token/submit")->assertOk();

        return Order::firstWhere('token', $token);
    }

    public function test_invoice_contains_reference_amount_and_qr_code(): void
    {
        $order = $this->order('invoice_advance');

        $html = app(InvoicePdf::class)->html($order);

        $this->assertStringContainsString('Rechnung '.$order->number, $html);
        $this->assertStringContainsString('Widmung für Mia', $html);
        $this->assertStringContainsString('33.00', $html);
        $this->assertStringContainsString('RF', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64', $html); // QR code
        $this->assertStringContainsString($order->aktion->order_deadline->locale('de_CH')->isoFormat('LL'), $html);
    }

    public function test_parcel_invoices_are_due_after_shipping(): void
    {
        $order = $this->order('invoice_in_parcel');
        $due = $order->aktion->estimated_shipping_date->copy()->addDays(10)->locale('de_CH')->isoFormat('LL');

        $this->assertStringContainsString($due, app(InvoicePdf::class)->html($order));
    }

    public function test_customer_can_download_invoice_pdf(): void
    {
        $order = $this->order('invoice_advance');
        $renderer = Mockery::mock(PdfRenderer::class);
        $renderer->shouldReceive('render')->once()->andReturn('%PDF-fake');
        $this->app->instance(PdfRenderer::class, $renderer);

        $this->get("/api/tenants/{$this->tenant->slug}/orders/{$order->token}/invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-fake', false);
    }

    public function test_no_invoice_for_online_orders(): void
    {
        $order = $this->order('invoice_advance');
        $order->update(['payment_method' => 'online']);

        $this->get("/api/tenants/{$this->tenant->slug}/orders/{$order->token}/invoice")->assertNotFound();
    }
}
