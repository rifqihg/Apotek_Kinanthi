<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$details = App\Models\Control\CompoundingDetail::where('compounding_id', 1)->get()->toArray();
echo json_encode($details, JSON_PRETTY_PRINT);
