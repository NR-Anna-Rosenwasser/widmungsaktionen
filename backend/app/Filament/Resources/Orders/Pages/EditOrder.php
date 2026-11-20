<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Exceptions\OrderNotAllowed;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\AdminOrders;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Correct contact, addresses and dedications of a submitted order.
 *
 * @property Order $record
 */
class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(AdminOrders::canEdit($this->record), 404);
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->record->number} bearbeiten";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'items' => $this->record->items()->with('book')->get()->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->book->title,
                'signed' => $item->signed_at !== null,
                ...$item->only(['signature_only', 'recipient_name', 'wish']),
            ])->all(),
            'dedications_checked' => true,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(AdminOrders::class)->update($record, $data, auth()->user());
        } catch (OrderNotAllowed $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->halt();
        }

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
