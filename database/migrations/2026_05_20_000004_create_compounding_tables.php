<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain 5: Compounding Lab & Production (Laboratorium Racikan)
     * Formula racikan, pengajuan racikan (termasuk relasi ke resep), rincian bahan, label etiket,
     * produk jadi hasil racikan (beserta status kalkulasi harga), dan pencatatan deduksi stok.
     */
    public function up(): void
    {
        // 1. Compounding Formulas (Master Resep/Formula)
        Schema::create('compounding_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_resep');
            $table->string('kategori_sediaan')->nullable();
            $table->decimal('target_qty', 15, 2)->nullable();
            $table->string('target_unit')->nullable();
            $table->string('signa_default')->nullable();
            $table->integer('bud_days')->default(14);
            $table->text('instruksi_racik')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // 1.5. Compounding Formula Ingredients (BOM)
        Schema::create('formula_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('compounding_formulas')->cascadeOnDelete();
            $table->enum('source_type', ['medicine', 'raw_material']);
            $table->unsignedBigInteger('source_id');
            $table->decimal('qty_required', 15, 2);
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        // 2. Compounding Submissions (Pengajuan Produksi Racikan)
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
            $table->decimal('jasa_racik', 15, 2)->default(0);
            $table->decimal('biaya_kemasan', 15, 2)->default(0);
            $table->decimal('total_hpp_racikan', 15, 2)->default(0);
            $table->decimal('harga_jual_racikan', 15, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('pharmacist_id');
            $table->index('formula_id');
            $table->index('status');
            $table->index('approved_by');
            $table->index('prescription_id');
        });

        // 3. Compounding Details (Rincian Bahan Racikan)
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

            $table->index('compounding_id');
            $table->index('medicine_id');
            $table->index('raw_material_id');
            $table->index('stock_batch_id');
            $table->index('source_type');
        });

        // 4. Compounding Labels (Etiket & Aturan Pakai)
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

            $table->index('compounding_id');
        });

        // 5. Compounded Products (Produk Jadi Hasil Racikan)
        Schema::create('compounded_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_submission_id')->constrained('compounding_submissions')->onDelete('cascade');
            $table->string('nama_produk');
            $table->string('batch_no');
            $table->date('tanggal_racik');
            $table->date('bud_date');
            $table->decimal('qty_hasil', 12, 2);
            $table->decimal('actual_yield', 15, 2)->nullable();
            $table->decimal('production_loss', 15, 2)->default(0);
            $table->string('satuan')->default('Gram');
            $table->decimal('base_cost', 15, 2)->default(0);
            $table->decimal('hpp_per_unit', 12, 2)->default(0);
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->string('pricing_status')->default('pending_price');
            $table->string('status')->default('pending_physical_check');
            $table->boolean('is_frozen')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('compounding_submission_id');
            $table->index('status');
            $table->index('bud_date');
            $table->index('created_by');
            $table->index('pricing_status');
        });

        // 6. Compounding Stock Deductions (Pencatatan Deduksi Stok)
        Schema::create('compounding_stock_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compounding_detail_id')->constrained('compounding_details')->onDelete('cascade');
            $table->unsignedBigInteger('stock_source_id');
            $table->string('stock_source_type');
            $table->decimal('qty_deducted', 15, 2);
            $table->timestamps();

            $table->index('compounding_detail_id');
            $table->index(['stock_source_id', 'stock_source_type'], 'csd_stock_source_idx');
        });

        // 7. Relasi balik item resep ke produk racikan & pengajuan
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->foreignId('compounded_product_id')->nullable()->after('medicine_id')->constrained('compounded_products')->nullOnDelete();
            $table->foreignId('compounding_id')->nullable()->after('compounded_product_id')->constrained('compounding_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropForeign(['compounding_id']);
            $table->dropColumn('compounding_id');
            $table->dropForeign(['compounded_product_id']);
            $table->dropColumn('compounded_product_id');
        });

        Schema::dropIfExists('compounding_stock_deductions');
        Schema::dropIfExists('compounded_products');
        Schema::dropIfExists('compounding_labels');
        Schema::dropIfExists('compounding_details');
        Schema::dropIfExists('compounding_submissions');
        Schema::dropIfExists('formula_ingredients');
        Schema::dropIfExists('compounding_formulas');
    }
};
