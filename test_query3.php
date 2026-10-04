<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('compounding_submissions', function (Blueprint $table) {
    if (!Schema::hasColumn('compounding_submissions', 'jasa_racik')) {
        $table->decimal('jasa_racik', 15, 2)->default(0)->after('biaya_tuslah');
    }
    if (!Schema::hasColumn('compounding_submissions', 'biaya_kemasan')) {
        $table->decimal('biaya_kemasan', 15, 2)->default(0)->after('jasa_racik');
    }
});
echo "Columns added successfully!\n";
