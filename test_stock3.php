<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stock = \App\Models\Control\IncomingStock::find(1);
if ($stock) {
    $stock->unit_conversion_factor = 6;
    $stock->qty_converted = 30;
    $stock->qty_fisik_verify = 30;
    
    // mid_to_big_ratio cannot be null
    $stock->mid_to_big_ratio = 1; 
    
    $stock->save();
    
    $med = $stock->medicine;
    if ($med) {
        $med->recalculateStock();
        echo "Fixed Promag stock!\n";
    }
}
