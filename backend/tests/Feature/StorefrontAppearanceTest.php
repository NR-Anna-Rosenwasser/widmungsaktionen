<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditTenantSettings;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StorefrontAppearance;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');
    }

    protected function storefront(): array
    {
        return $this->getJson("/api/tenants/{$this->tenant->slug}")->assertOk()->json('tenant.storefront');
    }

    public function test_defaults_with_shop_name(): void
    {
        $storefront = $this->storefront();

        $this->assertSame('#2B3C69', $storefront['colors']['primary']);
        $this->assertSame('Dein Buch. Persönlich gewidmet.', $storefront['texts']['de']['title']);
        $this->assertStringContainsString('Anna Rosenwasser widmet alle Bücher', $storefront['texts']['de']['lead']);
        $this->assertSame('Anna Rosenwasser · Order signed books', $storefront['texts']['en']['seo_title']);
        $this->assertSame('heart', $storefront['hero']['visual']);
        $this->assertTrue($storefront['sections']['lookup']);
    }

    public function test_overrides_per_locale_and_section_toggles(): void
    {
        $this->tenant->update(['settings' => ['storefront' => [
            'texts' => ['de' => ['title' => 'Herz, signiert.', 'cta' => ' '], 'en' => ['title' => 'Signed by {name}']],
            'sections' => ['how' => false],
            'colors' => ['primary' => '112233', 'accent' => 'not-a-colour'],
        ]]]);

        $storefront = $this->storefront();

        $this->assertSame('Herz, signiert.', $storefront['texts']['de']['title']);
        $this->assertSame('Jetzt bestellen', $storefront['texts']['de']['cta'], 'blank override falls back');
        $this->assertSame('Signed by Anna Rosenwasser', $storefront['texts']['en']['title']);
        $this->assertFalse($storefront['sections']['how']);
        $this->assertTrue($storefront['sections']['books']);
        $this->assertSame('#112233', $storefront['colors']['primary']);
        $this->assertSame('#D989B2', $storefront['colors']['accent'], 'invalid colour falls back');
    }

    public function test_legacy_intro_is_used_as_german_lead(): void
    {
        $this->tenant->update(['settings' => ['branding' => ['intro' => 'Alter Einleitungstext']]]);

        $this->assertSame('Alter Einleitungstext', $this->storefront()['texts']['de']['lead']);
    }

    public function test_image_visual_without_image_falls_back_and_preview_image_chain(): void
    {
        $this->tenant->update(['settings' => ['storefront' => ['hero' => ['visual' => 'image']]]]);
        $this->assertSame('none', $this->storefront()['hero']['visual']);
        $this->assertNull($this->storefront()['seo']['image_url']);

        $this->tenant->books()->first()->update(['cover_path' => 'covers/herz.jpg']);
        $this->assertStringEndsWith('/storage/covers/herz.jpg', $this->storefront()['seo']['image_url']);

        $this->tenant->update(['settings' => ['storefront' => ['hero' => ['visual' => 'image', 'image_path' => 'tenants/1/hero.jpg']]]]);
        $storefront = $this->storefront();
        $this->assertSame('image', $storefront['hero']['visual']);
        $this->assertStringEndsWith('/storage/tenants/1/hero.jpg', $storefront['seo']['image_url']);
    }

    public function test_contrast_ratio(): void
    {
        $this->assertSame(21.0, StorefrontAppearance::contrast('#000000', '#FFFFFF'));
        // Anna's navy on pink: fine for headlines, just below the guideline for small text.
        $this->assertSame(4.2, StorefrontAppearance::contrast('#2B3C69', '#D989B2'));
        $this->assertLessThan(4.5, StorefrontAppearance::contrast('#FFFF00', '#FFFFFF'));
    }

    public function test_settings_page_shows_preview_and_saves_colours_and_texts(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);

        Livewire::test(EditTenantSettings::class)
            ->assertSee('Für grosse Schrift gut, für kleine Schrift knapp')
            ->assertDontSee('Schwacher Kontrast')
            ->fillForm([
                'settings.storefront.colors.primary' => '#FFFF00',
                'settings.storefront.texts.de.title' => 'Neuer Titel',
                'settings.storefront.sections.lookup' => false,
            ])
            ->assertSee('Schwacher Kontrast')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenant->refresh();
        $this->assertSame('#FFFF00', $this->tenant->setting('storefront.colors.primary'));
        $this->assertSame('Neuer Titel', $this->tenant->setting('storefront.texts.de.title'));
        $this->assertFalse($this->tenant->setting('storefront.sections.lookup'));
    }

    public function test_switching_the_hero_visual_keeps_the_uploaded_image(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));
        Filament::setTenant($this->tenant);
        Storage::fake('public');
        Storage::disk('public')->put('tenants/1/hero.jpg', UploadedFile::fake()->image('hero.jpg')->getContent());
        $this->tenant->update(['settings' => ['storefront' => ['hero' => ['visual' => 'image', 'image_path' => 'tenants/1/hero.jpg', 'image_alt' => 'Buch']]]]);

        Livewire::test(EditTenantSettings::class)
            ->fillForm(['settings.storefront.hero.visual' => 'heart'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenant->refresh();
        $this->assertSame('heart', $this->tenant->setting('storefront.hero.visual'));
        $this->assertSame('tenants/1/hero.jpg', $this->tenant->setting('storefront.hero.image_path'));
        $this->assertSame('Buch', $this->tenant->setting('storefront.hero.image_alt'));
    }

    public function test_wizard_is_wide(): void
    {
        $this->actingAs(User::firstWhere('email', 'admin@widmungen.test'));

        $this->assertSame('6xl', (new RegisterTenant)->getMaxWidth()->value);
    }
}
