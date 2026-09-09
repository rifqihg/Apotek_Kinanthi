<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Stock Confirmations
     * Konfirmasi penerimaan stok oleh Petugas Gudang.
     * FK: users
     */
    public function up(): void
    {
        Schema::create('stock_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_type'); // 'supplier' or 'compounding'
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('item_type', ['obat', 'bahan_baku'])->default('obat');
            $table->string('unit_type')->nullable(); // Box/Botol/Gram
            $table->integer('conversion_rate')->default(1);
            $table->string('evidence_image')->nullable();

            $table->decimal('qty_expected', 12, 2);
            $table->decimal('qty_received', 12, 2)->nullable();
            $table->text('condition_note')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index(['reference_id', 'reference_type']);
            $table->index('user_id');
            $table->index('is_confirmed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_confirmations');
    }
};
