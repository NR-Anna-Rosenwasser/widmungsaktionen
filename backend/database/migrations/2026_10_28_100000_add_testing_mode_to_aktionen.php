<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Testing mode: an Aktion that only people with its secret preview link see,
 * with its own order numbers (TEST-…) so the real sequence has no gaps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aktionen', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('status');
            $table->string('preview_token', 64)->nullable()->unique()->after('is_test');
        });

        // Only one open real Aktion per tenant; test Aktionen don't count.
        DB::statement('DROP INDEX aktionen_one_open_per_tenant');
        DB::statement("CREATE UNIQUE INDEX aktionen_one_open_per_tenant ON aktionen (tenant_id) WHERE status = 'open' AND NOT is_test");

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('test_order_sequence')->default(0)->after('order_sequence');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX aktionen_one_open_per_tenant');
        DB::statement("CREATE UNIQUE INDEX aktionen_one_open_per_tenant ON aktionen (tenant_id) WHERE status = 'open'");

        Schema::table('aktionen', function (Blueprint $table) {
            $table->dropColumn(['is_test', 'preview_token']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('test_order_sequence');
        });
    }
};
