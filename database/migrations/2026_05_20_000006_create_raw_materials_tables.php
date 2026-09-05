<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Raw Materials + Raw Material Stocks
     * Bahan baku racikan dan batch stoknya.
     * FK: categories, users
     */
    public function up(): void
    {
        // ── Tabel Katalog Bahan Baku ──
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nama_bahan');
            $table->string('bentuk_sediaan')->nullable();
            $table->string('kode_bahan')->unique()->nullable();
            $table->string('satuan_ukur', 50)->default('g');
            $table->string('base_unit')->nullable();
            $table->string('large_unit')->nullable();
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->decimal('stok_total', 12, 4)->default(0);
            $table->decimal('density_factor', 8, 4)->default(1.0000);
            $table->decimal('harga_beli_satuan', 15, 2)->default(0);
            $table->decimal('threshold_min', 12, 4)->default(0);
            $table->text('deskripsi')->nullable();
            $table->string('status')->default('pending_registration');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('admin_gate_1_at')->nullable();
            $table->timestamp('admin_gate_2_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('category_id');
            $table->index('status');
            $table->index('creator_id');
            $table->index('verified_by');
        });

        // ── Tabel Batch Stok Bahan Baku ──
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date')->nullable();
            $table->decimal('expected_quantity', 15, 2)->nullable();
            $table->decimal('actual_quantity', 15, 2)->nullable();
            $table->decimal('stock_on_hand', 12, 4)->default(0);
            $table->string('status')->default('pending_physical_check');
            $table->boolean('is_frozen')->default(false);
            $table->string('shelf_location')->nullable();
            $table->string('rack_location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index(['raw_material_id', 'batch_no']);
            $table->index('expired_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_stocks');
        Schema::dropIfExists('raw_materials');
    }
};
