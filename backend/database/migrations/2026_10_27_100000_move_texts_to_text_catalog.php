<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editable texts move to one place (App\Support\Texts\TextCatalog):
 *
 * - shop overrides settings.storefront.texts.<locale>.<key> (and the legacy
 *   branding.intro) become settings.texts.<locale>.storefront.home.<key>,
 *   with {name} (shop name) renamed to {shop};
 * - an Aktion's «Text im Shop» (description) becomes its start page extra
 *   text in both languages, as it was shown in both before.
 */
return new class extends Migration
{
    /** Old storefront text key => new key below storefront.home. */
    protected const KEYS = [
        'eyebrow' => 'eyebrow', 'title' => 'title', 'lead' => 'lead', 'cta' => 'cta',
        'books_title' => 'booksTitle', 'how_title' => 'howTitle',
        'how_1_title' => 'how1Title', 'how_1_text' => 'how1Text',
        'how_2_title' => 'how2Title', 'how_2_text' => 'how2Text',
        'how_3_title' => 'how3Title', 'how_3_text' => 'how3Text',
        'closed_title' => 'closedTitle', 'lookup_title' => 'lookupTitle', 'lookup_text' => 'lookupText',
        'seo_title' => 'seoTitle', 'seo_description' => 'seoDescription',
    ];

    public function up(): void
    {
        Schema::table('aktionen', function (Blueprint $table) {
            $table->jsonb('texts')->default('{}');
        });

        DB::table('aktionen')->whereNotNull('description')->orderBy('id')->each(function (object $aktion) {
            $description = trim((string) $aktion->description);
            if ($description !== '') {
                DB::table('aktionen')->where('id', $aktion->id)->update([
                    'texts' => json_encode(['de' => ['home' => $description], 'en' => ['home' => $description]]),
                ]);
            }
        });

        Schema::table('aktionen', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];

            foreach (['de', 'en'] as $locale) {
                foreach (self::KEYS as $old => $new) {
                    $value = trim((string) Arr::get($settings, "storefront.texts.$locale.$old"));
                    if ($old === 'lead' && $locale === 'de' && $value === '') {
                        $value = trim((string) Arr::get($settings, 'branding.intro'));
                    }
                    if ($value !== '') {
                        $settings['texts'][$locale]['storefront']['home'][$new] = str_replace('{name}', '{shop}', $value);
                    }
                }
            }

            unset($settings['storefront']['texts'], $settings['branding']);

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        Schema::table('aktionen', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        DB::table('aktionen')->orderBy('id')->each(function (object $aktion) {
            $texts = json_decode($aktion->texts ?? '{}', true) ?: [];
            $description = $texts['de']['home'] ?? $texts['en']['home'] ?? null;
            if ($description !== null) {
                DB::table('aktionen')->where('id', $aktion->id)->update(['description' => $description]);
            }
        });

        Schema::table('aktionen', function (Blueprint $table) {
            $table->dropColumn('texts');
        });

        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];

            foreach (['de', 'en'] as $locale) {
                foreach (self::KEYS as $old => $new) {
                    $value = Arr::get($settings, "texts.$locale.storefront.home.$new");
                    if ($value !== null) {
                        $settings['storefront']['texts'][$locale][$old] = str_replace('{shop}', '{name}', $value);
                    }
                }
            }

            // Texts that did not exist before are dropped.
            unset($settings['texts']);

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        });
    }
};
