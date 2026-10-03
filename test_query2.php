<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = microtime(true);
$medicines = \App\Models\Control\Medicine::with('latestActiveIncomingStock')
    ->select(['id', 'nama_obat', 'satuan_stok', 'rasio_konversi', 'threshold_min', 'satuan_besar', 'satuan_menengah'])
    ->orderBy('nama_obat')
    ->get()
    ->map(function ($m) {
        $lastApproved = $m->latestActiveIncomingStock;
        $m->current_price = $lastApproved ? $lastApproved->harga_jual_akhir : 0;
        return $m;
    });
    
echo 'Time after map: ' . (microtime(true) - $start) . 's, ' . count($medicines) . " rows\n";

$json = json_encode($medicines);
echo 'JSON length: ' . strlen($json) . "\n";
echo "Done.\n";
