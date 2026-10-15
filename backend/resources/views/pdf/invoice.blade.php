<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<title>{{ __('invoice.title', ['number' => $order->number], $locale) }}</title>
<style>
@include('pdf.partials.styles')
</style>
</head>
<body>
@include('pdf.partials.invoice-page')
</body>
</html>
