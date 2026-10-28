<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unpaid orders after the deadline are now decided one by one in «Offene
 * Bestellungen» by default. Existing shops switch to that too, whatever
 * they had chosen before.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];
            $settings['unpaid_after_deadline'] = 'manual';

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // The previous choice isn't kept; «move» was the old default.
        DB::table('tenants')->orderBy('id')->each(function (object $tenant) {
            $settings = json_decode($tenant->settings ?? '{}', true) ?: [];
            if (($settings['unpaid_after_deadline'] ?? null) === 'manual') {
                $settings['unpaid_after_deadline'] = 'move';
                DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }
};
