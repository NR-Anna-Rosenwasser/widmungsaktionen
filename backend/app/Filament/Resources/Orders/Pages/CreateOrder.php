<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotAllowed;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\AdminOrders;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Enter an order by hand, optionally based on an existing one (`?from=<id>`),
 * e.g. to replace a cancelled order that wasn't refunded.
 */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Neue Bestellung';

    protected static bool $canCreateAnother = false;

    #[Locked]
    public ?int $sourceOrderId = null;

    public function mount(): void
    {
        $this->sourceOrderId = $this->source()?->id;

        parent::mount();
    }

    protected function source(): ?Order
    {
        $id = $this->sourceOrderId ?? request()->integer('from');

        return $id ? filament()->getTenant()->orders()->with('items')->find($id) : null;
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        // fill() without arguments applies the field defaults (language, country, Aktion …);
        // fill([...]) would not. The source order's values go on top.
        $this->form->fill();

        if ($prefill = $this->prefill()) {
            $this->form->fill([...$this->form->getRawState(), ...$prefill]);
        }

        $this->callHook('afterFill');
    }

    /**
     * @return array<string, mixed>
     */
    protected function prefill(): array
    {
        $source = $this->source();
        if (! $source) {
            return [];
        }

        // The money of a cancelled, unrefunded order pays for its replacements.
        $paidElsewhere = $source->status === OrderStatus::Cancelled && $source->payment_status === PaymentStatus::Paid;

        return [
            ...$source->only(AdminOrders::FIELDS),
            'aktion_id' => $source->aktion_id,
            'payment_method' => $source->payment_method?->value,
            'items' => $source->items->map(fn ($item) => $item->only(['book_id', 'signature_only', 'recipient_name', 'wish']))->all(),
            'dedications_checked' => true,
            'mark_paid' => $paidElsewhere,
            'paid_at' => $source->payments()->where('status', 'paid')->latest('paid_at')->value('paid_at') ?? now(),
            'paid_note' => $paidElsewhere ? "Bezahlt mit Bestellung {$source->number}" : null,
            'add_to_stack' => true,
            // The customer knows about the fix already; usually you tell them personally.
            'notify' => false,
        ];
    }

    public function getSubheading(): ?string
    {
        $source = $this->source();

        return $source ? "Auf Basis von {$source->number}. Passe Bücher, Widmungen und Adresse für diese Bestellung an." : null;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AdminOrders::class)->create(filament()->getTenant(), [...$data, 'source_order_id' => $this->sourceOrderId], auth()->user());
        } catch (OrderNotAllowed $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Bestellung erfasst';
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
