<?php

use App\Http\Controllers\Control\AdminController;
use App\Http\Controllers\Control\ApotekerController;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Unified dashboard redirect based on role
Route::get('/dashboard', function () {
    $user = auth()->user();
    return match ($user->role) {
        'admin' => redirect()->route('control.admin.dashboard'),
        'apoteker' => redirect()->route('control.apoteker.dashboard'),
        'kasir' => redirect()->route('control.kasir.queue'),
        default => redirect()->route('login')->with('error', 'Role tidak dikenal. Hubungi administrator.'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});



// ═══════════════════════════════════════════════════════════
// ADMIN ROUTES (The Brain)
// ═══════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin'])->prefix('control/admin')->name('control.admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');

    // Suppliers
    Route::get('/suppliers', [AdminController::class, 'suppliers'])->name('suppliers');
    Route::post('/suppliers', [AdminController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [AdminController::class, 'updateSupplier'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [AdminController::class, 'destroySupplier'])->name('suppliers.destroy');

    // ─── MASTER DATA ─────────────────────────────────────────
    Route::get('/medicines/faktur', [AdminController::class, 'medicinesFaktur'])->name('medicines.faktur');
    Route::get('/medicines/racikan', [AdminController::class, 'medicinesRacikan'])->name('medicines.racikan');
    Route::post('/medicines/transfer', [AdminController::class, 'transferToWarehouse'])->name('medicines.transfer');
    Route::post('/medicines/adjust', [AdminController::class, 'adjustStock'])->name('medicines.adjust');
    Route::post('/medicines/{medicine}/photo', [AdminController::class, 'updateMedicinePhoto'])->name('medicines.updatePhoto');
    Route::put('/medicines/{medicine}', [AdminController::class, 'updateMedicine'])->name('medicines.update');
    Route::get('/compounded-products', [AdminController::class, 'compoundedProducts'])->name('compounded-products');
    
    // Raw Materials managed by Admin
    Route::get('/raw-materials', [AdminController::class, 'rawMaterials'])->name('raw-materials');
    Route::post('/raw-materials', [AdminController::class, 'storeRawMaterial'])->name('raw-materials.store');
    Route::put('/raw-materials/{rawMaterial}', [AdminController::class, 'updateRawMaterial'])->name('raw-materials.update');
    Route::delete('/raw-materials/{rawMaterial}', [AdminController::class, 'destroyRawMaterial'])->name('raw-materials.destroy');
    

    // ─── FAKTUR MASUK (Single-Gate Workflow) ────────────────
    Route::get('/incoming-stocks', [AdminController::class, 'incomingStocks'])->name('incoming-stocks');
    Route::post('/incoming-stocks', [AdminController::class, 'storeIncomingStock'])->name('incoming-stocks.store');

    // ─── AUDIT & USER MANAGEMENT ─────────────────────────────
    Route::get('/audit-trails', [AdminController::class, 'auditTrails'])->name('audit-trails');
    Route::get('/audit-trails/export', [AdminController::class, 'exportAuditTrails'])->name('audit-trails.export');
    Route::get('/stock-logs', [AdminController::class, 'stockLogs'])->name('stock-logs');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    // ─── ADMIN STOCK OPNAME ──────────────────────────────────
    Route::get('/stock-opname', [AdminController::class, 'stockOpnameIndex'])->name('stock-opname.index');
    Route::post('/stock-opname', [AdminController::class, 'stockOpnameStore'])->name('stock-opname.store');
    Route::get('/stock-opname/{id}', [AdminController::class, 'stockOpnameShow'])->name('stock-opname.show');
    Route::post('/stock-opname/{id}/adjust', [AdminController::class, 'stockOpnameAdjust'])->name('stock-opname.adjust');
    // ─── PRICING ENGINE ──────────────────────────────────────
    Route::get('/pricing', [AdminController::class, 'pricingIndex'])->name('pricing.index');
    Route::post('/pricing/compounded/{product}/set-price', [AdminController::class, 'setPriceCompounded'])->name('pricing.compounded.set');
    Route::post('/pricing/medicine/{medicine}/set-price', [AdminController::class, 'setPriceMedicine'])->name('pricing.medicine.set');

    // ─── MANAJEMEN INVENTORI ─────────────────────────
    Route::get('/inventory', [AdminController::class, 'inventory'])->name('inventory.index');
    Route::get('/inventory/distribution', [AdminController::class, 'distribution'])->name('inventory.distribution');
    Route::post('/inventory/distribution', [AdminController::class, 'processDistribution'])->name('inventory.distribution.process');
    Route::patch('/inventory/{inventory}/location', [AdminController::class, 'updateLocation'])->name('inventory.location');
    Route::get('/inventory/{inventory}/label', [AdminController::class, 'printLabel'])->name('inventory.label');
    Route::post('/inventory/{inventory}/request-action', [AdminController::class, 'requestInventoryAction'])->name('inventory.request-action');

    Route::get('/mutations', [AdminController::class, 'mutations'])->name('mutation-log.index');
    Route::get('/returns', [AdminController::class, 'returns'])->name('returns.index');
    Route::post('/returns', [AdminController::class, 'storeReturn'])->name('returns.store');

});

Route::middleware(['auth', 'role:admin'])->prefix('control/admin')->name('control.admin.')->group(function () {
    // Laporan Penjualan (Accessible by Admin only)
    Route::get('/reports/sales', [\App\Http\Controllers\Control\ReportController::class, 'salesReport'])->name('reports.sales');
    Route::get('/reports/sales/export', [\App\Http\Controllers\Control\ReportController::class, 'exportSales'])->name('reports.sales.export');
    
    // Monitoring Transaksi & Stok Kasir
    Route::get('/reports/cashier-monitoring', [\App\Http\Controllers\Control\ReportController::class, 'cashierMonitoring'])->name('reports.cashier-monitoring');
});

// ═══════════════════════════════════════════════════════════
// APOTEKER ROUTES (The Lab)
// ═══════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:apoteker'])->prefix('control/apoteker')->name('control.apoteker.')->group(function () {
    Route::get('/dashboard', [ApotekerController::class, 'dashboard'])->name('dashboard');

    // Formulas
    Route::get('/formulas', [ApotekerController::class, 'formulas'])->name('formulas');
    Route::post('/formulas', [ApotekerController::class, 'storeFormula'])->name('formulas.store');

    // Submissions
    Route::get('/submissions', [ApotekerController::class, 'submissions'])->name('submissions');
    Route::post('/submissions', [ApotekerController::class, 'storeSubmission'])->name('submissions.store');
    Route::get('/submissions/{submission}', [ApotekerController::class, 'showSubmission'])->name('submissions.show');
    Route::post('/submissions/{submission}/cancel', [ApotekerController::class, 'cancelSubmission'])->name('submissions.cancel');

    // FEFO API
    Route::get('/fefo/{medicineId}', [ApotekerController::class, 'fefoSuggestion'])->name('fefo');

    // Stock Check
    Route::get('/stock-check', [ApotekerController::class, 'stockCheck'])->name('stock-check');

    // Katalog Bahan Baku (Read-only for Apoteker, CRUD by Admin)
    Route::get('/raw-materials', [ApotekerController::class, 'rawMaterials'])->name('raw-materials');

    // BUD Tracker
    Route::get('/bud-tracker', [ApotekerController::class, 'budTracker'])->name('bud-tracker');

    // Prescriptions
    Route::get('/prescriptions', [\App\Http\Controllers\Control\PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/create', [\App\Http\Controllers\Control\PrescriptionController::class, 'create'])->name('prescriptions.create');
    Route::post('/prescriptions', [\App\Http\Controllers\Control\PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{id}', [\App\Http\Controllers\Control\PrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::patch('/prescriptions/{id}/status', [\App\Http\Controllers\Control\PrescriptionController::class, 'updateStatus'])->name('prescriptions.updateStatus');
    Route::post('/patients/quick-store', [\App\Http\Controllers\Control\PrescriptionController::class, 'storePatient'])->name('patients.store');
    Route::post('/prescriptions/items/{id}/start-compounding', [\App\Http\Controllers\Control\PrescriptionController::class, 'startCompounding'])->name('prescriptions.items.start-compounding');
    Route::patch('/prescriptions/items/{id}/complete', [\App\Http\Controllers\Control\PrescriptionController::class, 'completeItem'])->name('prescriptions.items.complete');

    // Laporan & Log Audit

    Route::get('/stock-audit', [\App\Http\Controllers\Control\DisposalController::class, 'auditLogs'])->name('stock-audit');

    // Pemusnahan Stok
    Route::get('/disposals', [\App\Http\Controllers\Control\DisposalController::class, 'index'])->name('disposals');
    Route::post('/disposals', [\App\Http\Controllers\Control\DisposalController::class, 'store'])->name('disposals.store');
});

// ═══════════════════════════════════════════════════════════
// KASIR ROUTES (Kasir)
// ═══════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    // Empty for now (Legacy prefix, redirect to control)
});

Route::middleware(['auth', 'role:kasir'])->prefix('control/kasir')->name('control.kasir.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Control\SaleController::class, 'index'])->name('dashboard');
    Route::get('/queue', [\App\Http\Controllers\Control\SaleController::class, 'index'])->name('queue');
    Route::get('/checkout/{prescription}', [\App\Http\Controllers\Control\SaleController::class, 'show'])->name('checkout');
    Route::post('/sales', [\App\Http\Controllers\Control\SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}/receipt', [\App\Http\Controllers\Control\SaleController::class, 'receipt'])->name('sales.receipt');

    Route::get('/otc', [\App\Http\Controllers\Control\SaleController::class, 'otcIndex'])->name('otc.index');
    Route::post('/otc', [\App\Http\Controllers\Control\SaleController::class, 'otcStore'])->name('otc.store');

});

require __DIR__ . '/auth.php';
