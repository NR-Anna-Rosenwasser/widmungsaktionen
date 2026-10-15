<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('abandoned_reminder_sent_at');
            // How often an unpaid order was moved on to the next Aktion.
            $table->unsignedSmallInteger('moved_count')->default(0)->after('stack_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_reminder_sent_at', 'moved_count']);
        });
    }
};
