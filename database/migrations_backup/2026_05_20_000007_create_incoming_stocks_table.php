<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Incoming Stocks (Faktur Masuk)
     * Data faktur pembelian obat dari supplier/PBF.
     * Divalidasi oleh: Admin | Diverifikasi fisik oleh: Admin
     * FK: suppliers, medicines, users
     */
    public function up(): void
    {
        Schema::create('incoming_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('faktur_no');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date');
            $table->integer('qty_masuk')->default(0);
            $table->integer('unit_conversion_factor')->default(1);
            $table->string('satuan_input')->default('Box');
            $table->string('mid_unit')->nullable();
            $table->integer('mid_to_big_ratio')->default(1);
            $table->integer('small_to_mid_ratio')->default(1);
            $table->integer('qty_converted')->default(0);
            $table->decimal('harga_beli_satuan', 15, 2);
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->decimal('jumlah_harga', 15, 2)->default(0);
            $table->decimal('ppn_persen', 5, 2)->default(11.00);
            $table->decimal('margin_persen', 5, 2)->default(25.00);
            $table->decimal('harga_jual_saat_ini', 15, 2)->default(0);
            $table->decimal('harga_jual_akhir', 15, 2)->default(0);
            $table->string('status_price_alert')->default('normal');
            $table->string('status_approval')->default('pending');
            $table->enum('status', ['in_transit', 'received', 'returned'])->default('in_transit');
            $table->enum('status_gate', ['pending_admin_verify', 'pending_admin_final', 'active'])->default('pending_admin_verify');
            $table->text('admin_note')->nullable();
            $table->integer('qty_fisik_verify')->nullable();
            $table->text('verify_note')->nullable();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revised_at')->nullable();
            $table->boolean('is_retur')->default(false);
            $table->text('alasan_retur')->nullable();
            $table->date('tanggal_faktur')->nullable();
            $table->string('foto_faktur_path')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('supplier_id');
            $table->index('medicine_id');
            $table->index('status_approval');
            $table->index('status');
            $table->index('status_gate');
            $table->index('expired_date');
            $table->index('faktur_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_stocks');
    }
};
