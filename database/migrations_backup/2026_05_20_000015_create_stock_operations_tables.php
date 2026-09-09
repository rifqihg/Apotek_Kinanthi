<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Stock Mutations + Stock Adjustments + Stock Logs
     * Log mutasi stok, penyesuaian manual, dan catatan perubahan stok.
     * FK: warehouse_inventories, raw_material_stocks, medicines, users
     */
    public function up(): void
    {
        // ── Tabel Mutasi Stok (per batch) ──
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->onDelete('cascade');
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->onDelete('cascade');
            $table->unsignedBigInteger('item_id')->nullable(); // Medicine or Raw Material ID
            $table->string('item_type')->nullable(); // Model class
            $table->string('type'); // Masuk, Keluar, Penyesuaian, etc.
            $table->decimal('quantity', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable(); // e.g. IncomingStock, CompoundingSubmission
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('warehouse_inventory_id');
            $table->index('raw_material_stock_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });

        // ── Tabel Penyesuaian Stok Manual ──
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['in', 'out']);
            $table->integer('qty');
            $table->text('alasan');
            $table->foreignId('adjusted_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('medicine_id');
            $table->index('adjusted_by');
        });

        // ── Tabel Log Perubahan Stok ──
        Schema::create('stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->onDelete('cascade');
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->onDelete('cascade');
            $table->string('action'); // Adjustment, Disposal, Restock, etc.
            $table->decimal('qty_before', 15, 2);
            $table->decimal('qty_after', 15, 2);
            $table->decimal('change_amount', 15, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('raw_material_id');
            $table->index('medicine_id');
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_logs');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_mutations');
    }
};
