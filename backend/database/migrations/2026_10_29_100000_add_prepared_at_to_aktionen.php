<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Widmungsaktion vorbereiten» is pressed once per Aktion (and can be undone
 * until the first book is signed). Aktionen that already have post-it
 * numbers count as prepared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aktionen', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable()->after('status');
        });

        DB::table('aktionen')
            ->whereExists(fn ($q) => $q->from('orders')->whereColumn('orders.aktion_id', 'aktionen.id')->whereNotNull('orders.stack_number'))
            ->update(['prepared_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('aktionen', function (Blueprint $table) {
            $table->dropColumn('prepared_at');
        });
    }
};
