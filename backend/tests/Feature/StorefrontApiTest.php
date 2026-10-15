<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->book = $this->tenant->books()->firstWhere('title', 'Herz');
    }

    protected function base(): string
    {
        return '/api/tenants/'.$this->tenant->slug;
    }

    protected function startOrder(int $copies = 1): Order
    {
        $token = $this->postJson($this->base().'/orders', [
            'email' => 'leserin@example.com',
            'copies' => array_fill(0, $copies, ['book_id' => $this->book->id]),
        ])->assertCreated()->json('token');

        return Order::firstWhere('token', $token);
    }

    protected function billing(): array
    {
        return [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'street' => 'Langstrasse 1',
            'postal_code' => '8004',
            'town' => 'Zürich',
        ];
    }

    public function test_resolve_redirects_to_the_only_tenant_and_maps_custom_domains(): void
    {
        $this->getJson('/api/resolve?host=widmungen.ddev.site')
            ->assertJson(['mode' => 'redirect', 'tenant' => 'anna-rosenwasser']);

        $this->getJson('/api/resolve?host=anna-test.ddev.site')
            ->assertJson(['mode' => 'custom_domain', 'tenant' => 'anna-rosenwasser']);

        Tenant::create(['name' => 'Zweiter Shop', 'slug' => 'zweiter-shop', 'order_prefix' => 'ZS', 'settings' => []]);

        $this->getJson('/api/resolve?host=widmungen.ddev.site')->assertJson(['mode' => 'picker']);
    }

    public function test_tenant_shows_the_open_aktion_and_its_books(): void
    {
        $this->getJson($this->base())
            ->assertOk()
            ->assertJsonPath('tenant.max_copies_per_order', 4)
            ->assertJsonPath('aktion.accepting_orders', true)
            ->assertJsonCount(2, 'aktion.books')
            ->assertJsonPath('next_aktion.name', 'Winter-Widmungsaktion 2027');
    }

    public function test_full_order_flow(): void
    {
        $order = $this->startOrder(2);

        $this->assertSame('draft', $order->status->value);
        $this->assertSame(6000 + 800, $order->total);

        $this->patchJson($this->base()."/orders/{$order->token}", [
            'copies' => [
                ['book_id' => $this->book->id, 'recipient_name' => 'Mia', 'wish' => 'Für Mia, die Mutigste.'],
                ['book_id' => $this->book->id, 'signature_only' => true],
            ],
            'billing' => $this->billing(),
            'payment_method' => 'invoice_advance',
            'current_step' => 'payment',
        ])->assertOk()
            ->assertJsonPath('copies.0.wish', 'Für Mia, die Mutigste.')
            ->assertJsonPath('copies.1.recipient_name', null);

        $this->postJson($this->base()."/orders/{$order->token}/submit")
            ->assertOk()
            ->assertJsonPath('order.status', 'pending_payment')
            ->assertJsonPath('order.number', 'AR-'.now()->year.'-0001');

        // Submitted orders are read-only.
        $this->patchJson($this->base()."/orders/{$order->token}", ['email' => 'x@example.com'])->assertStatus(409);

        $this->postJson($this->base().'/orders/lookup', ['number' => 'ar-'.now()->year.'-0001', 'email' => 'LESERIN@example.com'])
            ->assertOk()
            ->assertJsonPath('token', $order->token);
    }

    public function test_single_copy_ships_as_letter(): void
    {
        $this->assertSame(3000 + 300, $this->startOrder(1)->total);
    }

    public function test_max_four_copies(): void
    {
        $this->postJson($this->base().'/orders', [
            'email' => 'leserin@example.com',
            'copies' => array_fill(0, 5, ['book_id' => $this->book->id]),
        ])->assertUnprocessable()->assertJsonValidationErrors('copies');
    }

    public function test_gift_requires_address_and_message_and_no_invoice_in_parcel(): void
    {
        $order = $this->startOrder();

        $this->patchJson($this->base()."/orders/{$order->token}", [
            'billing' => $this->billing(),
            'is_gift' => true,
            'payment_method' => 'invoice_in_parcel',
        ])->assertOk();

        $this->postJson($this->base()."/orders/{$order->token}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping.first_name', 'shipping.street', 'gift_message', 'payment_method']);
    }

    public function test_invoice_in_parcel_confirms_immediately(): void
    {
        $order = $this->startOrder();

        $this->patchJson($this->base()."/orders/{$order->token}", [
            'billing' => $this->billing(),
            'payment_method' => 'invoice_in_parcel',
        ]);

        $this->postJson($this->base()."/orders/{$order->token}/submit")
            ->assertOk()
            ->assertJsonPath('order.status', 'confirmed');
    }

    public function test_no_orders_without_open_aktion(): void
    {
        $this->tenant->aktionen()->update(['status' => AktionStatus::Closed]);

        $this->postJson($this->base().'/orders', [
            'email' => 'leserin@example.com',
            'copies' => [['book_id' => $this->book->id]],
        ])->assertStatus(409);
    }

    public function test_capacity_is_enforced_on_submit(): void
    {
        $this->tenant->aktionen()->where('status', AktionStatus::Open)->update(['capacity' => 1]);
        $order = $this->startOrder(2);

        $this->patchJson($this->base()."/orders/{$order->token}", [
            'billing' => $this->billing(),
            'payment_method' => 'invoice_advance',
        ]);

        $this->postJson($this->base()."/orders/{$order->token}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('copies');
    }
}
