<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain 2: Master Data Tables
     * Pengaturan global, kategori produk, satuan ukur, supplier PBF, dan data pasien.
     */
    public function up(): void
    {
        // 1. Settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_ppn', 5, 2)->default(11.00);
            $table->decimal('default_margin', 5, 2)->default(25.00);
            $table->timestamps();
        });

        // 2. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('both'); // medicine, raw_material, both
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        // 3. Units
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->string('base_unit')->nullable();
            $table->timestamps();
        });

        // 4. Suppliers (PBF)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pbf');
            $table->string('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // 5. Patients
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('settings');
    }
};
