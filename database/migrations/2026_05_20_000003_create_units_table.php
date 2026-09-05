<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Units
     * Satuan ukur (konversi satuan besar ke kecil).
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->string('base_unit')->nullable(); // Reference to base unit name if this is a large unit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
