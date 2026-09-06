<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\Setting;
use App\Models\Control\WarehouseInventory;
use App\Models\Prescription;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Admin Dashboard - The Brain of the system.
     */
    public function dashboard(Request $request)
    {
        $settings = Setting::current();
        $totalMedicines = Medicine::count();
        $lowStockCount = Medicine::belowThreshold()->count();
        $fefoAlertCount = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->expiringSoon(90)->count();
        $fefoNearestBatch = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->orderBy('expired_date', 'asc')->first();

        $pendingCompounding = CompoundingSubmission::pending()->count();

        $todaySalesCount = Sale::where('status', 'paid')->whereDate('created_at', today())->count();
        $todaySalesTotal = Sale::where('status', 'paid')->whereDate('created_at', today())->sum('total_amount');
        $activePrescriptionsCount = Prescription::whereIn('status', ['diproses', 'siap_ambil'])->count();
        $pendingPricingCount = CompoundedProduct::where('pricing_status', 'pending_price')->count();

        $lowStockPercentage = ($totalMedicines > 0) ? round(($lowStockCount / $totalMedicines) * 100) : 0;

        $filter = $request->query('filter', 'abjad');
        $query = Medicine::with(['incomingStocks' => function($q) {
            $q->orderBy('expired_date', 'asc');
        }]);
        
        if ($filter === 'exp') {
            $query->orderBy(
                IncomingStock::select('expired_date')
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->whereNotNull('expired_date')
                    ->orderBy('expired_date', 'asc')
                    ->limit(1),
                'asc'
            );
        } elseif ($filter === 'qty') {
            $query->orderBy('stok_total', 'asc');
        } else {
            $query->orderBy('nama_obat', 'asc');
        }
        
        $paginator = $query->paginate(10)->withQueryString();
        
        $tableStocks = $paginator->getCollection()->map(function ($medicine) {
            $incoming = $medicine->incomingStocks->first(); // Gets the one with earliest expiry
            return [
                'id' => $medicine->id,
                'nama_obat' => $medicine->nama_obat,
                'kategori' => $medicine->kategori ?: '-',
                'nomor_batch' => $incoming ? $incoming->batch_no : '-',
                'stok_fisik' => $medicine->stok_total,
                
                // Required for Katalog Faktur QTY formatting logic
                'qty_masuk' => $incoming ? $incoming->qty_masuk : 0,
                'qty_converted' => $incoming ? $incoming->qty_converted : 0,
                'satuan_input' => $incoming ? $incoming->satuan_input : null,
                'mid_unit' => $incoming ? $incoming->mid_unit : null,
                'unit_conversion_factor' => $incoming ? $incoming->unit_conversion_factor : 1,
                'mid_to_big_ratio' => $incoming ? $incoming->mid_to_big_ratio : 0,
                'small_to_mid_ratio' => $incoming ? $incoming->small_to_mid_ratio : 0,
                'satuan_stok' => $medicine->satuan_stok ?? 'Unit',
                
                'batas_aman' => $medicine->threshold_min,
                'expired_date' => $incoming && $incoming->expired_date ? Carbon::parse($incoming->expired_date)->format('d M Y') : '-',
                'is_expiring_soon' => $incoming && $incoming->expired_date && Carbon::parse($incoming->expired_date)->diffInDays(now()) <= 90
            ];
        });
        
        $paginator->setCollection($tableStocks);

        return Inertia::render('Control/Admin/Dashboard', [
            'stats' => [
                'total_obat' => $totalMedicines,
                'low_stock_count' => $lowStockCount,
                'low_stock_percentage' => $lowStockPercentage,
                'fefo_alert_count' => $fefoAlertCount,
                'fefo_nearest_date' => $fefoNearestBatch && $fefoNearestBatch->expired_date ? Carbon::parse($fefoNearestBatch->expired_date)->format('d M Y') : null,
                'pending_compounding' => $pendingCompounding,
                'today_sales_count' => $todaySalesCount,
                'today_sales_total' => $todaySalesTotal,
                'active_prescriptions_count' => $activePrescriptionsCount,
                'pending_pricing_count' => $pendingPricingCount,
            ],
            'tableStocks' => $paginator,
            'currentFilter' => $filter,
        ]);
    }

    // ─── SETTINGS ───────────────────────────────────────────
    public function settings()
    {
        return Inertia::render('Control/Admin/Settings', [
            'settings' => Setting::current(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_ppn' => 'required|numeric|min:0|max:100',
            'default_margin' => 'required|numeric|min:0|max:100',
        ]);

        $settings = Setting::current();
        $old = $settings->toArray();
        $settings->update($validated);

        AuditTrail::log(
            $request->user()->id,
            'UPDATE_SETTINGS',
            'Mengubah pengaturan default PPN & Margin',
            Setting::class,
            $settings->id,
            $old,
            $validated
        );

        return redirect()->back()->with('success', 'Pengaturan berhasil diperbarui.');
    }
}