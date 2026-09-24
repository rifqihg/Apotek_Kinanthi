<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Control\Admin\{AdminCashierExportController, AdminController, AdminInventoryController, AdminMedicineController, AdminPricingController, AdminProcurementController, AdminReportController, AdminSalesExportController, AdminStockOpnameController, AdminUserController};
use App\Http\Controllers\Control\Apoteker\{ApotekerController, ApotekerCompoundingController, ApotekerFormulaController, ApotekerInventoryController, ApotekerPrescriptionController};
use App\Http\Controllers\Control\Kasir\KasirSaleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]));

// Unified dashboard redirect based on role
Route::get('/dashboard', fn () => match (auth()->user()?->role) {
    'admin' => redirect()->route('control.admin.dashboard'),
    'apoteker' => redirect()->route('control.apoteker.dashboard'),
    'kasir' => redirect()->route('control.kasir.queue'),
    default => redirect()->route('login')->with('error', 'Role tidak dikenal. Hubungi administrator.'),
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
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
    Route::get('/suppliers', [AdminProcurementController::class, 'suppliers'])->name('suppliers');
    Route::post('/suppliers', [AdminProcurementController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [AdminProcurementController::class, 'updateSupplier'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [AdminProcurementController::class, 'destroySupplier'])->name('suppliers.destroy');

    // ─── MASTER DATA ─────────────────────────────────────────
    Route::get('/medicines/faktur', [AdminMedicineController::class, 'medicinesFaktur'])->name('medicines.faktur');
    Route::post('/medicines/{medicine}/photo', [AdminMedicineController::class, 'updateMedicinePhoto'])->name('medicines.updatePhoto');
    Route::put('/medicines/{medicine}', [AdminMedicineController::class, 'updateMedicine'])->name('medicines.update');
    Route::get('/compounded-products', [AdminMedicineController::class, 'compoundedProducts'])->name('compounded-products');

    // ─── FAKTUR MASUK (Single-Gate Workflow) ─────────────────
    Route::get('/incoming-stocks', [AdminProcurementController::class, 'incomingStocks'])->name('incoming-stocks');
    Route::post('/incoming-stocks', [AdminProcurementController::class, 'storeIncomingStock'])->name('incoming-stocks.store');

    // ─── AUDIT & USER MANAGEMENT ──────────────────────────────
    Route::get('/audit-trails', [AdminUserController::class, 'auditTrails'])->name('audit-trails');
    Route::get('/stock-logs', [AdminUserController::class, 'stockLogs'])->name('stock-logs');
    Route::get('/users', [AdminUserController::class, 'users'])->name('users');
    Route::post('/users', [AdminUserController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroyUser'])->name('users.destroy');

    // ─── ADMIN STOCK OPNAME ───────────────────────────────────
    Route::get('/stock-opname', [AdminStockOpnameController::class, 'stockOpnameIndex'])->name('stock-opname.index');
    Route::post('/stock-opname', [AdminStockOpnameController::class, 'stockOpnameStore'])->name('stock-opname.store');
    Route::get('/stock-opname/{id}', [AdminStockOpnameController::class, 'stockOpnameShow'])->name('stock-opname.show');
    Route::post('/stock-opname/{id}/adjust', [AdminStockOpnameController::class, 'stockOpnameAdjust'])->name('stock-opname.adjust');

    // ─── PRICING ENGINE ───────────────────────────────────────
    Route::get('/pricing', [AdminPricingController::class, 'pricingIndex'])->name('pricing.index');
    Route::post('/pricing/compounded/{product}/set-price', [AdminPricingController::class, 'setPriceCompounded'])->name('pricing.compounded.set');
    Route::post('/pricing/medicine/{medicine}/set-price', [AdminPricingController::class, 'setPriceMedicine'])->name('pricing.medicine.set');

    // ─── MANAJEMEN INVENTORI ──────────────────────────────────
    Route::get('/inventory/distribution', [AdminInventoryController::class, 'distribution'])->name('inventory.distribution');
    Route::post('/inventory/distribution', [AdminInventoryController::class, 'processDistribution'])->name('inventory.distribution.process');
    Route::get('/returns', [AdminInventoryController::class, 'returns'])->name('returns.index');
    Route::post('/returns', [AdminInventoryController::class, 'storeReturn'])->name('returns.store');

    // ─── LAPORAN & MONITORING ─────────────────────────────────
    Route::get('/reports/sales', [AdminReportController::class, 'salesReport'])->name('reports.sales');
    Route::get('/reports/sales/export', [AdminSalesExportController::class, 'exportSales'])->name('reports.sales.export');
    Route::get('/reports/sales/export-pdf', [AdminSalesExportController::class, 'exportSalesPdf'])->name('reports.sales.export-pdf');
    Route::get('/reports/cashier-monitoring', [AdminReportController::class, 'cashierMonitoring'])->name('reports.cashier-monitoring');
    Route::get('/reports/cashier-monitoring/export', [AdminCashierExportController::class, 'exportCashierMonitoring'])->name('reports.cashier-monitoring.export');
    Route::get('/reports/cashier-monitoring/export-pdf', [AdminCashierExportController::class, 'exportCashierMonitoringPdf'])->name('reports.cashier-monitoring.export-pdf');
});


// Shared Master Bahan Baku (Accessible by Admin & Apoteker)
Route::middleware(['auth', 'role:admin,apoteker'])->prefix('control/admin')->name('control.admin.')->group(function () {
    Route::get('/raw-materials', [AdminMedicineController::class, 'rawMaterials'])->name('raw-materials');
    Route::post('/raw-materials', [AdminMedicineController::class, 'storeRawMaterial'])->name('raw-materials.store');
    Route::put('/raw-materials/{rawMaterial}', [AdminMedicineController::class, 'updateRawMaterial'])->name('raw-materials.update');
    Route::delete('/raw-materials/{rawMaterial}', [AdminMedicineController::class, 'destroyRawMaterial'])->name('raw-materials.destroy');

    // Secure Faktur File Access (Admin & Apoteker)
    Route::get('/incoming-stocks/{incomingStock}/faktur', [AdminProcurementController::class, 'showFakturFile'])->name('incoming-stocks.faktur-file');
});

// ═══════════════════════════════════════════════════════════
// APOTEKER ROUTES (The Lab)
// ═══════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:apoteker'])->prefix('control/apoteker')->name('control.apoteker.')->group(function () {
    Route::get('/dashboard', [ApotekerController::class, 'dashboard'])->name('dashboard');

    // Formulas
    Route::get('/formulas', [ApotekerFormulaController::class, 'formulas'])->name('formulas');
    Route::post('/formulas', [ApotekerFormulaController::class, 'storeFormula'])->name('formulas.store');

    // Submissions
    Route::get('/submissions', [ApotekerCompoundingController::class, 'submissions'])->name('submissions');
    Route::post('/submissions', [ApotekerCompoundingController::class, 'storeSubmission'])->name('submissions.store');
    Route::get('/submissions/{submission}', [ApotekerCompoundingController::class, 'showSubmission'])->name('submissions.show');
    Route::post('/submissions/{submission}/cancel', [ApotekerCompoundingController::class, 'cancelSubmission'])->name('submissions.cancel');

    // Thermal Label
    Route::get('/compounding/{id}/label', [ApotekerCompoundingController::class, 'printLabel'])->name('compounding.label');

    // FEFO API
    Route::get('/fefo/{medicineId}', [ApotekerCompoundingController::class, 'fefoSuggestion'])->name('fefo');

    // Stock Check
    Route::get('/stock-check', [ApotekerInventoryController::class, 'stockCheck'])->name('stock-check');


    // BUD Tracker
    Route::get('/bud-tracker', [ApotekerCompoundingController::class, 'budTracker'])->name('bud-tracker');

    // Prescriptions
    Route::get('/prescriptions', [ApotekerPrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/create', [ApotekerPrescriptionController::class, 'create'])->name('prescriptions.create');
    Route::post('/prescriptions', [ApotekerPrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{id}', [ApotekerPrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::patch('/prescriptions/{id}/status', [ApotekerPrescriptionController::class, 'updateStatus'])->name('prescriptions.updateStatus');
    Route::post('/patients/quick-store', [ApotekerPrescriptionController::class, 'storePatient'])->name('patients.store');
    Route::post('/prescriptions/items/{id}/start-compounding', [ApotekerPrescriptionController::class, 'startCompounding'])->name('prescriptions.items.start-compounding');
    Route::patch('/prescriptions/items/{id}/complete', [ApotekerPrescriptionController::class, 'completeItem'])->name('prescriptions.items.complete');

    // Laporan & Log Audit
    Route::get('/stock-audit', [ApotekerInventoryController::class, 'auditLogs'])->name('stock-audit');
    Route::get('/reports/sipnap', [ApotekerInventoryController::class, 'sipnapReport'])->name('reports.sipnap');
    Route::get('/reports/sipnap/export-pdf', [ApotekerInventoryController::class, 'exportSipnapPdf'])->name('reports.sipnap.export-pdf');
});

// ═══════════════════════════════════════════════════════════
// KASIR ROUTES (Kasir)
// ═══════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:kasir'])->prefix('control/kasir')->name('control.kasir.')->group(function () {
    Route::get('/', [KasirSaleController::class, 'index'])->name('dashboard');
    Route::get('/queue', [KasirSaleController::class, 'index'])->name('queue');
    Route::get('/checkout/{prescription}', [KasirSaleController::class, 'show'])->name('checkout');
    Route::post('/sales', [KasirSaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}/receipt', [KasirSaleController::class, 'receipt'])->name('sales.receipt');
    Route::post('/sales/{sale}/void', [KasirSaleController::class, 'voidSale'])->middleware('throttle:5,1')->name('sales.void');

    Route::get('/otc', [KasirSaleController::class, 'otcIndex'])->name('otc.index');
    Route::post('/otc', [KasirSaleController::class, 'otcStore'])->name('otc.store');
});

require __DIR__ . '/auth.php';
