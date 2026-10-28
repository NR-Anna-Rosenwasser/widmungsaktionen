<?php

namespace Tests\Concerns;

use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\SigningService;

/**
 * Places orders through the storefront API, like a customer would.
 */
trait CreatesOrders
{
    /**
     * «Widmungsaktion vorbereiten», after letting the order deadline pass.
     */
    protected function prepareStack(Aktion $aktion): int
    {
        $aktion->update(['order_deadline' => now()->subMinute()]);

        return app(SigningService::class)->prepare($aktion);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $copies
     */
    protected function submitOrder(
        Tenant $tenant,
        string $method = 'invoice_advance',
        ?array $copies = null,
        array $overrides = [],
    ): Order {
        $base = "/api/tenants/{$tenant->slug}/orders";
        $copies ??= [['book_id' => $tenant->books()->orderBy('sort')->first()->id, 'recipient_name' => 'Mia', 'wish' => 'Für Mia']];

        $token = $this->postJson($base, [
            'email' => $overrides['email'] ?? 'leserin@example.com',
            'copies' => array_map(fn ($copy) => ['book_id' => $copy['book_id']], $copies),
        ])->assertCreated()->json('token');

        $this->patchJson("$base/$token", array_merge([
            'copies' => $copies,
            'billing' => ['first_name' => 'Mia', 'last_name' => 'Muster', 'street' => 'Langstrasse 1', 'postal_code' => '8004', 'town' => 'Zürich'],
            'payment_method' => $method,
        ], $overrides))->assertOk();

        $this->postJson("$base/$token/submit")->assertOk();

        return Order::firstWhere('token', $token);
    }
}
