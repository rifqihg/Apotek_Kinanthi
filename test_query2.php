<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$submission = App\Models\Control\CompoundingSubmission::where('id', 1)->first()->toArray();
echo json_encode($submission, JSON_PRETTY_PRINT);
