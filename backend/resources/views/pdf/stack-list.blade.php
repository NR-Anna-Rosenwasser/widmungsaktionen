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
</style>
</head>
<body>
    <h1>Stapelliste · {{ $aktion->name }}</h1>
    <div class="sub">Widmungstag {{ $aktion->signing_date->format('d.m.Y') }} · {{ $orders->count() }} Bestellungen · {{ $orders->sum(fn ($o) => $o->items->count()) }} Bücher</div>
    <table>
        <thead>
            <tr>
                <th>Post-it</th><th>Bestellung</th><th>Bücher &amp; Widmungen</th><th>Versand an</th><th>Gewidmet</th><th>Verpackt</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($orders as $order)
            <tr>
                <td class="no">#{{ $order->stack_number }}</td>
                <td style="white-space: nowrap">
                    {{ $order->number }}<br>
                    <span class="muted">{{ $order->billingName() }}</span>
                </td>
                <td>
                    @foreach ($order->items as $item)
                        <div>{{ $item->book->title }} <span class="muted">· {{ $item->signature_only || ! $item->recipient_name ? 'nur Unterschrift' : 'für '.$item->recipient_name }}</span></div>
                    @endforeach
                </td>
                <td>
                    {{ implode(', ', $order->shippingAddressLines()) }}<br>
                    <span class="tag">{{ $shipping->methodFor($order) === 'letter' ? 'Brief' : 'Paket' }}</span>
                    @if ($order->is_gift) <span class="tag">Geschenk</span> @endif
                    @if ($needsInvoice($order)) <span class="tag">Rechnung beilegen</span> @endif
                </td>
                <td><span class="box"></span></td>
                <td><span class="box"></span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
