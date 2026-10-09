<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain 4: Prescriptions (Pelayanan Resep Dokter)
     * Resep dokter, data dokter perujuk, dan item-item obat resep.
     */
    public function up(): void
    {
        // 1. Prescriptions (Resep)
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->decimal('patient_weight_kg', 5, 2)->nullable();
            $table->unsignedTinyInteger('patient_age_years')->nullable();
            $table->string('doctor_name', 100);
            $table->string('doctor_sip', 60)->nullable();
            $table->string('clinic_name', 100)->nullable();
            $table->date('prescription_date');
            $table->enum('status', [
                'baru',
                'diproses',
                'siap_ambil',
                'selesai',
                'batal'
            ])->default('baru');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('screening_notes')->nullable();
            $table->timestamps();

            $table->index('status', 'prescriptions_status_idx');
            $table->index(['status', 'created_at'], 'prescriptions_status_created_idx');
        });

        // 2. Prescription Items (Rincian Item Resep)
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->enum('item_type', ['obat_jadi', 'racikan']);
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('custom_formula_name')->nullable();
            $table->text('custom_formula_notes')->nullable();
            $table->string('item_name')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->string('dosage_instruction')->nullable();
            $table->string('usage_instructions')->nullable();
            $table->string('item_notes')->nullable();
            $table->boolean('is_iter')->default(false);
            $table->integer('iter_count')->default(0);
            $table->enum('status', ['pending', 'dikerjakan', 'selesai'])->default('pending');
            $table->timestamps();

            $table->index('item_type');
            $table->index('medicine_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
