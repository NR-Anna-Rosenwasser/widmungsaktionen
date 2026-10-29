<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official directory of postal codes and localities in CH and LI (swisstopo),
 * for the postal code dropdown in the order form. Filled by address:import-postcodes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_localities', function (Blueprint $table) {
            $table->id();
            $table->string('postal_code', 4);
            $table->string('additional_digit', 2);
            $table->string('name');
            $table->string('country', 2);
            $table->unique(['postal_code', 'additional_digit', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_localities');
    }
};
