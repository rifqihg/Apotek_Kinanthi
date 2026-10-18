<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add missing performance indexes.
     */
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->index('created_at', 'stock_mutations_created_at_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->index('name', 'patients_name_idx');
        });

        Schema::table('incoming_stocks', function (Blueprint $table) {
            $table->index(['status_gate', 'qty_converted'], 'incoming_stocks_gate_qty_idx');
            $table->index('tanggal_faktur', 'incoming_stocks_tanggal_faktur_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('stock_mutations_created_at_idx');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_name_idx');
        });

        Schema::table('incoming_stocks', function (Blueprint $table) {
            $table->dropIndex('incoming_stocks_gate_qty_idx');
            $table->dropIndex('incoming_stocks_tanggal_faktur_idx');
        });
    }
};
