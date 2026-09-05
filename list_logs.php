<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = App\Models\Control\StockMutation::whereDate('created_at', '>=', '2026-08-30')->get();
foreach($logs as $log) {
    echo $log->id . ' | ' . $log->type . ' | ' . $log->note . ' | ' . $log->quantity . "\n";
}
