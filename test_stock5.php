<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stock = \App\Models\Control\IncomingStock::find(3);
if ($stock) {
    $stock->unit_conversion_factor = 8;
    $stock->qty_converted = 72;
    $stock->qty_fisik_verify = 72;
    $stock->save();
    
    $med = $stock->medicine;
    if ($med) {
        $med->rasio_konversi = 8;
        $med->save();
        $med->recalculateStock();
        echo "Fixed Flucadex stock!\n";
    }
}
