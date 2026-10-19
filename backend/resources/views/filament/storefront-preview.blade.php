@php
    use App\Services\StorefrontAppearance;

    ['primary' => $primary, 'accent' => $accent, 'accent_light' => $light] = $colors;
    $checks = [
        'Schrift auf Akzentfarbe' => StorefrontAppearance::contrast($primary, $accent),
        'Schrift auf heller Akzentfarbe' => StorefrontAppearance::contrast($primary, $light),
        'Schrift auf Weiss' => StorefrontAppearance::contrast($primary, '#FFFFFF'),
        'Weisse Schrift auf Hauptfarbe (Buttons)' => StorefrontAppearance::contrast('#FFFFFF', $primary),
    ];
    // WCAG: 4.5 : 1 for normal text, 3 : 1 for large text (headlines).
    $weak = array_filter($checks, fn ($ratio) => $ratio < 3);
    $tight = array_filter($checks, fn ($ratio) => $ratio >= 3 && $ratio < 4.5);
@endphp

<div style="border: 1px solid rgb(0 0 0 / 0.1); border-radius: 6px; overflow: hidden; font-family: ui-sans-serif, system-ui, sans-serif;">
    <div style="padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; background: #fff; color: {{ $primary }}; font-weight: 700; font-size: 13px;">
        <span>{{ $name ?: 'Shopname' }}</span>
        <span style="font-size: 11px; background: {{ $light }}; padding: 2px 6px; border-radius: 3px;">DE</span>
    </div>

    <div style="background: {{ $accent }}; color: {{ $primary }}; padding: 28px 24px 40px;">
        <div style="font-size: 10px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; opacity: 0.8;">Herbst-Widmungsaktion</div>
        <div style="font-size: 26px; font-weight: 700; line-height: 1.1; margin-top: 6px; max-width: 420px;">{{ $title }}</div>
        <div style="margin-top: 14px; display: inline-block; font-weight: 700; border-bottom: 2px solid currentColor; padding-bottom: 1px; font-size: 14px;">{{ $cta }} →</div>
    </div>

    <div style="background: #fff; padding: 0 24px 20px;">
        <div style="margin-top: -18px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: {{ $light }}; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);">
            @foreach (['Bestellschluss' => '5. November', 'Widmungstag' => '12. November', 'Versand ca.' => '19. November'] as $label => $date)
                <div style="background: #fff; padding: 10px 12px; color: {{ $primary }};">
                    <div style="font-size: 10px; opacity: 0.75;">{{ $label }}</div>
                    <div style="font-weight: 700; font-size: 13px;">{{ $date }}</div>
                </div>
            @endforeach
        </div>

        <div style="display: flex; gap: 12px; align-items: center; margin-top: 18px;">
            <div style="background: {{ $light }}; color: {{ $primary }}; border-radius: 4px; padding: 10px 14px; font-size: 12px; flex: 1;">
                <strong>So funktioniert’s</strong> · Bestellen · Widmen · Ankommen
            </div>
            <div style="background: {{ $primary }}; color: #fff; border-radius: 3px; padding: 8px 14px; font-size: 12px; font-weight: 700; white-space: nowrap;">{{ $cta }} →</div>
        </div>
    </div>

    <div style="background: {{ $primary }}; color: {{ $light }}; padding: 10px 16px; font-size: 11px;">{{ $name ?: 'Shopname' }} · Kontakt · Datenschutz</div>
</div>

<div style="margin-top: 8px; font-size: 12px; line-height: 1.5;">
    @if ($weak)
        <div style="color: #b91c1c;">
            <strong>Schwacher Kontrast</strong>, Texte sind schlecht lesbar:
            @foreach ($weak as $label => $ratio)
                {{ $label }} ({{ number_format($ratio, 1) }} : 1)@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif
    @if ($tight)
        <div style="color: #b45309;">
            <strong>Für grosse Schrift gut, für kleine Schrift knapp</strong>:
            @foreach ($tight as $label => $ratio)
                {{ $label }} ({{ number_format($ratio, 1) }} : 1)@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif
    @if (! $weak && ! $tight)
        <div style="color: #15803d;">Kontrast gut lesbar (alle Kombinationen mindestens 4.5 : 1).</div>
    @else
        <div style="color: #64748b;">Empfohlen (WCAG): mindestens 4.5 : 1 für normale Schrift, 3 : 1 für grosse Schrift.</div>
    @endif
</div>
