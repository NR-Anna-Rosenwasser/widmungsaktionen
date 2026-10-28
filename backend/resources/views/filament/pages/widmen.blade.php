<x-filament-panels::page>
<style>
    .wm { --navy: #2B3C69; --navy-soft: #4a5a85; --rose: #D989B2; --blush: #EEC6D8; --blush-light: #f8e8ef; --danger: #a8324a;
          color: var(--navy); font-family: inherit; }
    .wm-focus { position: fixed; inset: 0; z-index: 60; background: #fff; overflow-y: auto; padding: 2rem 1.5rem 3rem; }
    .wm-inner { max-width: 54rem; margin: 0 auto; }
    .wm-bar { display: flex; align-items: center; gap: 1rem; justify-content: space-between; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .wm-progress { flex: 1; min-width: 12rem; }
    .wm-progress-track { height: 8px; background: var(--blush-light); border-radius: 999px; overflow: hidden; }
    .wm-progress-fill { height: 100%; background: var(--rose); transition: width .3s ease; }
    .wm-progress-label { font-size: .9rem; font-weight: 600; margin-bottom: .35rem; color: var(--navy-soft); }
    .wm-card { background: var(--blush-light); border-radius: 6px; padding: 2rem; }
    .wm-center { text-align: center; }
    .wm-stack { font-size: clamp(4rem, 12vw, 7.5rem); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
    .wm-stack small { font-size: .3em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--navy-soft); display: block; margin-bottom: .4rem; }
    .wm-meta { font-size: 1.1rem; color: var(--navy-soft); margin-top: .75rem; }
    .wm-book { font-size: 1.5rem; font-weight: 700; margin-top: .25rem; }
    .wm-dedication { background: #fff; border-radius: 6px; padding: 1.75rem 2rem; margin-top: 1.5rem; }
    .wm-label { font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--navy-soft); }
    .wm-recipient { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; line-height: 1.1; margin-top: .25rem; }
    .wm-wish { font-size: clamp(1.35rem, 3vw, 1.85rem); line-height: 1.45; margin-top: 1rem; font-style: italic; }
    .wm-sigonly { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; }
    .wm-next { margin-top: 1.25rem; font-size: 1.05rem; color: var(--navy-soft); text-align: center; }
    .wm-next strong { color: var(--navy); }
    .wm-next-new { display: inline-block; background: var(--blush); color: var(--navy); border-radius: 4px; padding: .35rem .75rem; font-weight: 700; }
    .wm-actions { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; margin-top: 2rem; }
    .wm-btn { display: inline-flex; align-items: center; gap: .6rem; border-radius: 4px; padding: .9rem 1.4rem; font-weight: 700; font-size: 1.05rem; border: 2px solid var(--navy); background: #fff; color: var(--navy); cursor: pointer; }
    .wm-btn:hover { background: var(--blush-light); }
    .wm-btn:disabled { opacity: .45; cursor: not-allowed; }
    .wm-btn-primary { background: var(--navy); color: #fff; font-size: 1.35rem; padding: 1.1rem 2.2rem; }
    .wm-btn-primary:hover { background: var(--navy-soft); }
    .wm-btn-danger { border-color: var(--danger); color: var(--danger); }
    .wm-kbd { font-size: .75rem; font-weight: 700; border: 1.5px solid currentColor; border-radius: 3px; padding: .05rem .35rem; opacity: .75; }
    .wm-input { font-size: 3rem; font-weight: 800; text-align: center; width: 12rem; border: 3px solid var(--navy); border-radius: 6px; padding: .5rem; color: var(--navy); background: #fff; }
    .wm-input:focus { outline: 3px solid var(--rose); outline-offset: 3px; }
    .wm-h { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 800; line-height: 1.2; }
    .wm-p { font-size: 1.15rem; margin-top: .75rem; color: var(--navy-soft); }
    .wm-alert { border: 3px solid var(--danger); background: #fff; }
    .wm-alert .wm-h { color: var(--danger); }
    .wm-list { margin-top: 1.5rem; text-align: left; display: grid; gap: .5rem; }
    .wm-list-row { display: grid; grid-template-columns: 4.5rem 1fr; gap: 1rem; align-items: baseline; background: #fff; border-radius: 4px; padding: .6rem 1rem; }
    .wm-list-row.is-current { outline: 3px solid var(--rose); }
    .wm-list-row b { font-size: 1.4rem; }
    .wm-choice { display: grid; gap: .75rem; margin-top: 1.5rem; }
    .wm-choice button { text-align: left; }
    .wm-flag { margin-top: 1rem; display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
    .wm-flag input { min-width: 18rem; border: 2px solid var(--navy-soft); border-radius: 4px; padding: .6rem .8rem; color: var(--navy); }
    @media (prefers-reduced-motion: reduce) { .wm-progress-fill { transition: none; } }
</style>

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
                <p class="wm-p">Noch keine Aktion ist bereit. Bereite zuerst unter «Aktionen» den Stapel vor («Widmen vorbereiten»).</p>
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
                    <p class="wm-h">Welche Nummer steht auf dem Post-it vor dir?</p>
                    <p class="wm-p">Schau auf den Stapel und tipp die Nummer ein.</p>
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
                                    @if ($n->signed_at) · <em>schon gewidmet</em> @endif
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
                    <p class="wm-h">Alles gewidmet! 🎉</p>
                    <p class="wm-p">
                        {{ $this->progress['signed'] }} von {{ $this->progress['total'] }} Büchern sind gewidmet.
                        @php($flagged = $this->selectedAktion->orders()->whereHas('items', fn ($q) => $q->where('moderation_status', 'flagged'))->count())
                        @if ($flagged)
                            {{ $flagged }} {{ $flagged === 1 ? 'Bestellung hat' : 'Bestellungen haben' }} eine Rückfrage, siehe «Widmungswünsche».
                        @endif
                    </p>
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
