<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'apoteker', 'kasir') DEFAULT 'kasir'");
    }

    public function down(): void
    {
        // Irreversible enum column modification
    }
};

