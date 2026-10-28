<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Stapelliste {{ $aktion->name }}</title>
<style>
    @font-face { font-family: 'Barlow'; font-weight: 700; src: url('barlow-latin-700-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 400; src: url('hanken-grotesk-latin-400-normal.woff2'); }
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 600; src: url('hanken-grotesk-latin-600-normal.woff2'); }
    @page { size: A4; margin: 14mm 14mm 16mm; }
    body { font-family: 'Hanken Grotesk', Arial, sans-serif; font-size: 9.5pt; color: #2B3C69; }
    h1 { font-family: 'Barlow', Arial, sans-serif; font-size: 18pt; margin: 0 0 1mm; }
    .sub { color: #4a5a85; margin-bottom: 5mm; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    th { white-space: nowrap; text-align: left; font-weight: 600; border-bottom: 1.2pt solid #2B3C69; padding: 1.5mm 1.5mm; font-size: 8.5pt; }
    td { padding: 2mm 1.5mm; border-bottom: 0.4pt solid #EEC6D8; vertical-align: top; }
    tr { break-inside: avoid; }
    .no { font-family: 'Barlow', Arial, sans-serif; font-size: 16pt; font-weight: 700; white-space: nowrap; }
    .muted { color: #4a5a85; font-size: 8.5pt; }
    .box { display: inline-block; width: 4mm; height: 4mm; border: 0.8pt solid #2B3C69; border-radius: 0.6mm; }
    .tag { display: inline-block; background: #f8e8ef; border-radius: 0.8mm; padding: 0.3mm 1.2mm; font-size: 8pt; font-weight: 600; }
    .books { margin: 0 0 5mm; border: 0.8pt solid #2B3C69; border-radius: 1mm; padding: 2.5mm 3mm; }
    .books h2 { font-family: 'Barlow', Arial, sans-serif; font-size: 11pt; margin: 0 0 1.5mm; }
    .books span { display: inline-block; margin-right: 6mm; font-size: 10.5pt; }
    .books b { font-family: 'Barlow', Arial, sans-serif; font-size: 13pt; }
    .legend { color: #4a5a85; font-size: 8.5pt; margin-bottom: 3mm; }
    .copies { font-size: 10.5pt; }
    .copies b { font-weight: 600; }
    td.check { text-align: center; }
    tr.invoice td { background: #fff4c2; }
    .invoice-tag { display: inline-block; background: #2B3C69; color: #fff; border-radius: 0.8mm; padding: 0.5mm 1.5mm; font-size: 8.5pt; font-weight: 700; letter-spacing: 0.02em; }
    .query-tag { display: inline-block; border: 0.8pt solid #a8324a; color: #a8324a; border-radius: 0.8mm; padding: 0.2mm 1.2mm; font-size: 8pt; font-weight: 700; }
</style>
</head>
<body>
    <h1>Stapelliste · {{ $aktion->name }}</h1>
    <div class="sub">Widmungstag {{ $aktion->signing_date->format('d.m.Y') }} · {{ $orders->count() }} Bestellungen · {{ $books->sum() }} Bücher</div>

    <div class="books">
        <h2>Bücher bereitlegen</h2>
        @foreach ($books as $title => $count)
            <span><b>{{ $count }}×</b> {{ $title }}</span>
        @endforeach
    </div>

    <div class="legend">
        «Vorbereitet» abhaken, wenn jedes Buch der Bestellung seinen Post-it hat. «Verpackt» abhaken, wenn die Sendung zu ist.
        Gelbe Zeilen: Rechnung beilegen.
    </div>

    <table>
        <thead>
            <tr>
                <th>Post-it</th><th>Bestellung</th><th>Bücher</th><th>Versand</th><th>Vorbereitet</th><th>Verpackt</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($orders as $order)
            @php($invoice = $needsInvoice($order))
            <tr @class(['invoice' => $invoice])>
                <td class="no">#{{ $order->stack_number }}</td>
                <td style="white-space: nowrap">
                    {{ $order->number }}<br>
                    <span class="muted">{{ $order->billingName() }}</span>
                </td>
                <td class="copies">
                    @foreach ($order->items->sortBy(fn ($item) => [$item->book->sort, $item->book->title])->countBy(fn ($item) => $item->book->title) as $title => $count)
                        <div><b>{{ $count }}×</b> {{ $title }}</div>
                    @endforeach
                    @if ($order->items->contains(fn ($item) => $item->moderation_status !== \App\Enums\ModerationStatus::Approved))
                        <span class="query-tag">Rückfrage offen</span>
                    @endif
                </td>
                <td>
                    <span class="tag">{{ $shipping->methodFor($order) === 'letter' ? 'Brief' : 'Paket' }}</span>
                    @if ($order->is_gift) <span class="tag">Geschenk</span> @endif
                    @if ($invoice) <br><span class="invoice-tag">RECHNUNG BEILEGEN</span> @endif
                </td>
                <td class="check"><span class="box"></span></td>
                <td class="check"><span class="box"></span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
