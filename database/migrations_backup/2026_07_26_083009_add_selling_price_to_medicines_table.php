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
            $table->decimal('selling_price_large', 12, 2)->nullable()->after('deskripsi');
            $table->decimal('selling_price_mid', 12, 2)->nullable()->after('selling_price_large');
            $table->decimal('selling_price', 12, 2)->nullable()->after('selling_price_mid'); // Base Unit Price
            $table->string('satuan_menengah')->nullable()->after('satuan_besar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['selling_price_large', 'selling_price_mid', 'selling_price', 'satuan_menengah']);
        });
    }
};
