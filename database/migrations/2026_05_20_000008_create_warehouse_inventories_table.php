<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Warehouse Inventories (Stok Gudang per Batch)
     * Inventaris gudang per-batch obat (FEFO/FIFO).
     * Dikelola oleh: Petugas Gudang
     * FK: incoming_stocks, medicines
     */
    public function up(): void
    {
        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_stock_id')->nullable()->constrained('incoming_stocks')->onDelete('set null');
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date');
            $table->decimal('stock_on_hand', 12, 2)->default(0);
            $table->enum('status', ['active', 'pending_return', 'pending_destruction'])->default('active');
            $table->enum('location_type', ['gudang', 'kasir'])->default('gudang');
            $table->foreignId('location_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_frozen')->default(false);
            $table->string('shelf_location')->nullable();
            $table->string('rack_location')->nullable();
            $table->date('transferred_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index(['medicine_id', 'batch_no']);
            $table->index('expired_date');
            $table->index('status');
            $table->index('incoming_stock_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventories');
    }
};
