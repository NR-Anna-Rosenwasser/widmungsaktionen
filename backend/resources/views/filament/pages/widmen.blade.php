<x-filament-panels::page>
@include('filament.pages.partials.focus-styles')

<div
    class="wm"
    x-data="{
        focus: false,
        busy: false,
        flagOpen: false,
        toggleFocus() {
            this.focus = ! this.focus
            if (this.focus) { document.documentElement.requestFullscreen?.().catch(() => {}) }
            else if (document.fullscreenElement) { document.exitFullscreen?.() }
        },
        async run(method) {
            if (this.busy) return
            this.busy = true
            try { await $wire.call(method) } finally { this.busy = false }
        },
        key(e) {
            if (e.target.closest('input, textarea, select')) return
            const mode = $wire.mode
            if (mode === 'sign') {
                if (e.code === 'Space' || e.key === 'Enter') { e.preventDefault(); this.run('sign') }
                else if (e.key === 'u' || e.key === 'U') { this.run('undo') }
                else if (e.key === 'f' || e.key === 'F') { e.preventDefault(); this.flagOpen = true; $nextTick(() => $refs.flagNote?.focus()) }
                else if (e.key === 'p' || e.key === 'P') { this.run('pause') }
            } else if (mode === 'paused' && (e.code === 'Space' || e.key === 'Enter')) {
                e.preventDefault(); this.run('resume')
            } else if (mode === 'mismatch' && e.key === 'Enter') {
                this.run('retryCheckpoint')
            }
        },
    }"
    x-on:keydown.window="key($event)"
    x-on:fullscreenchange.document="if (! document.fullscreenElement) focus = false"
    :class="focus && 'wm-focus'"
