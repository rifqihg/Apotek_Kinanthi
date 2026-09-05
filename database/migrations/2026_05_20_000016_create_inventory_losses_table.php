<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Inventory Losses
     * Pencatatan kehilangan/kerusakan stok (obat, bahan baku, produk racikan).
     * FK: raw_materials, medicines, compounded_products, warehouse_inventories,
     *     raw_material_stocks, users
     */
    public function up(): void
    {
        Schema::create('inventory_losses', function (Blueprint $table) {
            $table->id();
            $table->string('tipe_loss')->default('manual_disposal');
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->onDelete('cascade');
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->onDelete('cascade');
            $table->foreignId('compounded_product_id')->nullable()->constrained('compounded_products')->onDelete('cascade');
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->onDelete('cascade');
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->onDelete('cascade');
            $table->decimal('qty_lost', 15, 2);
            $table->string('reason');
            $table->string('evidence_path')->nullable(); // Photo of the condition
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('tipe_loss');
            $table->index('raw_material_id');
            $table->index('medicine_id');
            $table->index('compounded_product_id');
            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_losses');
    }
};
