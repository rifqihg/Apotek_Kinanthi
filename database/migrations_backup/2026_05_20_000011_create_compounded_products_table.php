<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Compounded Products + Stock Deductions
     * Produk hasil racikan dan catatan deduksi stok bahan.
     * FK: compounding_submissions, compounding_details, users
     */
    public function up(): void
    {
        // ── Tabel Produk Hasil Racikan ──
        Schema::create('compounded_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_submission_id')->constrained()->onDelete('cascade');
            $table->string('nama_produk');
            $table->string('batch_no');
            $table->date('tanggal_racik');
            $table->date('bud_date'); // Beyond Use Date
            $table->decimal('qty_hasil', 12, 2);
            $table->decimal('actual_yield', 15, 2)->nullable();
            $table->decimal('production_loss', 15, 2)->default(0);
            $table->string('satuan')->default('Gram');
            $table->decimal('base_cost', 15, 2)->default(0);
            $table->decimal('hpp_per_unit', 12, 2)->default(0);
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->string('status')->default('pending_physical_check');
            $table->boolean('is_frozen')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('compounding_submission_id');
            $table->index('status');
            $table->index('bud_date');
            $table->index('created_by');
        });

        // ── Tabel Deduksi Stok untuk Racikan ──
        Schema::create('compounding_stock_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_detail_id')->constrained('compounding_details')->onDelete('cascade');
            // Reference to the specific batch/stock source (polymorphic manual)
            $table->unsignedBigInteger('stock_source_id'); // RawMaterialStock id or WarehouseInventory id
            $table->string('stock_source_type'); // 'raw_material_stock' or 'warehouse_inventory'
            $table->decimal('qty_deducted', 15, 2);
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('compounding_detail_id');
            $table->index(['stock_source_id', 'stock_source_type'], 'csd_stock_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compounding_stock_deductions');
        Schema::dropIfExists('compounded_products');
    }
};
