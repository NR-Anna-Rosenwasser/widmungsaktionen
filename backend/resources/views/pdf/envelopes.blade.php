<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Couverts C5</title>
<style>
    @font-face { font-family: 'Hanken Grotesk'; font-weight: 400; src: url('hanken-grotesk-latin-400-normal.woff2'); }
    @page { size: 229mm 162mm; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'Hanken Grotesk', Arial, sans-serif; color: #2B3C69; }
    .envelope { position: relative; width: 229mm; height: 162mm; overflow: hidden; break-after: page; }
    .envelope:last-child { break-after: auto; }
    .sender { position: absolute; top: 12mm; left: 15mm; font-size: 8pt; line-height: 1.35; white-space: pre-line; color: #4a5a85; }
    .to { position: absolute; top: 80mm; left: 120mm; width: 95mm; font-size: 12pt; line-height: 1.4; }
    .no { position: absolute; bottom: 8mm; left: 15mm; font-size: 7pt; color: #4a5a85; }
</style>
</head>
<body>
@foreach ($orders as $order)
    <div class="envelope">
        <div class="sender">{{ $tenant->letterhead }}</div>
        <div class="to">
            @foreach ($order->shippingAddressLines() as $line)<div>{{ $line }}</div>@endforeach
        </div>
        <div class="no">#{{ $order->stack_number }}</div>
    </div>
@endforeach
</body>
</html>
