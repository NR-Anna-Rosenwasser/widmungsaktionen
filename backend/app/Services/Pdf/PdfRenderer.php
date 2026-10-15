<?php

namespace App\Services\Pdf;

use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use GuzzleHttp\Client;

/**
 * HTML → PDF via Gotenberg (headless Chromium). Brand fonts are sent along
 * as assets so PDFs look like the storefront.
 */
class PdfRenderer
{
    public function render(string $html): string
    {
        $assets = collect(glob(resource_path('fonts/*.woff2')))
            ->map(fn (string $path) => Stream::path($path))
            ->all();

        $request = Gotenberg::chromium(config('widmungen.gotenberg_url'))
            ->pdf()
            ->paperSize('210mm', '297mm')
            ->margins(0, 0, 0, 0)
            ->printBackground()
            ->preferCssPageSize()
            ->assets(...$assets)
            ->html(Stream::string('index.html', $html));

        return (string) Gotenberg::send($request, $this->client())->getBody();
    }

    /**
     * @param  array<string, string>  $pdfs  filename => PDF contents
     */
    public function merge(array $pdfs): string
    {
        $streams = collect($pdfs)
            ->map(fn (string $pdf, string $name) => Stream::string($name, $pdf))
            ->values()
            ->all();

        $request = Gotenberg::pdfEngines(config('widmungen.gotenberg_url'))->merge(...$streams);

        return (string) Gotenberg::send($request, $this->client())->getBody();
    }

    protected function client(): Client
    {
        return new Client(['timeout' => 60]);
    }
}
