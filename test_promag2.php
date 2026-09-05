<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s = App\Models\Control\IncomingStock::where("medicine_id", 9)->latest()->first();
echo json_encode($s);
