<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Compounding Submissions, Details, Labels
     * Pengajuan racikan oleh Apoteker, detail bahan, dan label/etiket.
     * FK: users, compounding_formulas, medicines, raw_materials, raw_material_stocks
     */
    public function up(): void
    {
        // ── Tabel Pengajuan Racikan ──
        Schema::create('compounding_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pharmacist_id')->nullable()->constrained('users');
            $table->foreignId('formula_id')->nullable()->constrained('compounding_formulas')->nullOnDelete();
            $table->string('nama_racikan');
            $table->decimal('target_quantity', 15, 2)->nullable();
            $table->string('target_unit')->nullable();
            $table->integer('expected_yield')->nullable();
            $table->decimal('total_net_mass', 15, 2)->nullable();
            $table->decimal('unit_weight_target', 12, 2)->nullable();
            $table->decimal('filler_qty', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('apoteker_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->decimal('total_hpp_bahan', 15, 2)->default(0);
            $table->decimal('biaya_tuslah', 15, 2)->default(0);
            $table->decimal('total_hpp_racikan', 15, 2)->default(0);
            $table->decimal('harga_jual_racikan', 15, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('user_id');
            $table->index('pharmacist_id');
            $table->index('formula_id');
            $table->index('status');
            $table->index('approved_by');
        });

        // ── Tabel Detail Bahan Racikan ──
        Schema::create('compounding_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_id')->constrained('compounding_submissions')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->nullOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('raw_material_stocks');
            $table->enum('source_type', ['medicine', 'raw_material'])->default('medicine');
            $table->decimal('volume_to_mass_factor', 8, 2)->default(1.0000);
            $table->decimal('qty_needed', 12, 2);
            $table->decimal('net_quantity', 15, 2)->nullable();
            $table->decimal('wastage_percentage', 5, 2)->default(0);
            $table->decimal('qty_total_deducted', 15, 2)->default(0);
            $table->string('suggested_batch_no')->nullable();
            $table->date('suggested_expired_date')->nullable();
            $table->decimal('harga_satuan_saat_pakai', 15, 2)->default(0);
            $table->decimal('subtotal_hpp', 15, 2)->default(0);
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('compounding_id');
            $table->index('medicine_id');
            $table->index('raw_material_id');
            $table->index('stock_batch_id');
            $table->index('source_type');
        });

        // ── Tabel Label/Etiket Racikan ──
        Schema::create('compounding_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_id')->constrained('compounding_submissions')->cascadeOnDelete();
            $table->date('tanggal_racik')->nullable();
            $table->date('beyond_use_date')->nullable();
            $table->integer('bud_hari')->default(30);
            $table->date('tanggal_kadaluarsa_bud')->nullable();
            $table->string('aturan_pakai');
            $table->text('keterangan')->nullable();
            $table->string('nama_pasien')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index('compounding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compounding_labels');
        Schema::dropIfExists('compounding_details');
        Schema::dropIfExists('compounding_submissions');
    }
};
