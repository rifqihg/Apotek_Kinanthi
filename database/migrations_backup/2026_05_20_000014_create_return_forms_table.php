<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Return Forms
     * Formulir retur obat ke supplier.
     * FK: incoming_stocks, suppliers, users
     */
    public function up(): void
    {
        Schema::create('return_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_stock_id')->constrained('incoming_stocks')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->integer('qty_retur');
            $table->text('alasan_retur');
            $table->enum('status', ['draft', 'sent', 'acknowledged'])->default('draft');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('incoming_stock_id');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_forms');
    }
};
