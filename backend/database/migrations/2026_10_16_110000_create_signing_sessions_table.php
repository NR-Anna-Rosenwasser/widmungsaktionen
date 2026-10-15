<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One sitting of the Widmen workflow, kept as an audit trail.
        Schema::create('signing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktion_id')->constrained('aktionen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('signed_count')->default(0);
            $table->jsonb('log')->default('[]');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_sessions');
    }
};
