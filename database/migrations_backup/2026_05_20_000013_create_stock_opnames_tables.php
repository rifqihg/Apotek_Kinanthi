<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Stock Opnames + Items
     * Stock opname oleh Petugas Gudang dan detail itemnya.
     * FK: users, categories, medicines, raw_materials, warehouse_inventories,
     *     raw_material_stocks, compounded_products
     */
    public function up(): void
    {
        // ── Tabel Sesi Stock Opname ──
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->date('opname_date');
            $table->boolean('is_blind_count')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('created_by');
            $table->index('status');
            $table->index('opname_date');
        });

        // ── Tabel Detail Item Stock Opname ──
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->nullOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->nullOnDelete();
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->nullOnDelete();
            $table->foreignId('compounded_product_id')->nullable()->constrained('compounded_products')->nullOnDelete();
            $table->string('batch_no');
            $table->integer('batch_id')->nullable();
            $table->decimal('system_stock', 12, 4);
            $table->decimal('physical_stock', 12, 4)->nullable();
            $table->decimal('difference', 12, 4)->default(0);
            $table->text('reason')->nullable(); // Wajib jika selisih != 0
            $table->string('evidence_photo')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('stock_opname_id');
            $table->index('medicine_id');
            $table->index('raw_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
