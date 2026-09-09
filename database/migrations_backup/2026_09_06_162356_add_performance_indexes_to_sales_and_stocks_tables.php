<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Indeks komposit pada tabel sales untuk pencarian laporan & filter status + tanggal
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'sales_status_created_at_idx');
        });

        // 2. Indeks pada tabel sale_items untuk filter jenis produk (obat_jadi / racikan)
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index('item_type', 'sale_items_item_type_idx');
            $table->index(['item_type', 'medicine_id'], 'sale_items_item_type_med_id_idx');
        });

        // 3. Indeks komposit pada tabel incoming_stocks untuk subquery tanggal kedaluwarsa & verifikasi stok
        Schema::table('incoming_stocks', function (Blueprint $table) {
            $table->index(['medicine_id', 'expired_date'], 'incoming_stocks_med_expired_idx');
            $table->index(['medicine_id', 'status_gate'], 'incoming_stocks_med_status_gate_idx');
        });

        // 4. Indeks pada tabel prescriptions untuk filter antrian status resep
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index('status', 'prescriptions_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_status_created_at_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_item_type_med_id_idx');
            $table->dropIndex('sale_items_item_type_idx');
        });

        Schema::table('incoming_stocks', function (Blueprint $table) {
            $table->dropIndex('incoming_stocks_med_expired_idx');
            $table->dropIndex('incoming_stocks_med_status_gate_idx');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('prescriptions_status_idx');
        });
    }
};
