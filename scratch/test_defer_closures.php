<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Control\Apoteker\ApotekerController;
use App\Http\Controllers\Control\Admin\AdminReportController;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Inertia\DeferProp;

echo "=== Testing Defer Closures in Controllers ===\n";

// 1. Mock Authentication
$user = User::first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

// 2. Test ApotekerController::dashboard()
$apoController = new ApotekerController();
$apoResponse = $apoController->dashboard();
assert($apoResponse instanceof InertiaResponse, "ApotekerController::dashboard must return InertiaResponse");

$apoProps = (fn() => $this->props)->call($apoResponse);

assert(array_key_exists('queueCount', $apoProps), "queueCount key missing");
assert(array_key_exists('activeSubmissions', $apoProps), "activeSubmissions key missing");
assert(array_key_exists('rawMaterialAlerts', $apoProps), "rawMaterialAlerts key missing");

assert($apoProps['activeSubmissions'] instanceof DeferProp, "activeSubmissions must be an Inertia\\Defer instance");
assert($apoProps['rawMaterialAlerts'] instanceof DeferProp, "rawMaterialAlerts must be an Inertia\\Defer instance");

echo "[OK] ApotekerController::dashboard correctly returns Inertia::defer closures.\n";

// 3. Test AdminReportController::salesReport()
$reportController = new AdminReportController();
$salesRequest = Request::create('/control/admin/reports/sales', 'GET', [
    'start_date' => now()->startOfMonth()->format('Y-m-d'),
    'end_date' => now()->format('Y-m-d')
]);

$salesResponse = $reportController->salesReport($salesRequest);
assert($salesResponse instanceof InertiaResponse, "salesReport must return InertiaResponse");

$salesProps = (fn() => $this->props)->call($salesResponse);
assert(array_key_exists('medicines', $salesProps), "medicines key missing");
assert(array_key_exists('summary', $salesProps), "summary key missing");
assert($salesProps['summary'] instanceof DeferProp, "summary must be an Inertia\\Defer instance");

// Invoke the closure in summary to verify keys
$summaryData = ($salesProps['summary'])();
assert(is_array($summaryData), "summary must evaluate to array");
assert(array_key_exists('total_items', $summaryData), "total_items missing in summary");
assert(array_key_exists('total_amount', $summaryData), "total_amount missing in summary");
assert(array_key_exists('total_modal', $summaryData), "total_modal missing in summary");
assert(array_key_exists('total_laba', $summaryData), "total_laba missing in summary");

echo "[OK] AdminReportController::salesReport correctly defers summary and returns complete KPIs.\n";

// 4. Test AdminReportController::cashierMonitoring()
$cashierRequest = Request::create('/control/admin/reports/cashier-monitoring', 'GET', [
    'start_date' => now()->startOfMonth()->format('Y-m-d'),
    'end_date' => now()->format('Y-m-d')
]);

$cashierResponse = $reportController->cashierMonitoring($cashierRequest);
assert($cashierResponse instanceof InertiaResponse, "cashierMonitoring must return InertiaResponse");

$cashierProps = (fn() => $this->props)->call($cashierResponse);
assert(array_key_exists('transactions', $cashierProps), "transactions key missing");
assert(array_key_exists('kpis', $cashierProps), "kpis key missing");
assert($cashierProps['kpis'] instanceof DeferProp, "kpis must be an Inertia\\Defer instance");

// Invoke the closure in kpis to verify keys
$kpisData = ($cashierProps['kpis'])();
assert(is_array($kpisData), "kpis must evaluate to array");
assert(array_key_exists('total_transactions', $kpisData), "total_transactions missing in kpis");
assert(array_key_exists('total_sales', $kpisData), "total_sales missing in kpis");
assert(array_key_exists('total_discount', $kpisData), "total_discount missing in kpis");
assert(array_key_exists('total_laba', $kpisData), "total_laba missing in kpis");

echo "[OK] AdminReportController::cashierMonitoring correctly defers kpis and returns complete KPIs.\n";

echo "=== ALL CHECKS PASSED PERFECTLY ===\n";
