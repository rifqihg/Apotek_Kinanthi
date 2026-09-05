<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Compounding Formulas (Resep Racikan)
     * Master formula/resep racikan obat.
     * Dikelola oleh: Apoteker
     */
    public function up(): void
    {
        Schema::create('compounding_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_resep');
            $table->string('kategori_sediaan')->nullable();
            $table->text('instruksi_racik')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compounding_formulas');
    }
};
