<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain 6: Inventory Logistics, Stock Operations & Point of Sales (Kasir)
     * Faktur masuk, inventori gudang (FEFO batch), konfirmasi stok, stock opname,
     * retur supplier, mutasi/penyesuaian stok, kehilangan/kerusakan, transaksi kasir & item penjualan.
     */
    public function up(): void
    {
        // 1. Incoming Stocks (Faktur Masuk PBF)
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
            $table->decimal('harga_jual_akhir', 15, 2)->default(0);
            $table->string('status_approval')->default('pending');
            $table->enum('status_gate', ['pending_admin_verify', 'pending_admin_final', 'active'])->default('pending_admin_verify');
            $table->text('admin_note')->nullable();
            $table->integer('qty_fisik_verify')->nullable();
            $table->text('verify_note')->nullable();
            $table->date('tanggal_faktur')->nullable();
            $table->string('foto_faktur_path')->nullable();
            $table->timestamps();

            // Optimasi Index & Index Komposit Performa
            $table->index('supplier_id');
            $table->index('medicine_id');
            $table->index('status_approval');
            $table->index('status_gate');
            $table->index('expired_date');
            $table->index('faktur_no');
            $table->index(['medicine_id', 'expired_date'], 'incoming_stocks_med_expired_idx');
            $table->index(['medicine_id', 'status_gate'], 'incoming_stocks_med_status_gate_idx');
            $table->index(['status_gate', 'qty_converted'], 'incoming_stocks_gate_qty_idx');
            $table->index('tanggal_faktur', 'incoming_stocks_tanggal_faktur_idx');
        });

        // 2. Warehouse Inventories (Stok Gudang per Batch)
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
            $table->date('transferred_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['medicine_id', 'batch_no']);
            $table->index('expired_date');
            $table->index('status');
            $table->index('incoming_stock_id');
            $table->index(['location_type', 'location_id', 'status'], 'wi_location_status_idx');
            $table->index(['medicine_id', 'location_type', 'location_id', 'status', 'expired_date'], 'wi_fefo_lookup_idx');
        });

        // 3. Stock Opnames & Items
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->date('opname_date');
            $table->boolean('is_blind_count')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('status');
            $table->index('opname_date');
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->nullOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->nullOnDelete();
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->nullOnDelete();
            $table->foreignId('compounded_product_id')->nullable()->constrained('compounded_products')->nullOnDelete();
            $table->string('batch_no');
            $table->decimal('system_stock', 12, 4);
            $table->decimal('physical_stock', 12, 4)->nullable();
            $table->decimal('difference', 12, 4)->default(0);
            $table->text('reason')->nullable();
            $table->string('evidence_photo')->nullable();
            $table->timestamps();

            $table->index('stock_opname_id');
            $table->index('medicine_id');
            $table->index('raw_material_id');
        });

        // 4. Return Forms (Retur Obat ke Supplier)
        Schema::create('return_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_stock_id')->constrained('incoming_stocks')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->integer('qty_retur');
            $table->text('alasan_retur');
            $table->enum('status', ['draft', 'sent', 'acknowledged'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('incoming_stock_id');
            $table->index('supplier_id');
            $table->index('status');
        });

        // 5. Stock Operations: Mutations & Logs
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->onDelete('cascade');
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->onDelete('cascade');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_type')->nullable();
            $table->string('type');
            $table->decimal('quantity', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('warehouse_inventory_id');
            $table->index('raw_material_stock_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at', 'stock_mutations_created_at_idx');
            $table->index(['item_type', 'item_id', 'created_at'], 'sm_item_type_id_created_idx');
        });

        Schema::create('stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->onDelete('cascade');
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->onDelete('cascade');
            $table->string('action');
            $table->decimal('qty_before', 15, 2);
            $table->decimal('qty_after', 15, 2);
            $table->decimal('change_amount', 15, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->index('raw_material_id');
            $table->index('medicine_id');
            $table->index('user_id');
            $table->index('action');
        });

        // 6. Inventory Losses (Kerusakan / Kehilangan Stok)
        Schema::create('inventory_losses', function (Blueprint $table) {
            $table->id();
            $table->string('tipe_loss')->default('manual_disposal');
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->onDelete('cascade');
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->onDelete('cascade');
            $table->foreignId('compounded_product_id')->nullable()->constrained('compounded_products')->onDelete('cascade');
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->onDelete('cascade');
            $table->foreignId('raw_material_stock_id')->nullable()->constrained('raw_material_stocks')->onDelete('cascade');
            $table->decimal('qty_lost', 15, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index('tipe_loss');
            $table->index('raw_material_id');
            $table->index('medicine_id');
            $table->index('compounded_product_id');
            $table->index('status');
            $table->index('user_id');
        });

        // 7. Sales (Transaksi Penjualan Kasir)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->foreignId('kasir_id')->constrained('users');
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->enum('sale_type', ['resep', 'otc']);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('change_amount', 12, 2);
            $table->enum('payment_method', ['tunai', 'transfer', 'qris']);
            $table->enum('status', ['draft', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'sales_status_created_at_idx');
            $table->index(['kasir_id', 'created_at'], 'sales_kasir_created_idx');
        });

        // 8. Sale Items (Rincian Item Transaksi Penjualan)
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->enum('item_type', ['obat_jadi', 'racikan']);
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->foreignId('compounded_product_id')->nullable()->constrained('compounded_products')->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('item_type', 'sale_items_item_type_idx');
            $table->index(['item_type', 'medicine_id'], 'sale_items_item_type_med_id_idx');
            $table->index(['sale_id', 'medicine_id']);
            $table->index(['sale_id', 'item_type'], 'sale_items_sale_id_type_idx');
            $table->index(['item_type', 'compounded_product_id'], 'sale_items_type_cp_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('inventory_losses');
        Schema::dropIfExists('stock_logs');
        Schema::dropIfExists('stock_mutations');
        Schema::dropIfExists('return_forms');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('warehouse_inventories');
        Schema::dropIfExists('incoming_stocks');
    }
};
