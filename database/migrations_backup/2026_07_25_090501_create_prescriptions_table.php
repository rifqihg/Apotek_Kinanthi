<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
                // Format: RX-{YYYYMMDD}-{4digit}, e.g. RX-20250715-0001
                // Auto-generated in model boot(), NOT here
            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->restrictOnDelete();
            $table->string('doctor_name', 100);
            $table->string('clinic_name', 100)->nullable();
            $table->date('prescription_date');
            $table->enum('status', [
                'baru',
                'diproses',
                'siap_ambil',
                'selesai',
                'batal'
            ])->default('baru');
            $table->foreignId('handled_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
