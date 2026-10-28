<?php

namespace Tests\Feature;

use App\Enums\AktionStatus;
use App\Filament\Pages\Tenancy\EditTenantSettings;
use App\Filament\Pages\Texte;
use App\Filament\Resources\Aktions\Pages\EditAktion;
use App\Mail\OrderConfirmationMail;
use App\Models\Aktion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\InvoicePdf;
use App\Support\Texts\TextCatalog;
use App\Support\Texts\Texts;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class TextsTest extends TestCase
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

    protected function setTexts(array $texts): void
    {
        $this->tenant->update(['settings' => [...$this->tenant->settings, 'texts' => $texts]]);
    }

    protected function actAsAdmin(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
    }

    public function test_catalog_has_defaults_and_declares_every_placeholder(): void
    {
        $keys = array_keys(TextCatalog::texts());

        foreach (TextCatalog::texts() as $key => $text) {
            foreach (TextCatalog::LOCALES as $locale) {
                $default = app(Texts::class)->default($key, $locale);
                $this->assertNotSame($key, $default, "$key has no $locale default");
                $this->assertSame([], Texts::unknownPlaceholders($default, $text['placeholders']), "$key ($locale) uses undeclared placeholders");
            }

            foreach ($keys as $other) {
                $this->assertFalse(str_starts_with($other, "$key."), "$key is a prefix of $other (they share storage)");
            }
        }
    }

    public function test_storefront_texts_with_overrides_markdown_and_shop_name(): void
    {
        $this->setTexts([
            'de' => ['storefront' => ['home' => ['title' => 'Herz, signiert.', 'cta' => ' ', 'lead' => "**{shop}** widmet.\n\n<script>x</script>"]]],
            'en' => ['storefront' => ['home' => ['title' => 'Signed by {shop}']]],
        ]);

        $texts = $this->getJson("/api/tenants/{$this->tenant->slug}")->assertOk()->json('tenant.texts');

        $this->assertSame('Herz, signiert.', $texts['de']['home.title']);
        $this->assertSame('Jetzt bestellen', $texts['de']['home.cta'], 'blank override falls back');
        $this->assertSame('Signed by Anna Rosenwasser', $texts['en']['home.title']);
        $this->assertStringContainsString('<strong>Anna Rosenwasser</strong> widmet.', $texts['de']['home.lead']);
        $this->assertStringNotContainsString('<script>', $texts['de']['home.lead']);
        $this->assertSame('Anna Rosenwasser · Order signed books', $texts['en']['home.seoTitle']);
        // Placeholders the storefront fills stay in place.
        $this->assertStringContainsString('{email}', $texts['de']['tracking.thanksText']);
        $this->assertSame('Schritt {current} von {total}', $texts['de']['order.stepOf']);
    }

    public function test_aktion_extras_reach_the_storefront_and_order_page(): void
    {
        $this->aktion->update(['texts' => ['de' => ['home' => 'Diesmal *auch* signiert', 'tracking' => 'Danke, {shop}!'], 'en' => []]]);

        $aktion = $this->getJson("/api/tenants/{$this->tenant->slug}")->json('aktion');
        $this->assertSame('<p>Diesmal <em>auch</em> signiert</p>', $aktion['texts']['de']['home']);
        $this->assertNull($aktion['texts']['en']['home']);
        $this->assertNull($aktion['texts']['de']['order']);

        $order = $this->submitOrder($this->tenant);
        $tracking = $this->getJson("/api/tenants/{$this->tenant->slug}/orders/{$order->token}")->json('aktion.texts');
        $this->assertSame('<p>Danke, Anna Rosenwasser!</p>', $tracking['de']['tracking']);
    }

    public function test_mails_use_overrides_extras_and_escape_customer_data(): void
    {
        $this->setTexts(['de' => ['mail' => [
            'greeting' => 'Liebe*r {name}',
            'confirmation' => ['subject' => 'Juhu, {number}!', 'intro' => 'Danke von **{shop}**.'],
        ]]]);
        $this->aktion->update(['texts' => ['de' => ['mail_confirmation' => 'Frohe *Festtage*!']]]);

        $order = $this->submitOrder($this->tenant, 'invoice_in_parcel');
        $order->update(['billing_first_name' => '<b>Mia_</b>']);
        $mail = new OrderConfirmationMail($order->fresh());
        $html = $mail->render();

        $this->assertSame("Juhu, {$order->number}!", $mail->envelope()->subject);
        // Mail HTML carries inline styles on every tag.
        $this->assertMatchesRegularExpression('/Danke von <strong[^>]*>Anna Rosenwasser<\/strong>\./', $html);
        $this->assertMatchesRegularExpression('/Frohe <em[^>]*>Festtage<\/em>!/', $html);
        $this->assertStringContainsString('&lt;b&gt;Mia_&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Mia', $html);
    }

    public function test_invoice_uses_overrides_and_aktion_note(): void
    {
        $this->setTexts(['de' => ['invoice' => ['intro_advance' => 'Bitte *bis* {date} zahlen.']]]);
        $this->aktion->update(['texts' => ['de' => ['invoice' => 'Danke für die **Unterstützung**.']]]);

        $html = app(InvoicePdf::class)->html($this->submitOrder($this->tenant));

        $this->assertMatchesRegularExpression('/Bitte <em>bis<\/em> \d+\. \w+ \d{4} zahlen\./u', $html);
        $this->assertStringContainsString('Danke für die <strong>Unterstützung</strong>.', $html);
    }

    public function test_texts_page_saves_changes_and_drops_unchanged_defaults(): void
    {
        $this->actAsAdmin();
        $this->setTexts(['de' => ['mail' => ['signature' => 'Alles Liebe']]]);

        Livewire::test(Texte::class, ['bereich' => 'mail_signed'])
            ->assertFormSet(['de.mail.signed.intro' => null])
            ->fillForm([
                'de.mail.signed.intro' => 'Gewidmet! ✍️',
                'en.mail.signed.subject' => app(Texts::class)->default('mail.signed.subject', 'en'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->tenant->fresh()->settings;
        $this->assertSame('Gewidmet! ✍️', data_get($settings, 'texts.de.mail.signed.intro'));
        $this->assertNull(data_get($settings, 'texts.en.mail.signed.subject'), 'unchanged default is not stored');
        $this->assertSame('Alles Liebe', data_get($settings, 'texts.de.mail.signature'), 'other areas are kept');
    }

    public function test_every_area_of_the_texts_page_renders(): void
    {
        $this->actAsAdmin();

        foreach (TextCatalog::groups() as $name => $group) {
            $page = Livewire::test(Texte::class, ['bereich' => $name])->assertOk();

            foreach (array_keys($group['texts']) as $key) {
                $page->assertFormFieldExists("de.$key")->assertFormFieldExists("en.$key");
            }
        }
    }

    public function test_texts_page_rejects_unknown_placeholders_and_other_roles(): void
    {
        $this->actAsAdmin();

        Livewire::test(Texte::class, ['bereich' => 'mail_signed'])
            ->fillForm(['de.mail.signed.next' => 'Versand ab {datum}'])
            ->call('save')
            ->assertHasFormErrors(['de.mail.signed.next']);

        $this->actingAs(User::firstWhere('email', 'anna@widmungen.test'));
        $this->assertFalse(Texte::canAccess());
    }

    public function test_settings_page_keeps_texts(): void
    {
        $this->actAsAdmin();
        $this->setTexts(['de' => ['storefront' => ['home' => ['title' => 'Bleibt']]]]);

        Livewire::test(EditTenantSettings::class)->call('save')->assertHasNoFormErrors();

        $this->assertSame('Bleibt', data_get($this->tenant->fresh()->settings, 'texts.de.storefront.home.title'));
    }

    public function test_aktion_form_saves_extras(): void
    {
        $this->actAsAdmin();

        Livewire::test(EditAktion::class, ['record' => $this->aktion->getRouteKey()])
            ->fillForm(['texts.de.letter' => 'P.S. Frohe Weihnachten!'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('P.S. Frohe Weihnachten!', $this->aktion->fresh()->texts['de']['letter']);
    }

    public function test_migration_keeps_existing_texts_and_aktion_description(): void
    {
        $migration = require database_path('migrations/2026_10_27_100000_move_texts_to_text_catalog.php');
        $migration->down();

        DB::table('aktionen')->where('id', $this->aktion->id)->update(['description' => 'Herbst mit Lesung']);
        DB::table('tenants')->where('id', $this->tenant->id)->update(['settings' => json_encode([
            'storefront' => ['colors' => ['primary' => '#112233'], 'texts' => ['de' => ['title' => 'Mein Titel', 'how_1_text' => 'Von {name}'], 'en' => ['seo_title' => 'Hi']]],
            'branding' => ['intro' => 'Alte Einleitung'],
            'max_copies_per_order' => 3,
        ])]);

        $migration->up();

        $this->assertSame(['de' => ['home' => 'Herbst mit Lesung'], 'en' => ['home' => 'Herbst mit Lesung']], $this->aktion->fresh()->texts);

        $settings = $this->tenant->fresh()->settings;
        $this->assertEquals([
            'de' => ['storefront' => ['home' => ['title' => 'Mein Titel', 'lead' => 'Alte Einleitung', 'how1Text' => 'Von {shop}']]],
            'en' => ['storefront' => ['home' => ['seoTitle' => 'Hi']]],
        ], $settings['texts']);
        $this->assertSame('#112233', $settings['storefront']['colors']['primary']);
        $this->assertSame(3, $settings['max_copies_per_order']);
        $this->assertArrayNotHasKey('texts', $settings['storefront']);

        $storefront = $this->getJson("/api/tenants/{$this->tenant->slug}")->json();
        $this->assertSame('Mein Titel', $storefront['tenant']['texts']['de']['home.title']);
        $this->assertSame('<p>Von Anna Rosenwasser</p>', $storefront['tenant']['texts']['de']['home.how1Text']);
        $this->assertSame('<p>Herbst mit Lesung</p>', $storefront['aktion']['texts']['en']['home']);
    }
}
