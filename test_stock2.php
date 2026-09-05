<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stocks = \App\Models\Control\IncomingStock::with('medicine')->get();
foreach ($stocks as $stock) {
    echo "ID: $stock->id, Obat: " . ($stock->medicine->nama_obat ?? 'N/A') . "\n";
    echo "  qty_masuk: $stock->qty_masuk, qty_converted: $stock->qty_converted\n";
    echo "  satuan_input: $stock->satuan_input, mid_unit: " . ($stock->mid_unit ?? 'NULL') . "\n";
    echo "  mid_to_big: " . ($stock->mid_to_big_ratio ?? 'NULL') . ", small_to_mid: " . ($stock->small_to_mid_ratio ?? 'NULL') . "\n";
    echo "  medicine base_unit: " . ($stock->medicine->satuan_stok ?? 'NULL') . "\n";
}
