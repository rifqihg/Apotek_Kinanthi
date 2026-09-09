<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')
                  ->constrained('prescriptions')
                  ->cascadeOnDelete();
            $table->enum('item_type', ['obat_jadi', 'racikan']);
            $table->foreignId('medicine_id')
                  ->nullable()
                  ->constrained('medicines')
                  ->nullOnDelete();
                  // filled when item_type = 'obat_jadi'
            $table->foreignId('compounded_product_id')
                  ->nullable()
                  ->constrained('compounded_products')
                  ->nullOnDelete();
                  // filled when item_type = 'racikan' AND
                  // product already exists in master data
            $table->string('custom_formula_name')->nullable();
                  // filled when item_type = 'racikan' AND
                  // it's a new formula not yet in master data
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            $table->text('usage_instructions')->nullable();
                // e.g. "3x1 setelah makan"
            $table->enum('status', [
                'pending',
                'dikerjakan',
                'selesai'
            ])->default('pending');
            $table->foreignId('compounding_id')
                  ->nullable()
                  ->constrained('compounding_submissions')
                  ->nullOnDelete();
                  // linked after apoteker processes compounding
                  // references compounding_submissions table
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
