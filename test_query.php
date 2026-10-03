<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = microtime(true);
$medicines = \App\Models\Control\Medicine::with('latestActiveIncomingStock')
    ->select(['id', 'nama_obat', 'satuan_stok', 'rasio_konversi', 'threshold_min', 'satuan_besar', 'satuan_menengah'])
    ->get();
echo 'Time: ' . (microtime(true) - $start) . 's, ' . count($medicines) . " rows\n";
