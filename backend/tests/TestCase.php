<?php

namespace Tests;

use App\Services\Pdf\PdfRenderer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never talk to outside services. PDFs (e.g. invoices attached
        // to mails) come from a stand-in instead of the Gotenberg container.
        $this->app->instance(PdfRenderer::class, new class extends PdfRenderer
        {
            public function render(string $html): string
            {
                return '%PDF-1.4 fake';
            }

            public function merge(array $pdfs): string
            {
                return '%PDF-1.4 fake';
            }
        });

        Http::preventStrayRequests();
    }
}
