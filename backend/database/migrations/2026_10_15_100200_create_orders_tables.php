<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aktion_id')->constrained('aktionen');
            // Assigned on submit, e.g. "AR-2026-0042".
            $table->string('number')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('locale', 5)->default('de');
            $table->string('current_step')->nullable();

            $table->string('email');
            $table->string('phone')->nullable();

            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_postal_code', 16)->nullable();
            $table->string('billing_town')->nullable();
            $table->string('billing_country', 2)->default('CH');

            $table->boolean('is_gift')->default(false);
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('shipping_street')->nullable();
            $table->string('shipping_postal_code', 16)->nullable();
            $table->string('shipping_town')->nullable();
            $table->string('shipping_country', 2)->default('CH');
            $table->text('gift_message')->nullable();

            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->unsignedInteger('total')->default(0);

            $table->boolean('newsletter_opt_in')->default(false);
            $table->boolean('reminders_opt_out')->default(false);
            $table->timestamp('abandoned_reminder_sent_at')->nullable();

            // Per-Aktion sequence for the signing stack (post-it number).
            $table->unsignedInteger('stack_number')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['aktion_id', 'stack_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained();
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('position');

            $table->boolean('signature_only')->default(false);
            $table->string('recipient_name')->nullable();
            $table->text('wish')->nullable();
            $table->string('moderation_status')->default('pending');
            $table->text('moderation_note')->nullable();

            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('flagged_at')->nullable();
            $table->text('flag_note')->nullable();
            $table->timestamp('dedication_anonymised_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending');
            $table->string('reference')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->date('shipped_at');
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
