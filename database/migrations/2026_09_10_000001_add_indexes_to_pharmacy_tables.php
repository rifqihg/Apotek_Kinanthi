<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pilar 2 Optimasi: Penambahan indeks & composite index untuk optimasi query backend.
     */
    public function up(): void
    {
        // 1. Indeks biasa pada kolom 'role' di tabel 'users'
        // Mengeliminasi Full Table Scan saat filter role user (misal: kasir monitoring / laporan)
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        // 2. Composite Index pada kolom 'sale_id' dan 'medicine_id' di tabel 'sale_items'
        // Mempercepat join, filter, dan agregasi kuantitas item per transaksi penjualan
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['sale_id', 'medicine_id']);
        });

        // 3. Composite Index pada tabel 'raw_material_stocks' untuk optimasi algoritma FEFO racikan
        // Menggabungkan: raw_material_id, status, stock_on_hand, dan expired_date
        Schema::table('raw_material_stocks', function (Blueprint $table) {
            $table->index(
                ['raw_material_id', 'status', 'stock_on_hand', 'expired_date'],
                'rm_stocks_fefo_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_material_stocks', function (Blueprint $table) {
            $table->dropIndex('rm_stocks_fefo_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'medicine_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
