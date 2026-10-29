<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Each shop now chooses its languages (settings.locales) and a default
 * (settings.default_locale). Existing shops keep what they had: German and
 * English, German first. Stored explicitly, so a later change of the
 * defaults for new shops doesn't affect them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];
            $settings['locales'] ??= ['de', 'en'];
            $settings['default_locale'] ??= 'de';

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];
            unset($settings['locales'], $settings['default_locale']);

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        });
    }
};