>
<div class="wm-inner">

    @if (! $this->selectedAktion)
        {{-- Choose an Aktion --}}
        <div class="wm-card">
            <p class="wm-h">Welche Aktion möchtest du widmen?</p>
            @if ($this->aktionen->isEmpty())
                <p class="wm-p">Noch keine Aktion ist bereit. Bereite sie zuerst unter «Vorbereiten & Versand» vor.</p>
            @else
                <div class="wm-choice">
                    @foreach ($this->aktionen as $a)
                        <button type="button" class="wm-btn" wire:click="selectAktion({{ $a->id }})">
                            {{ $a->adminLabel() }} · Widmungstag {{ $a->signing_date->format('d.m.Y') }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- Top bar --}}
        <div class="wm-bar">
            <div class="wm-progress">
                <div class="wm-progress-label">
                    {{ $this->progress['signed'] }} von {{ $this->progress['total'] }} Büchern gewidmet
                </div>
                <div class="wm-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $this->progress['total'] }}" aria-valuenow="{{ $this->progress['signed'] }}">
                    <div class="wm-progress-fill" style="width: {{ $this->progress['total'] ? round($this->progress['signed'] / $this->progress['total'] * 100) : 0 }}%"></div>
                </div>
            </div>
            <button type="button" class="wm-btn" x-on:click="toggleFocus()">
                <span x-text="focus ? 'Fokus beenden' : 'Fokus-Modus'"></span>
            </button>
        </div>

        @switch($mode)
            @case('start')
                <div class="wm-card wm-center">
                    <p class="wm-h">Bereit zum Widmen?</p>
                    <p class="wm-p">Leg den Stapel mit den Post-its bereit. Du siehst immer genau ein Buch. Ab und zu fragen wir nach der Nummer auf dem Post-it vor dir, damit nichts durcheinander gerät.</p>
                    <p class="wm-p">
                        <span class="wm-kbd">Leertaste</span> gewidmet ·
                        <span class="wm-kbd">U</span> rückgängig ·
                        <span class="wm-kbd">F</span> Rückfrage ·
                        <span class="wm-kbd">P</span> Pause
                    </p>
                    <div class="wm-actions">
                        <button type="button" class="wm-btn wm-btn-primary" wire:click="start" x-on:click="if (! focus) toggleFocus()">Los geht’s</button>
                    </div>
                </div>
                @break

            @case('checkpoint')
                <form class="wm-card wm-center" wire:submit="submitCheckpoint">
                    @if ($this->heldBefore->isNotEmpty())
                        <div class="wm-card wm-alert" style="margin-bottom: 1.5rem; padding: 1.25rem">
                            <p class="wm-h" style="font-size: 1.4rem">
                                Rückfrage: Leg {{ $this->heldBefore->count() === 1 ? 'diesen Post-it' : 'diese Post-its' }} mit allen Büchern beiseite
                            </p>
                            <p class="wm-p" style="color: var(--navy)">
                                @foreach ($this->heldBefore as $o)
                                    <strong>#{{ $o->stack_number }}</strong> ({{ $o->items()->count() }} {{ $o->items()->count() === 1 ? 'Buch' : 'Bücher' }})@if (! $loop->last), @endif
                                @endforeach
                            </p>
                            <p class="wm-p">Die Bestellung kommt am Schluss dran, sobald die Rückfrage geklärt ist.</p>
                        </div>
                    @endif
                    @if ($this->currentIsPassed)
                        <div class="wm-passed">Nachgeholt</div>
                        <p class="wm-h">Hol Post-it #{{ $this->current?->order->stack_number }} von den beiseitegelegten Büchern.</p>
                        <p class="wm-p">Die Rückfrage ist geklärt. Tipp zur Kontrolle die Nummer auf dem Post-it ein.</p>
                    @else
                        <p class="wm-h">Welche Nummer steht auf dem Post-it vor dir?</p>
                        <p class="wm-p">Schau auf den Stapel und tipp die Nummer ein.</p>
                    @endif
                    <div style="margin-top: 1.5rem">
                        <input
                            class="wm-input"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            aria-label="Nummer auf dem Post-it"
                            wire:model="checkpointInput"
                            x-init="$nextTick(() => $el.focus())"
                        >
                    </div>
                    <div class="wm-actions">
                        <button type="submit" class="wm-btn wm-btn-primary">Prüfen</button>
                    </div>
                </form>
                @break

            @case('mismatch')
                <div class="wm-card wm-alert wm-center">
                    <p class="wm-h">Stopp, das passt nicht ganz.</p>
                    <p class="wm-p" style="font-size: 1.35rem; color: var(--navy)">
                        Jetzt ist <strong>Post-it #{{ $this->current?->order->stack_number }}</strong> dran.
                        @if ($wrongNumber) Vor dir liegt #{{ $wrongNumber }}. @endif
                    </p>
                    <p class="wm-p">Such im Stapel nach #{{ $this->current?->order->stack_number }}. Diese Bücher liegen in der Nähe:</p>
                    <div class="wm-list">
                        @foreach ($this->neighbours as $n)
                            <div @class(['wm-list-row', 'is-current' => $n->id === $this->current?->id])>
                                <b>#{{ $n->order->stack_number }}</b>
                                <span>
                                    {{ $n->book->title }} · Buch {{ $n->position }} von {{ $this->copiesInOrder($n) }}
                                    @if ($n->signed_at) · <em>schon gewidmet</em>
                                    @elseif ($this->isHeld($n)) · <em>Rückfrage, beiseitelegen</em> @endif
                                    @if ($n->recipient_name) · für {{ $n->recipient_name }} @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="wm-actions">
                        <button type="button" class="wm-btn wm-btn-primary" wire:click="retryCheckpoint">Gefunden, nochmals prüfen</button>
                    </div>
                </div>
                @break

            @case('sign')
                @php($item = $this->current)
                @if ($item)
                    <div class="wm-card">
                        <div class="wm-center">
                            @if ($this->currentIsPassed)
                                <div class="wm-passed">Nachgeholt</div>
                            @endif
                            <div class="wm-stack"><small>Post-it</small>#{{ $item->order->stack_number }}</div>
                            <div class="wm-meta">Buch {{ $item->position }} von {{ $this->copiesInOrder($item) }} dieser Bestellung</div>
                            <div class="wm-book">{{ $item->book->title }}</div>
                        </div>

                        <div class="wm-dedication">
                            @if ($item->signature_only || (! $item->recipient_name && ! $item->wish))
                                <div class="wm-sigonly">Nur Unterschrift</div>
                            @else
                                <div class="wm-label">Für</div>
                                <div class="wm-recipient">{{ $item->recipient_name ?? '–' }}</div>
                                @if ($item->wish)
                                    <div class="wm-wish">«{{ $item->wish }}»</div>
                                @endif
                            @endif
                        </div>

                        <div class="wm-actions">
                            <button type="button" class="wm-btn wm-btn-primary" x-on:click="run('sign')" :disabled="busy">
                                Gewidmet ✓ <span class="wm-kbd">Leertaste</span>
                            </button>
                        </div>
                        <div class="wm-actions" style="margin-top: 1rem">
                            <button type="button" class="wm-btn" x-on:click="run('undo')" @disabled(empty($signedStack))>Rückgängig <span class="wm-kbd">U</span></button>
                            <button type="button" class="wm-btn wm-btn-danger" x-on:click="flagOpen = ! flagOpen; $nextTick(() => $refs.flagNote?.focus())">Rückfrage <span class="wm-kbd">F</span></button>
                            <button type="button" class="wm-btn" x-on:click="run('pause')">Pause <span class="wm-kbd">P</span></button>
                        </div>
                        <form class="wm-flag" x-show="flagOpen" x-cloak wire:submit="flag" x-on:submit="flagOpen = false">
                            <input x-ref="flagNote" type="text" wire:model="flagNote" placeholder="Was ist unklar? (optional)" x-on:keydown.escape="flagOpen = false">
                            <button type="submit" class="wm-btn wm-btn-danger">Beiseitelegen</button>
                        </form>
                    </div>

                    @if ($this->upcoming)
                        <div class="wm-next">
                            @if ($this->upcoming->order_id !== $item->order_id)
                                Danach: <span class="wm-next-new">neuer Post-it #{{ $this->upcoming->order->stack_number }}</span>
                            @else
                                Danach: <strong>Buch {{ $this->upcoming->position }}</strong> mit dem gleichen Post-it
                            @endif
                        </div>
                    @endif
                @endif
                @break

            @case('paused')
                <div class="wm-card wm-center">
                    <p class="wm-h">Pause ☕</p>
                    <p class="wm-p">Nimm dir Zeit. Wenn du weitermachst, prüfen wir kurz den Post-it.</p>
                    <div class="wm-actions">
                        <button type="button" class="wm-btn wm-btn-primary" x-on:click="run('resume')">Weiter <span class="wm-kbd">Leertaste</span></button>
                    </div>
                </div>
                @break

            @case('done')
                <div class="wm-card wm-center">
                    @if ($this->heldOrders->isEmpty())
                        <p class="wm-h">Alles gewidmet! 🎉</p>
                        <p class="wm-p">{{ $this->progress['signed'] }} von {{ $this->progress['total'] }} Büchern sind gewidmet.</p>
                    @else
                        <p class="wm-h">Fertig bis auf {{ $this->heldOrders->count() }} {{ $this->heldOrders->count() === 1 ? 'Bestellung' : 'Bestellungen' }} mit Rückfrage</p>
                        <p class="wm-p">
                            {{ $this->progress['signed'] }} von {{ $this->progress['total'] }} Büchern sind gewidmet.
                            Sobald die Rückfrage unter «Widmungswünsche» geklärt ist, kannst du die ganze Bestellung hier widmen.
                        </p>
                        <div class="wm-list">
                            @foreach ($this->heldOrders as $o)
                                <div class="wm-list-row">
                                    <b>#{{ $o->stack_number }}</b>
                                    <span>
                                        {{ $o->items->count() }} {{ $o->items->count() === 1 ? 'Buch' : 'Bücher' }} · <em>{{ $this->isPutAside($o) ? 'beiseitegelegt' : 'am Ende des Stapels' }}</em>
                                        @foreach ($o->items->where('moderation_status', '!==', \App\Enums\ModerationStatus::Approved) as $flagged)
                                            <br>Rückfrage zu {{ $flagged->book->title }} (Buch {{ $flagged->position }})@if ($flagged->moderation_note): {{ $flagged->moderation_note }}@endif
                                        @endforeach
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="wm-actions">
                            <button type="button" class="wm-btn wm-btn-primary" wire:click="continueSigning">Rückfragen geklärt? Weiter widmen</button>
                        </div>
                    @endif
                    @if (! empty($signedStack))
                        <div class="wm-actions">
                            <button type="button" class="wm-btn" wire:click="undo">Letztes rückgängig machen</button>
                        </div>
                    @endif
                </div>
                @break
        @endswitch
    @endif
</div>
</div>
</x-filament-panels::page>
