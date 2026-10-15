<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('author_name');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('isbn', 20)->nullable();
            // Money is stored in Rappen.
            $table->unsignedInteger('price');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('aktionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('orders_open_at');
            $table->timestamp('order_deadline');
            $table->date('signing_date');
            $table->date('estimated_shipping_date');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Only one open Aktion per tenant.
        DB::statement("CREATE UNIQUE INDEX aktionen_one_open_per_tenant ON aktionen (tenant_id) WHERE status = 'open'");

        Schema::create('aktion_book', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktion_id')->constrained('aktionen')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_override')->nullable();
            $table->unsignedInteger('sort')->default(0);

            $table->unique(['aktion_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktion_book');
        Schema::dropIfExists('aktionen');
        Schema::dropIfExists('books');
    }
};
