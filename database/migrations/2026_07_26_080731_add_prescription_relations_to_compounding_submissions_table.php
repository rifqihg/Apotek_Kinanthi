<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('compounding_submissions', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('approved_by')->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->after('prescription_id')->constrained('prescription_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compounding_submissions', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropForeign(['prescription_item_id']);
            $table->dropColumn(['prescription_id', 'prescription_item_id']);
        });
    }
};
