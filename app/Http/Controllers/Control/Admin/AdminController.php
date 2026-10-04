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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Admin Dashboard - The Brain of the system.
     */
    public function dashboard(Request $request)
    {
        $filter = $request->query('filter', 'abjad');
        $period = (string) $request->query('days', '7');
        if (!in_array($period, ['1D', '7', '30', '90', 'YTD', '365'])) {
            $period = '7';
        }

        return Inertia::render('Control/Admin/Dashboard', [
            'stats' => fn() => $this->getDashboardStats(),
            'tableStocks' => fn() => $this->getTableStocks($filter),
            'salesTrend' => fn() => $this->getSalesTrend($period),
            'topMedicines' => fn() => $this->getTopMedicines($period),
            'currentFilter' => $filter,
            'currentDays' => $period,
        ]);
    }

    /**
     * Agregat Tren Penjualan & Transaksi per Periode (1D, 7, 30, 90, YTD, 365)
     */
    private function getSalesTrend(string $period): array
    {
        // 1. FILTER 1D: HARI INI PER MENIT DALAM JAM OPERASIONAL APOTEK (08:00 - 22:00)
        if ($period === '1D') {
            $today = Carbon::today('Asia/Jakarta');
            $localStart = $today->copy()->setTime(8, 0, 0);
            $localEnd = $today->copy()->setTime(22, 0, 0);

            // Konversi ke UTC untuk query database karena Laravel menyimpan timestamp UTC
            $utcStart = $localStart->copy()->timezone('UTC');
            $utcEnd = $localEnd->copy()->timezone('UTC');

            // Ambil seluruh penjualan kasir hari ini dalam rentang jam operasional
            $salesToday = Sale::query()
                ->where('status', 'paid')
                ->where('created_at', '>=', $utcStart)
                ->where('created_at', '<=', $utcEnd)
                ->get(['created_at', 'total_amount']);

            $salesByMinute = [];
            foreach ($salesToday as $sale) {
                // Konversi kembali timestamp UTC ke WIB (Asia/Jakarta) sebelum melabeli sumbu X
                $m = $sale->created_at->timezone('Asia/Jakarta')->format('H:i');
                if (!isset($salesByMinute[$m])) {
                    $salesByMinute[$m] = ['sales' => 0.0, 'tx' => 0];
                }
                $salesByMinute[$m]['sales'] += (float) $sale->total_amount;
                $salesByMinute[$m]['tx'] += 1;
            }

            // Bangun deret waktu per menit dari 08:00 hingga 22:00
            $trend = [];
            $cursor = $localStart->copy();
            while ($cursor <= $localEnd) {
                $timeLabel = $cursor->format('H:i');
                $hasData = isset($salesByMinute[$timeLabel]);

                $trend[] = [
                    'date' => $today->format('Y-m-d') . ' ' . $timeLabel,
                    'label' => $timeLabel,
                    'sales' => $hasData ? $salesByMinute[$timeLabel]['sales'] : 0,
                    'transactions' => $hasData ? $salesByMinute[$timeLabel]['tx'] : 0,
                ];

                $cursor->addMinute();
            }

            return $trend;
        }

        // 2. FILTER HARIAN STANDAR (7, 30, 90, 365 HARI & YTD TAHUN BERJALAN)
        if ($period === 'YTD') {
            $startDate = Carbon::today()->startOfYear();
            $days = (int) $startDate->diffInDays(Carbon::today()) + 1;
        } else {
            $days = (int) $period;
            if ($days <= 0) $days = 7;
            $startDate = Carbon::today()->subDays($days - 1)->startOfDay();
        }

        $salesGrouped = Sale::query()
            ->where('status', 'paid')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as sale_date, COUNT(id) as total_tx, COALESCE(SUM(total_amount), 0) as total_nominal')
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateObj = Carbon::today()->subDays($i);
            $dateStr = $dateObj->format('Y-m-d');
            $label = $dateObj->format('d M');

            $record = $salesGrouped->get($dateStr);

            $trend[] = [
                'date' => $dateStr,
                'label' => $label,
                'sales' => $record ? (float) $record->total_nominal : 0,
                'transactions' => $record ? (int) $record->total_tx : 0,
            ];
        }

        return $trend;
    }

    /**
     * Agregat Top Obat Paling Laris Berdasarkan QTY Terjual
     */
    private function getTopMedicines(string $period): array
    {
        if ($period === '1D') {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        } elseif ($period === 'YTD') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now();
        } else {
            $days = (int) $period;
            if ($days <= 0) $days = 7;
            $startDate = Carbon::today()->subDays($days - 1)->startOfDay();
            $endDate = Carbon::now();
        }

        // Agregat JOIN dengan index komposit, grouping medicine_id, limit 5
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
            ->where('sales.status', 'paid')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->selectRaw('medicines.id, medicines.nama_obat as name, medicines.kode_obat as code, medicines.satuan_stok as unit, SUM(sale_items.quantity) as total_qty')
            ->groupBy('medicines.id', 'medicines.nama_obat', 'medicines.kode_obat', 'medicines.satuan_stok')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'unit' => $item->unit ?? 'Unit',
                'qty' => (float) $item->total_qty,
            ])
            ->toArray();
    }

    private function getDashboardStats(): array
    {
        $totalMedicines = Medicine::count();
        $lowStockCount = Medicine::belowThreshold()->count();
        $fefoAlertCount = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->expiringSoon(90)->count();
        $fefoNearestBatch = WarehouseInventory::active()->where('stock_on_hand', '>', 0)->whereNotNull('expired_date')->orderBy('expired_date', 'asc')->first();

        $pendingCompounding = CompoundingSubmission::pending()->count();

        $todaySalesCount = Sale::where('status', 'paid')->whereDate('created_at', today())->count();
        $todaySalesTotal = (float) (Sale::where('status', 'paid')->whereDate('created_at', today())->sum('total_amount') ?? 0);
        $activePrescriptionsCount = Prescription::whereIn('status', ['diproses', 'siap_ambil'])->count();
        $pendingPricingCount = CompoundedProduct::where('pricing_status', 'pending_price')->count();

        $lowStockPercentage = ($totalMedicines > 0) ? round(($lowStockCount / $totalMedicines) * 100) : 0;

        return [
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
        ];
    }

    private function getTableStocks(string $filter)
    {
        $query = Medicine::with(['earliestExpiringStock', 'latestActiveIncomingStock']);
        
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
            $incoming = $medicine->earliestExpiringStock; // Gets the one with earliest expiry
            $latest = $medicine->latestActiveIncomingStock;
            
            return [
                'id' => $medicine->id,
                'nama_obat' => $medicine->nama_obat,
                'kategori' => $medicine->kategori ?: '-',
                'nomor_batch' => $incoming ? $incoming->batch_no : '-',
                'stok_fisik' => $medicine->stok_total,
                
                'qty_masuk' => 0,
                'qty_converted' => $medicine->stok_total,
                'satuan_input' => $medicine->satuan_besar,
                'mid_unit' => $medicine->satuan_menengah,
                'unit_conversion_factor' => $latest ? $latest->unit_conversion_factor : ($medicine->rasio_konversi ?: 1),
                'mid_to_big_ratio' => $latest ? $latest->mid_to_big_ratio : 1,
                'small_to_mid_ratio' => $latest ? $latest->small_to_mid_ratio : ($medicine->rasio_konversi ?: 1),
                'satuan_stok' => $medicine->satuan_stok ?? 'Unit',
                
                'batas_aman' => $medicine->threshold_min,
                'expired_date' => $incoming && $incoming->expired_date ? Carbon::parse($incoming->expired_date)->format('d M Y') : '-',
                'is_expiring_soon' => $incoming && $incoming->expired_date && Carbon::parse($incoming->expired_date)->diffInDays(now()) <= 90
            ];
        });
        
        $paginator->setCollection($tableStocks);

        return $paginator;
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