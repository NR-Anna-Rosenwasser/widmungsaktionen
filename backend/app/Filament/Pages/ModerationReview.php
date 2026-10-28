<?php

namespace App\Filament\Pages;

use App\Enums\ModerationStatus;
use App\Models\OrderItem;
use App\Services\ModerationService;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

/**
 * Check open dedication wishes one at a time, with keyboard shortcuts like
 * the signing screen. The list is fixed when the page opens, so one can go
 * back and forth without wishes disappearing.
 */
class ModerationReview extends Page
{
    protected static ?string $slug = 'widmungswuensche/pruefen';

    protected static ?string $title = 'Widmungswünsche einzeln prüfen';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.moderation-review';

    /** @var array<int, int> */
    public array $ids = [];

    public int $index = 0;

    public bool $editing = false;

    public ?string $recipientName = null;

    public ?string $wish = null;

    public string $flagNote = '';

    public static function canAccess(): bool
    {
        return Moderation::canAccess();
    }

    public function mount(): void
    {
        $this->ids = OrderItem::query()
            ->activeForTenant(filament()->getTenant())
            ->withDedication()
            ->where('moderation_status', ModerationStatus::Pending)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    #[Computed]
    public function item(): ?OrderItem
    {
        $id = $this->ids[$this->index] ?? null;

        return $id ? OrderItem::query()->with('order.aktion', 'book')->find($id) : null;
    }

    /**
     * What happened to the wishes of this round, for the end screen.
     *
     * @return array{approved: int, adjusted: int, flagged: int, open: int}
     */
    #[Computed]
    public function summary(): array
    {
        $items = OrderItem::query()->whereKey($this->ids)->get(['id', 'moderation_status', 'moderation_note']);
        $approved = $items->where('moderation_status', ModerationStatus::Approved);

        return [
            'approved' => $approved->where('moderation_note', '!=', 'Angepasst')->count(),
            'adjusted' => $approved->where('moderation_note', 'Angepasst')->count(),
            'flagged' => $items->where('moderation_status', ModerationStatus::Flagged)->count(),
            'open' => $items->where('moderation_status', ModerationStatus::Pending)->count(),
        ];
    }

    public function maxLength(): int
    {
        return (int) filament()->getTenant()->setting('dedication_max_length');
    }

    public function copiesInOrder(OrderItem $item): int
    {
        return $item->order->items()->count();
    }

    public function approve(): void
    {
        if (! $this->item) {
            return;
        }

        app(ModerationService::class)->approve($this->item, auth()->user());
        $this->next();
    }

    public function startEditing(): void
    {
        if (! $this->item) {
            return;
        }

        $this->recipientName = $this->item->recipient_name;
        $this->wish = $this->item->wish;
        $this->editing = true;
    }

    public function saveEdit(): void
    {
        if (! $this->item) {
            return;
        }

        $this->validate([
            'recipientName' => ['nullable', 'string', 'max:100'],
            'wish' => ['nullable', 'string', 'max:'.$this->maxLength()],
        ]);

        app(ModerationService::class)->adjust($this->item, ['recipient_name' => $this->recipientName, 'wish' => $this->wish], auth()->user());
        $this->next();
    }

    public function flag(): void
    {
        if (! $this->item) {
            return;
        }

        app(ModerationService::class)->flag($this->item, auth()->user(), trim($this->flagNote) ?: null);
        $this->next();
    }

    public function next(): void
    {
        $this->index = min($this->index + 1, count($this->ids));
        $this->resetItem();
    }

    public function previous(): void
    {
        $this->index = max($this->index - 1, 0);
        $this->resetItem();
    }

    protected function resetItem(): void
    {
        $this->reset('editing', 'recipientName', 'wish', 'flagNote');
        $this->resetValidation();
        unset($this->item);
    }
}
