<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sub = App\Models\Control\CompoundingSubmission::find(1);
if ($sub) {
    // Recalculate total_hpp_bahan from details
    $totalHpp = App\Models\Control\CompoundingDetail::where('compounding_id', 1)->sum('subtotal_hpp');
    $sub->total_hpp_bahan = $totalHpp;
    $sub->total_hpp_racikan = $totalHpp + $sub->biaya_tuslah;
    $sub->save();
    
    echo "Fixed submission ID 1 total_hpp_bahan to: " . $totalHpp . "\n";
}
