<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('order_prefix', 8);
            $table->unsignedInteger('order_sequence')->default(0);

            // Free-form sender block for letters, e.g. "Nationalrätin Anna Rosenwasser\nc/o Büro für Fortschritt\n…"
            $table->text('letterhead')->nullable();

            // Structured creditor address for the Swiss QR bill.
            $table->string('creditor_name')->nullable();
            $table->string('creditor_street')->nullable();
            $table->string('creditor_building_number', 16)->nullable();
            $table->string('creditor_postal_code', 16)->nullable();
            $table->string('creditor_town')->nullable();
            $table->string('creditor_country', 2)->default('CH');
            $table->string('iban', 34)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_reply_to')->nullable();

            $table->string('stripe_publishable_key')->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();

            $table->string('newsletter_webhook_url')->nullable();
            $table->text('newsletter_webhook_secret')->nullable();

            // Everything else (shipping tiers, payment methods, print settings, legal links, …).
            $table->jsonb('settings')->default('{}');

            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
