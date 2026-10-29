<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Filament\Pages\Tenancy\EditTenantSettings;
use App\Filament\Pages\Texte;
use App\Mail\OrderConfirmationMail;
use App\Models\Aktion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\InvoicePdf;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class LocalesTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected Tenant $tenant;

    protected Aktion $aktion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
        $this->aktion = $this->tenant->aktionen()->where('status', AktionStatus::Open)->first();
    }

    protected function setLocales(array $locales, string $default): void
    {
        $this->tenant->update(['settings' => [...$this->tenant->settings, 'locales' => $locales, 'default_locale' => $default]]);
    }

    protected function actAsAdmin(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    public function test_shops_offer_german_and_english_by_default(): void
    {
        $tenant = $this->getJson("/api/tenants/{$this->tenant->slug}")->assertOk()->json('tenant');

        $this->assertSame(['de', 'en'], $tenant['locales']);
        $this->assertSame('de', $tenant['default_locale']);
        $this->assertSame(['de', 'en'], array_keys($tenant['texts']));
    }

    public function test_storefront_gets_only_the_shops_languages(): void
    {
        $this->setLocales(['it', 'fr'], 'fr');

        $response = $this->getJson("/api/tenants/{$this->tenant->slug}")->assertOk();

        $this->assertSame(['fr', 'it'], $response->json('tenant.locales'), 'platform order');
        $this->assertSame('fr', $response->json('tenant.default_locale'));
        $this->assertSame(['fr', 'it'], array_keys($response->json('tenant.texts')));
        $this->assertSame(['fr', 'it'], array_keys($response->json('aktion.texts')));
        $this->assertNotEmpty($response->json('tenant.texts.fr')['home.title']);
    }

    public function test_orders_accept_only_the_shops_languages(): void
    {
        $this->setLocales(['de', 'fr'], 'fr');
        $base = "/api/tenants/{$this->tenant->slug}/orders";
        $copies = [['book_id' => $this->tenant->books()->first()->id]];

        $this->postJson($base, ['email' => 'a@example.com', 'locale' => 'en', 'copies' => $copies])->assertUnprocessable();

        $token = $this->postJson($base, ['email' => 'a@example.com', 'copies' => $copies])->assertCreated()->json('token');
        $this->assertSame('fr', $this->tenant->orders()->firstWhere('token', $token)->locale, 'shop default');
    }

    public function test_french_order_gets_french_mail_dates_links_and_invoice(): void
    {
        $this->setLocales(['de', 'fr'], 'de');
        $order = $this->submitOrder($this->tenant, overrides: ['locale' => 'fr']);

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString($order->aktion->order_deadline->locale('fr_CH')->isoFormat('LL'), $html);
        $this->assertStringContainsString($order->storefrontUrl().'?lang=fr', $html);
        $this->assertStringEndsWith('&lang=fr', $order->resumeUrl());
        $this->assertStringContainsString('Merci pour ta commande', $html);
        $this->assertSame("Facture-{$order->number}.pdf", app(InvoicePdf::class)->filename($order));
    }

    public function test_language_no_longer_offered_falls_back_to_the_default(): void
    {
        $order = $this->submitOrder($this->tenant, overrides: ['locale' => 'en']);
        $this->setLocales(['de'], 'de');

        $this->assertSame('de', $order->fresh()->language());
        $this->assertSame('de', (new OrderConfirmationMail($order->fresh()))->locale);
    }

    public function test_texts_page_has_a_tab_per_language_and_keeps_the_others(): void
    {
        $this->actAsAdmin();
        $this->setLocales(['de', 'fr'], 'de');
        $this->tenant->update(['settings' => [...$this->tenant->settings, 'texts' => [
            'de' => ['mail' => ['signed' => ['intro' => 'Gewidmet!']]],
            'en' => ['mail' => ['signed' => ['intro' => 'Signed!']]],
        ]]]);

        Livewire::test(Texte::class, ['bereich' => 'mail_signed'])
            ->assertFormFieldExists('de.mail.signed.intro')
            ->assertFormFieldExists('fr.mail.signed.intro')
            ->assertFormFieldDoesNotExist('en.mail.signed.intro')
            ->assertSee('Deutsch: Gewidmet!')
            ->fillForm(['fr.mail.signed.intro' => 'Dédicacé !'])
            ->call('save')
            ->assertHasNoFormErrors();

        $texts = $this->tenant->fresh()->settings['texts'];
        $this->assertSame('Dédicacé !', $texts['fr']['mail']['signed']['intro']);
        $this->assertSame('Gewidmet!', $texts['de']['mail']['signed']['intro']);
        $this->assertSame('Signed!', $texts['en']['mail']['signed']['intro'], 'languages not offered keep their texts');
    }

    public function test_settings_require_the_default_among_the_languages(): void
    {
        $this->actAsAdmin();

        Livewire::test(EditTenantSettings::class)
            ->fillForm(['settings.locales' => ['fr', 'it'], 'settings.default_locale' => 'it'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['fr', 'it'], $this->tenant->fresh()->locales());
        $this->assertSame('it', $this->tenant->fresh()->defaultLocale());

        Livewire::test(EditTenantSettings::class)
            ->fillForm(['settings.locales' => [], 'settings.default_locale' => 'de'])
            ->call('save')
            ->assertHasFormErrors(['settings.locales', 'settings.default_locale']);
    }

    public function test_adding_a_language_keeps_the_default_and_its_options(): void
    {
        $this->actAsAdmin();

        Livewire::test(EditTenantSettings::class)
            ->fillForm(['settings.locales' => ['de', 'en', 'fr']])
            ->assertFormSet(['settings.default_locale' => 'de'])
            ->assertSee('Français')
            // Removing the default moves it to the first language left (platform order).
            ->fillForm(['settings.locales' => ['en', 'fr']])
            ->assertFormSet(['settings.default_locale' => 'fr']);
    }

    public function test_migration_gives_existing_shops_german_and_english(): void
    {
        $migration = require database_path('migrations/2026_10_29_120000_add_locales_to_tenant_settings.php');
        $other = Tenant::query()->whereKeyNot($this->tenant->id)->first();
        $migration->down();
        DB::table('tenants')->where('id', $this->tenant->id)->update(['settings' => json_encode(['max_copies_per_order' => 3])]);
        $other?->update(['settings' => [...$other->settings, 'locales' => ['fr'], 'default_locale' => 'fr']]);

        $migration->up();

        $settings = $this->tenant->fresh()->settings;
        $this->assertSame(['de', 'en'], $settings['locales']);
        $this->assertSame('de', $settings['default_locale']);
        $this->assertSame(3, $settings['max_copies_per_order']);
        if ($other) {
            $this->assertSame(['fr'], $other->fresh()->settings['locales'], 'an explicit choice is kept');
        }
    }
}
