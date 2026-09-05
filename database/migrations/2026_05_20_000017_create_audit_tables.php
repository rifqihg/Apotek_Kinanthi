<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squashed Migration: Audit Trails + Verification Audit Logs
     * Log aktivitas sistem dan log verifikasi dual-gate bahan baku.
     * FK: users
     */
    public function up(): void
    {
        // ── Tabel Audit Trail Umum ──
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('performed_at');
        });

        // ── Tabel Log Verifikasi Dual-Gate (Bahan Baku) ──
        Schema::create('verification_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('gate'); // gate_1, gate_2
            $table->string('action'); // approve, reject
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->decimal('expected_qty', 15, 2)->nullable();
            $table->decimal('actual_qty', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // === OPTIMASI INDEX ===
            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('gate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_audit_logs');
        Schema::dropIfExists('audit_trails');
    }
};
