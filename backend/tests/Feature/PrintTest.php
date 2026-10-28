<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Enums\ModerationStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\Versand;
use App\Models\Aktion;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\PrintService;
use App\Services\SigningService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class PrintTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected Order $letter;

    protected Order $parcel;

    protected Order $gift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
        $book = $this->tenant->books()->first()->id;

        $this->letter = $this->submitOrder($this->tenant, 'invoice_in_parcel', [['book_id' => $book, 'signature_only' => true]]);
        $this->parcel = $this->submitOrder($this->tenant, 'invoice_in_parcel', [['book_id' => $book, 'signature_only' => true], ['book_id' => $book, 'signature_only' => true]]);
        $this->gift = $this->submitOrder($this->tenant, 'invoice_advance', [['book_id' => $book, 'recipient_name' => 'Kim']], [
            'is_gift' => true,
            'shipping' => ['first_name' => 'Kim', 'last_name' => 'Beispiel', 'street' => 'Bahnhofstrasse 2', 'postal_code' => '3000', 'town' => 'Bern'],
            'gift_message' => 'Alles Liebe!',
        ]);
        $this->gift->update(['status' => OrderStatus::Confirmed, 'payment_status' => 'paid']);
        Order::query()->each(fn ($o) => $o->items()->update(['moderation_status' => ModerationStatus::Approved]));
        app(SigningService::class)->prepare($this->aktion);

        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    protected function setEnvelopeMode(string $mode): void
    {
        $this->tenant->update(['settings' => ['print' => ['envelope_mode' => $mode, 'label_preset' => 'avery_3475']]]);
        $this->aktion->refresh();
    }

    public function test_letters_include_invoice_only_for_unpaid_parcel_invoices_and_hide_prices_for_gifts(): void
    {
        $html = app(PrintService::class)->lettersHtml($this->aktion);

        $this->assertSame(2, substr_count($html, 'Rechnung '.'AR-'), 'one invoice page each for the two in-parcel orders');
        $this->assertStringContainsString('Ein Geschenk für dich', $html);
        $this->assertStringContainsString('Alles Liebe!', $html);
        $this->assertStringContainsString('Mia Muster schenkt dir dieses persönlich gewidmete Buch.', $html);
    }

    public function test_labels_follow_the_envelope_mode(): void
    {
        $print = app(PrintService::class);

        $this->setEnvelopeMode('labels');
        $this->assertCount(3, $print->ordersNeedingLabels($this->aktion));

        // Window envelopes: only parcels need a label.
        $this->setEnvelopeMode('window_right');
        $this->assertSame([$this->parcel->id], $print->ordersNeedingLabels($this->aktion)->pluck('id')->all());

        $this->setEnvelopeMode('direct_print');
        $this->assertCount(2, $print->ordersForEnvelopes($this->aktion));
    }

    public function test_labels_skip_used_slots(): void
    {
        $this->setEnvelopeMode('labels');
        $html = app(PrintService::class)->labelsHtml($this->aktion, 23);

        // 24 per sheet, 23 skipped: one label on the first sheet, two on the second.
        $this->assertSame(2, substr_count($html, 'class="page"'));
        $this->assertSame(3, substr_count($html, 'class="label"'));
    }

    public function test_switching_the_aktion_updates_the_overview(): void
    {
        $other = $this->tenant->aktionen()->where('name', 'Winter-Widmungsaktion 2027')->first();
        $other->update(['status' => AktionStatus::Closed]);

        Livewire::test(Versand::class, ['aktion' => $this->aktion->id])
            ->assertSee('Widmungstag '.$this->aktion->signing_date->format('d.m.Y'))
            ->assertSee('3 auf dem Stapel')
            ->callAction(TestAction::make('chooseAktion'), ['aktion' => $other->id])
            ->assertSee('Widmungstag '.$other->signing_date->format('d.m.Y'))
            ->assertSee('0 auf dem Stapel');
    }

    public function test_versand_page_downloads_and_ships(): void
    {
        $renderer = Mockery::mock(PdfRenderer::class);
        $renderer->shouldReceive('render')->andReturn('%PDF');
        $this->app->instance(PdfRenderer::class, $renderer);

        $this->get('https://'.config('widmungen.admin_domain')."/{$this->tenant->slug}/drucken-versand")->assertOk();

        Livewire::test(Versand::class)
            ->callAction(TestAction::make('stackList')->schemaComponent('prepareActions', schema: 'content'))
            ->assertFileDownloaded('stapelliste-herbst-widmungsaktion-2026.pdf')
            ->callAction(TestAction::make('export')->schemaComponent('shipActions', schema: 'content'))
            ->assertFileDownloaded('bestellungen-herbst-widmungsaktion-2026.csv');

        // Sign everything, then ship all.
        $signing = app(SigningService::class);
        while ($item = $signing->current($this->aktion)) {
            $signing->sign($item, auth()->user());
        }

        Livewire::test(Versand::class)
            ->callAction(TestAction::make('shipAll')->schemaComponent('shipActions', schema: 'content'), ['date' => now()->toDateString(), 'notify' => false])
            ->assertHasNoActionErrors();

        $this->assertSame(3, $this->aktion->orders()->where('status', OrderStatus::Shipped)->count());
        $this->assertSame(AktionStatus::Done, $this->aktion->fresh()->status);
    }
}
