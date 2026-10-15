@php
    $perSheet = (int) ($sheet['cols'] * $sheet['rows']);
    // Empty slots first, so partly used sheets can be reused.
    $slots = array_merge(array_fill(0, $skip, null), $orders->all());
    $sheets = array_chunk($slots, max(1, $perSheet));
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Etiketten</title>
<style>
@include('pdf.partials.styles')
    .label { position: absolute; box-sizing: border-box; padding: 4mm 5mm; overflow: hidden; display: flex; flex-direction: column; justify-content: center; }
    .label .from { font-size: 6.5pt; color: #4a5a85; border-bottom: 0.4pt solid #4a5a85; padding-bottom: 0.8mm; margin-bottom: 1.6mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .to { font-size: 10pt; line-height: 1.3; }
    .label .no { position: absolute; right: 3mm; top: 2mm; font-size: 7pt; color: #4a5a85; }
</style>
</head>
<body>
@foreach ($sheets as $slotsOnSheet)
    <div class="page">
        @foreach ($slotsOnSheet as $i => $order)
            @php
                $col = $i % (int) $sheet['cols'];
                $row = intdiv($i, (int) $sheet['cols']);
                $left = $sheet['left'] + $col * ($sheet['width'] + $sheet['gap_x']);
                $top = $sheet['top'] + $row * ($sheet['height'] + $sheet['gap_y']);
            @endphp
            @if ($order)
                <div class="label" style="left: {{ $left }}mm; top: {{ $top }}mm; width: {{ $sheet['width'] }}mm; height: {{ $sheet['height'] }}mm;">
                    <div class="no">#{{ $order->stack_number }}</div>
                    <div class="from">{{ $sender }}</div>
                    <div class="to">
                        @foreach ($order->shippingAddressLines() as $line)<div>{{ $line }}</div>@endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
