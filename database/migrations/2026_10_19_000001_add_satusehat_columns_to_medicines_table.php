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
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->index()->after('kode_obat');
            $table->string('kfa_code', 100)->nullable()->index()->after('barcode');
            $table->string('nie', 100)->nullable()->index()->after('kfa_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['kfa_code']);
            $table->dropIndex(['nie']);
            $table->dropColumn(['barcode', 'kfa_code', 'nie']);
        });
    }
};
