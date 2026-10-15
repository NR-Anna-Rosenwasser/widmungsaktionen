<?php

namespace App\Filament\Pages;

use App\Enums\AktionStatus;
use App\Enums\TenantRole;
use App\Models\Aktion;
use App\Models\OrderItem;
use App\Models\SigningSession;
use App\Services\SigningService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Focused signing workflow: one copy at a time, with regular checks that the
 * book in front of Anna is the one on screen (post-it number).
 */
class Widmen extends Page
{
    protected static ?string $slug = 'widmen';

    protected static ?string $title = 'Widmen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;

    protected static string|UnitEnum|null $navigationGroup = 'Widmen';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.widmen';

    #[Url]
    public ?int $aktion = null;

    /** start | checkpoint | mismatch | sign | paused | done */
    public string $mode = 'start';

    public ?int $sessionId = null;

    public int $sinceCheckpoint = 0;

    /** Item ids signed in this sitting, for undo. */
    public array $signedStack = [];

    public string $checkpointInput = '';

    public ?int $wrongNumber = null;

    public string $flagNote = '';

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, [TenantRole::Admin, TenantRole::Widmen]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->selectedAktion ? 'Widmen: '.$this->selectedAktion->name : 'Widmen';
    }

    public function mount(): void
    {
        // Preselect the only Aktion that is being signed.
        if (! $this->aktion) {
            $candidates = $this->aktionen;
            if ($candidates->count() === 1) {
                $this->aktion = $candidates->first()->id;
            }
        }

        if ($this->selectedAktion && ! $this->current) {
            $this->mode = 'done';
        }
    }

    protected function signing(): SigningService
    {
        return app(SigningService::class);
    }

    /**
     * Aktionen with a prepared stack.
     *
     * @return Collection<int, Aktion>
     */
    #[Computed]
    public function aktionen(): Collection
    {
        return Aktion::query()
            ->whereBelongsTo(filament()->getTenant())
            ->whereIn('status', [AktionStatus::Signing, AktionStatus::Closed, AktionStatus::Open, AktionStatus::Shipping])
            ->whereHas('orders', fn ($q) => $q->whereNotNull('stack_number'))
            ->orderByDesc('signing_date')
            ->get();
    }

    #[Computed]
    public function selectedAktion(): ?Aktion
    {
        return $this->aktion
            ? Aktion::query()->whereBelongsTo(filament()->getTenant())->find($this->aktion)
            : null;
    }

    #[Computed]
    public function current(): ?OrderItem
    {
        return $this->selectedAktion ? $this->signing()->current($this->selectedAktion) : null;
    }

    /**
     * The copy after the current one, to announce a new post-it.
     */
    #[Computed]
    public function upcoming(): ?OrderItem
    {
        if (! $this->current) {
            return null;
        }

        return $this->signing()->queue($this->selectedAktion)
            ->whereNull('order_items.signed_at')
            ->where('order_items.moderation_status', 'approved')
            ->where('order_items.id', '!=', $this->current->id)
            ->first();
    }

    #[Computed]
    public function progress(): array
    {
        return $this->selectedAktion ? $this->signing()->progress($this->selectedAktion) : ['signed' => 0, 'total' => 0];
    }

    #[Computed]
    public function neighbours(): Collection
    {
        return $this->current ? $this->signing()->around($this->selectedAktion, $this->current) : collect();
    }

    public function copiesInOrder(OrderItem $item): int
    {
        return $item->order->items()->count();
    }

    public function selectAktion(int $id): void
    {
        $this->aktion = $id;
        $this->reset('mode', 'sessionId', 'sinceCheckpoint', 'signedStack', 'checkpointInput', 'wrongNumber');
        unset($this->selectedAktion, $this->current, $this->upcoming, $this->progress);
        if (! $this->current) {
            $this->mode = 'done';
        }
    }

    public function start(): void
    {
        $session = SigningSession::create([
            'aktion_id' => $this->selectedAktion->id,
            'user_id' => auth()->id(),
        ]);
        $session->record('started');
        $this->sessionId = $session->id;
        $this->askCheckpoint();
    }

    protected function session(): ?SigningSession
    {
        return $this->sessionId ? SigningSession::find($this->sessionId) : null;
    }

    protected function askCheckpoint(): void
    {
        $this->refreshState();
        $this->checkpointInput = '';
        $this->mode = $this->current ? 'checkpoint' : 'done';
    }

    public function submitCheckpoint(): void
    {
        $expected = $this->current?->order->stack_number;
        $given = (int) preg_replace('/\D/', '', $this->checkpointInput);

        if ($expected === null) {
            $this->mode = 'done';

            return;
        }

        if ($given === $expected) {
            $this->session()?->record('checkpoint_ok', ['stack_number' => $given]);
            $this->sinceCheckpoint = 0;
            $this->wrongNumber = null;
            $this->mode = 'sign';

            return;
        }

        $this->session()?->record('checkpoint_mismatch', ['expected' => $expected, 'given' => $given]);
        $this->wrongNumber = $given ?: null;
        $this->mode = 'mismatch';
    }

    public function retryCheckpoint(): void
    {
        $this->askCheckpoint();
    }

    public function sign(): void
    {
        if ($this->mode !== 'sign' || ! $this->current) {
            return;
        }

        $item = $this->current;
        $this->signing()->sign($item, auth()->user());
        $this->signedStack[] = $item->id;
        $this->sinceCheckpoint++;

        $session = $this->session();
        if ($session) {
            $session->increment('signed_count');
            $session->record('signed', ['item' => $item->id, 'stack_number' => $item->order->stack_number]);
        }

        $this->refreshState();

        if (! $this->current) {
            $this->finish();

            return;
        }

        // Check the stack every few books, at the start of a new post-it.
        $interval = (int) filament()->getTenant()->setting('checkpoint_interval');
        if ($this->sinceCheckpoint >= $interval && $this->current->position === 1) {
            $this->askCheckpoint();
        }
    }

    public function undo(): void
    {
        $id = array_pop($this->signedStack);
        if (! $id) {
            return;
        }

        $item = OrderItem::find($id);
        $this->signing()->unsign($item, auth()->user());
        $this->session()?->decrement('signed_count');
        $this->session()?->record('undo', ['item' => $id]);
        $this->askCheckpoint();
    }

    public function flag(): void
    {
        if (! $this->current) {
            return;
        }

        $item = $this->current;
        $this->signing()->flag($item, auth()->user(), trim($this->flagNote) ?: null);
        $this->session()?->record('flagged', ['item' => $item->id]);
        $this->flagNote = '';
        $this->askCheckpoint();
    }

    public function pause(): void
    {
        $this->session()?->record('paused');
        $this->mode = 'paused';
    }

    public function resume(): void
    {
        $this->session()?->record('resumed');
        $this->askCheckpoint();
    }

    protected function finish(): void
    {
        $session = $this->session();
        if ($session && ! $session->ended_at) {
            $session->record('finished');
            $session->update(['ended_at' => now()]);
        }
        $this->mode = 'done';
    }

    protected function refreshState(): void
    {
        unset($this->current, $this->upcoming, $this->progress, $this->neighbours);
    }
}
