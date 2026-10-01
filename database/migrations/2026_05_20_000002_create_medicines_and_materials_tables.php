<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain 3: Medicines & Raw Materials Catalog
     * Katalog master obat jadi (beserta skema harga jual bertingkat) dan bahan baku laboratorium racikan.
     */
    public function up(): void
    {
        // 1. Medicines (Master Obat Jadi)
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nama_obat');
            $table->string('kategori')->nullable();
            $table->enum('classification', [
                'narkotika',
                'psikotropika',
                'prekursor',
                'obat_keras',
                'bebas_terbatas',
                'bebas'
            ])->default('obat_keras');
            $table->string('kode_obat')->unique()->nullable();
            $table->string('satuan_stok', 50)->default('Tablet');
            $table->string('satuan_besar')->nullable();
            $table->string('satuan_menengah')->nullable();
            $table->integer('rasio_konversi')->default(1);
            $table->string('base_unit')->nullable();
            $table->string('large_unit')->nullable();
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->decimal('stok_total', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->integer('threshold_min')->default(10);
            $table->text('deskripsi')->nullable();
            $table->decimal('selling_price_large', 12, 2)->nullable();
            $table->decimal('selling_price_mid', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable(); // Base Unit Selling Price
            $table->string('foto_obat')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('status');
            $table->index('nama_obat');
            $table->index('classification');
        });

        // 2. Raw Materials (Katalog Bahan Baku)
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nama_bahan');
            $table->string('bentuk_sediaan')->nullable();
            $table->string('kode_bahan')->unique()->nullable();
            
            // Parameter Klinis
            $table->string('golongan_bahan', 100)->nullable();
            $table->string('suhu_penyimpanan', 100)->nullable();
            
            // Mesin Konversi Satuan
            $table->string('satuan_pembelian', 50)->nullable(); // e.g. Kg, Liter
            $table->string('satuan_dasar_penggunaan', 50)->nullable(); // e.g. mg, mL
            $table->decimal('rasio_konversi', 15, 4)->default(1); // e.g. 1 Kg = 1000000 mg
            
            $table->decimal('stok_total', 15, 4)->default(0); // Disimpan dalam satuan_dasar_penggunaan
            $table->decimal('density_factor', 8, 4)->default(1.0000);
            $table->decimal('harga_beli_satuan', 15, 2)->default(0);
            $table->decimal('threshold_min', 12, 4)->default(0);
            $table->text('deskripsi')->nullable();
            $table->string('status')->default('pending_registration');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('admin_gate_1_at')->nullable();
            $table->timestamp('admin_gate_2_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('status');
            $table->index('creator_id');
            $table->index('verified_by');
        });

        // 3. Raw Material Stocks (Batch Fisik Bahan Baku)
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date')->nullable();
            $table->decimal('expected_quantity', 15, 2)->nullable();
            $table->decimal('actual_quantity', 15, 2)->nullable();
            $table->decimal('stock_on_hand', 12, 4)->default(0);
            $table->string('status')->default('pending_physical_check');
            $table->boolean('is_frozen')->default(false);
            $table->string('shelf_location')->nullable();
            $table->string('rack_location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['raw_material_id', 'batch_no']);
            $table->index('expired_date');
            $table->index('status');
            $table->index(
                ['raw_material_id', 'status', 'stock_on_hand', 'expired_date'],
                'rm_stocks_fefo_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_stocks');
        Schema::dropIfExists('raw_materials');
        Schema::dropIfExists('medicines');
    }
};
