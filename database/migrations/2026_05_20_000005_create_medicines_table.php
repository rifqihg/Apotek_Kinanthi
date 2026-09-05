<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Medicines (Master Obat)
     * Data master obat yang dikelola oleh Admin.
     * FK: categories
     */
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nama_obat');
            $table->string('kategori')->nullable();
            $table->string('kode_obat')->unique()->nullable();
            $table->enum('satuan_stok', ['Tube', 'Gram', 'Pot', 'Tablet', 'Kapsul', 'Botol', 'Ampul', 'Sachet', 'Strip', 'Box'])->default('Tablet');
            $table->string('satuan_besar')->nullable();
            $table->integer('rasio_konversi')->default(1);
            $table->string('base_unit')->nullable();
            $table->string('large_unit')->nullable();
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->decimal('stok_total', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->integer('threshold_min')->default(10);
            $table->text('deskripsi')->nullable();
            $table->string('foto_obat')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('category_id');
            $table->index('status');
            $table->index('nama_obat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
