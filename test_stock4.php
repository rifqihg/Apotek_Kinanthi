<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$med = \App\Models\Control\Medicine::find(2);
echo "Xon-ce medicine rasio_konversi: " . $med->rasio_konversi . "\n";
