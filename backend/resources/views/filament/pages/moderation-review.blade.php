<x-filament-panels::page>
@include('filament.pages.partials.focus-styles')
<style>
    .wm-status { display: inline-block; border-radius: 4px; padding: .3rem .8rem; font-weight: 700; margin-bottom: .75rem; background: var(--blush); }
    .wm-status.is-approved { background: #d9efe1; }
    .wm-status.is-flagged { background: var(--danger); color: #fff; }
    .wm-edit { display: grid; gap: 1rem; text-align: left; }
    .wm-edit label { display: grid; gap: .35rem; }
    .wm-edit input, .wm-edit textarea { border: 2px solid var(--navy-soft); border-radius: 4px; padding: .7rem .9rem; font-size: 1.25rem; color: var(--navy); background: #fff; }
    .wm-error { color: var(--danger); font-weight: 600; }
</style>

<div
    class="wm"
    x-data="{
        busy: false,
        flagOpen: false,
        async run(method) {
            if (this.busy) return
            this.busy = true
            try { await $wire.call(method) } finally { this.busy = false; this.flagOpen = false }
        },
        key(e) {
            if (e.target.closest('input, textarea, select') || $wire.editing) return
            if (e.key === 'Enter') { e.preventDefault(); this.run('approve') }
            else if (e.key === 'a' || e.key === 'A') { e.preventDefault(); this.run('startEditing') }
            else if (e.key === 'f' || e.key === 'F') { e.preventDefault(); this.flagOpen = true; $nextTick(() => $refs.flagNote?.focus()) }
            else if (e.key === 'ArrowLeft') { this.run('previous') }
            else if (e.key === 'ArrowRight') { this.run('next') }
        },
    }"
    x-on:keydown.window="key($event)"
>
<div class="wm-inner">
    @php($total = count($ids))

    @if ($total === 0)
        <div class="wm-card wm-center">
            <p class="wm-h">Nichts zu prüfen 🎉</p>
            <p class="wm-p">Alle Widmungswünsche sind freigegeben oder haben eine Rückfrage.</p>
            <div class="wm-actions">
                <a class="wm-btn" href="{{ \App\Filament\Pages\Moderation::getUrl() }}">Zurück zur Liste</a>
            </div>
        </div>
    @else
        <div class="wm-bar">
            <div class="wm-progress">
                <div class="wm-progress-label">Wunsch {{ min($index + 1, $total) }} von {{ $total }}</div>
                <div class="wm-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $index }}">
                    <div class="wm-progress-fill" style="width: {{ round($index / $total * 100) }}%"></div>
                </div>
            </div>
            <a class="wm-btn" href="{{ \App\Filament\Pages\Moderation::getUrl() }}">Zur Liste</a>
        </div>

        @if ($item = $this->item)
            <div class="wm-card">
                <div class="wm-center">
                    @if ($item->moderation_status !== \App\Enums\ModerationStatus::Pending)
                        <div @class(['wm-status', 'is-approved' => $item->moderation_status === \App\Enums\ModerationStatus::Approved, 'is-flagged' => $item->moderation_status === \App\Enums\ModerationStatus::Flagged])>
                            {{ $item->moderation_status->getLabel() }}@if ($item->moderation_note) · {{ $item->moderation_note }}@endif
                        </div>
                    @endif
                    <div class="wm-meta" style="margin-top: 0">
                        {{ $item->order->number }} · {{ $item->order->billingName() }} · {{ $item->order->aktion->adminLabel() }}
                    </div>
                    <div class="wm-book">{{ $item->book->title }} <span class="wm-meta">· Buch {{ $item->position }} von {{ $this->copiesInOrder($item) }}</span></div>
                </div>

                <div class="wm-dedication">
                    @if ($editing)
                        <form class="wm-edit" wire:submit="saveEdit" x-on:keydown.escape="$wire.set('editing', false)">
                            <label>
                                <span class="wm-label">Für</span>
                                <input type="text" wire:model="recipientName" maxlength="100" x-init="$nextTick(() => $el.focus())">
                            </label>
                            <label>
                                <span class="wm-label">Wunsch (max. {{ $this->maxLength() }} Zeichen)</span>
                                <textarea rows="3" wire:model="wish" maxlength="{{ $this->maxLength() }}" x-on:keydown.meta.enter.prevent="$wire.saveEdit()" x-on:keydown.ctrl.enter.prevent="$wire.saveEdit()"></textarea>
                            </label>
                            @error('wish') <p class="wm-error">{{ $message }}</p> @enderror
                            @error('recipientName') <p class="wm-error">{{ $message }}</p> @enderror
                            <p class="wm-p" style="margin-top: 0">Die Kund*in sieht die angepasste Widmung auf ihrer Bestellseite.</p>
                            <div class="wm-actions" style="margin-top: 0">
                                <button type="submit" class="wm-btn wm-btn-primary">Speichern &amp; freigeben <span class="wm-kbd">⌘ Enter</span></button>
                                <button type="button" class="wm-btn" wire:click="$set('editing', false)">Abbrechen <span class="wm-kbd">Esc</span></button>
                            </div>
                        </form>
                    @else
                        <div class="wm-label">Für</div>
                        <div class="wm-recipient">{{ $item->recipient_name ?? '–' }}</div>
                        @if ($item->wish)
                            <div class="wm-wish">«{{ $item->wish }}»</div>
                        @endif
                    @endif
                </div>

                @unless ($editing)
                    <div class="wm-actions">
                        <button type="button" class="wm-btn wm-btn-primary" x-on:click="run('approve')" :disabled="busy">Freigeben ✓ <span class="wm-kbd">Enter</span></button>
                    </div>
                    <div class="wm-actions" style="margin-top: 1rem">
                        <button type="button" class="wm-btn" x-on:click="run('previous')" @disabled($index === 0)>← Zurück</button>
                        <button type="button" class="wm-btn" x-on:click="run('startEditing')">Anpassen <span class="wm-kbd">A</span></button>
                        <button type="button" class="wm-btn wm-btn-danger" x-on:click="flagOpen = ! flagOpen; $nextTick(() => $refs.flagNote?.focus())">Rückfrage <span class="wm-kbd">F</span></button>
                        <button type="button" class="wm-btn" x-on:click="run('next')">Überspringen →</button>
                    </div>
                    <form class="wm-flag" x-show="flagOpen" x-cloak x-on:submit.prevent="run('flag')">
                        <input x-ref="flagNote" type="text" wire:model="flagNote" placeholder="Notiz (intern), z. B. bei Kund*in nachfragen" x-on:keydown.escape="flagOpen = false">
                        <button type="submit" class="wm-btn wm-btn-danger">Rückfrage markieren</button>
                    </form>
                @endunless
            </div>
        @else
            <div class="wm-card wm-center">
                <p class="wm-h">Durch! 🎉</p>
                @php($summary = $this->summary)
                <p class="wm-p">
                    {{ $summary['approved'] }} freigegeben · {{ $summary['adjusted'] }} angepasst · {{ $summary['flagged'] }} {{ $summary['flagged'] === 1 ? 'Rückfrage' : 'Rückfragen' }}
                </p>
                @if ($summary['open'])
                    <p class="wm-p">{{ $summary['open'] }} übersprungen, noch nicht geprüft.</p>
                @endif
                <div class="wm-actions">
                    <button type="button" class="wm-btn" wire:click="previous">← Zurück</button>
                    <a class="wm-btn wm-btn-primary" href="{{ \App\Filament\Pages\Moderation::getUrl() }}">Zur Liste</a>
                </div>
            </div>
        @endif
    @endif
</div>
</div>
</x-filament-panels::page>
