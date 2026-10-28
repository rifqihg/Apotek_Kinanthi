<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\IncomingStock;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminReportController extends Controller
{
    /**
     * Query data rekap penjualan produk obat jadi dengan single SQL grouping agregat
     */
    private function getSalesData(string $startDate, string $endDate, ?array $medicineIds = null)
    {
        if ($medicineIds !== null && empty($medicineIds)) {
            return collect();
        }

        $medSalesQuery = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'paid')
            ->where('sale_items.item_type', 'obat_jadi')
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate);

        if ($medicineIds !== null) {
            $medSalesQuery->whereIn('sale_items.medicine_id', $medicineIds);
        }

        $medSales = $medSalesQuery
            ->selectRaw('sale_items.medicine_id, SUM(sale_items.quantity) as terjual, SUM(sale_items.subtotal) as total_penjualan')
            ->groupBy('sale_items.medicine_id')
            ->get()
            ->keyBy('medicine_id');

        $medicinesQuery = Medicine::query()
            ->with(['latestActiveIncomingStock', 'warehouseInventories:id,medicine_id,batch_no'])
            ->orderBy('nama_obat');

        if ($medicineIds !== null) {
            $medicinesQuery->whereIn('id', $medicineIds);
        }

        $medicines = $medicinesQuery->get();

        return $medicines->map(function ($med) use ($medSales) {
            $sale = $medSales->get($med->id);
            $med->terjual = $sale ? (float) $sale->terjual : 0;
            $med->total_penjualan = $sale ? (float) $sale->total_penjualan : 0;
            return $this->calculateMedicineMetric($med);
        });
    }

    /**
     * Query data rekap penjualan produk racikan dengan single SQL grouping agregat
     */
    private function getCompoundedSalesData(string $startDate, string $endDate, ?array $compoundedIds = null)
    {
        if ($compoundedIds !== null && empty($compoundedIds)) {
            return collect();
        }

        $cpSalesQuery = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'paid')
            ->where('sale_items.item_type', 'racikan')
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate);

        if ($compoundedIds !== null) {
            $cpSalesQuery->whereIn('sale_items.compounded_product_id', $compoundedIds);
        }

        $cpSales = $cpSalesQuery
            ->selectRaw('sale_items.compounded_product_id, SUM(sale_items.quantity) as terjual, SUM(sale_items.subtotal) as total_penjualan')
            ->groupBy('sale_items.compounded_product_id')
            ->get()
            ->keyBy('compounded_product_id');

        $compoundedQuery = CompoundedProduct::query()
            ->orderBy('nama_produk');

        if ($compoundedIds !== null) {
            $compoundedQuery->whereIn('id', $compoundedIds);
        }

        $compounded = $compoundedQuery->get();

        return $compounded->map(function ($cp) use ($cpSales) {
            $sale = $cpSales->get($cp->id);
            $cp->terjual = $sale ? (float) $sale->terjual : 0;
            $cp->total_penjualan = $sale ? (float) $sale->total_penjualan : 0;
            return $this->calculateCompoundedMetric($cp);
        });
    }

    /**
     * Kalkulasi metrik penjualan satuan obat (single source of truth)
     */
    private function calculateMedicineMetric($med, ?IncomingStock $latestStock = null): array
    {
        $stock = $latestStock ?? $med->latestActiveIncomingStock;
        $modal = $stock ? (float) $stock->calculateHppSatuan() : 0;
        $threshold = (int) ($med->threshold_min ?? 10);

        // Resolusi nomor batch / kode obat: eliminasi strip '-' jika data batch fisik tersedia
        $kodeOrBatch = $med->kode_obat;
        if (empty($kodeOrBatch) || $kodeOrBatch === '-') {
            $kodeOrBatch = $stock?->batch_no
                ?: $med->warehouseInventories?->first()?->batch_no
                ?: ('MED-' . str_pad($med->id, 4, '0', STR_PAD_LEFT));
        }

        return $this->formatMetricRow(
            $med->id,
            $kodeOrBatch,
            $med->nama_obat,
            'Obat Jadi',
            $med->satuan_stok ?: $med->base_unit,
            $modal,
            (float) $med->selling_price,
            (float) $med->stok_total,
            (float) ($med->terjual ?? 0),
            (float) ($med->total_penjualan ?? 0),
            $threshold
        );
    }

    /**
     * Kalkulasi metrik penjualan produk racikan
     */
    private function calculateCompoundedMetric($cp): array
    {
        $modal = (float) ($cp->hpp_per_unit > 0 ? $cp->hpp_per_unit : $cp->base_cost);
        return $this->formatMetricRow(
            'cp-' . $cp->id,
            $cp->batch_no ?: ('RAC-' . str_pad($cp->id, 4, '0', STR_PAD_LEFT)),
            $cp->nama_produk,
            'Racikan',
            $cp->satuan ?: 'Paket',
            $modal,
            (float) $cp->harga_jual,
            (float) $cp->qty_hasil,
            (float) ($cp->terjual ?? 0),
            (float) ($cp->total_penjualan ?? 0),
            5
        );
    }

    private function formatMetricRow(
        $id,
        string $kode,
        string $nama,
        string $kategori,
        ?string $satuan,
        float $modal,
        float $hargaJual,
        float $sisaStok,
        float $terjual,
        float $totalPenjualan,
        int $thresholdMin = 10
    ): array {
        $totalModal = $modal * $terjual;
        $laba = $totalPenjualan - $totalModal;
        $marginPercentage = $totalPenjualan > 0 ? round(($laba / $totalPenjualan) * 100, 1) : 0.0;

        if ($sisaStok <= 0) {
            $status = 'Habis';
        } elseif ($sisaStok <= $thresholdMin) {
            $status = 'Menipis';
        } else {
            $status = 'Aman';
        }

        return [
            'id' => $id,
            'kode_obat' => $kode,
            'nama_produk' => $nama,
            'kategori' => $kategori,
            'satuan' => $satuan,
            'modal' => $modal,
            'harga_jual' => $hargaJual,
            'qty_awal' => $sisaStok + $terjual,
            'terjual' => $terjual,
            'sisa_stok' => $sisaStok,
            'threshold_min' => $thresholdMin,
            'total_penjualan' => $totalPenjualan,
            'total_modal' => $totalModal,
            'laba' => $laba,
            'margin_percentage' => $marginPercentage,
            'status' => $status,
        ];
    }

    /**
     * Hitung ringkasan agregasi KPI penjualan (Obat Jadi + Racikan)
     */
    private function calculateSalesSummary(string $startDate, string $endDate): array
    {
        $salesAgg = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'paid')
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate)
            ->selectRaw('SUM(sale_items.quantity) as total_items, SUM(sale_items.subtotal) as gross_amount')
            ->first();

        $totalItems = (float) ($salesAgg->total_items ?? 0);
        $grossAmount = (float) ($salesAgg->gross_amount ?? 0);

        // Ambil total diskon dari transaksi paid pada rentang tanggal yang sama
        $totalDiscount = (float) DB::table('sales')
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('discount_amount');

        // Omzet Bersih Riil (Net Sales) = Total Nilai Kotor Produk dikurangi Total Diskon
        $netAmount = max(0, $grossAmount - $totalDiscount);

        // Hitung total modal hanya untuk item yang terjual pada rentang tanggal
        $medSales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'paid')
            ->where('sale_items.item_type', 'obat_jadi')
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate)
            ->selectRaw('sale_items.medicine_id, SUM(sale_items.quantity) as terjual')
            ->groupBy('sale_items.medicine_id')
            ->get();

        $medModal = 0.0;
        if ($medSales->isNotEmpty()) {
            $meds = Medicine::with('latestActiveIncomingStock')
                ->whereIn('id', $medSales->pluck('medicine_id'))
                ->get()
                ->keyBy('id');

            foreach ($medSales as $item) {
                $med = $meds->get($item->medicine_id);
                $modal = $med?->latestActiveIncomingStock ? (float) $med->latestActiveIncomingStock->calculateHppSatuan() : 0.0;
                $medModal += $modal * (float) $item->terjual;
            }
        }

        $cpSales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'paid')
            ->where('sale_items.item_type', 'racikan')
            ->whereDate('sales.created_at', '>=', $startDate)
            ->whereDate('sales.created_at', '<=', $endDate)
            ->selectRaw('sale_items.compounded_product_id, SUM(sale_items.quantity) as terjual')
            ->groupBy('sale_items.compounded_product_id')
            ->get();

        $cpModal = 0.0;
        if ($cpSales->isNotEmpty()) {
            $cps = CompoundedProduct::whereIn('id', $cpSales->pluck('compounded_product_id'))
                ->get(['id', 'hpp_per_unit', 'base_cost'])
                ->keyBy('id');

            foreach ($cpSales as $item) {
                $cp = $cps->get($item->compounded_product_id);
                $modal = (float) (($cp && $cp->hpp_per_unit > 0) ? $cp->hpp_per_unit : ($cp?->base_cost ?? 0));
                $cpModal += $modal * (float) $item->terjual;
            }
        }

        $totalModal = $medModal + $cpModal;

        return [
            'total_items' => $totalItems,
            'gross_amount' => $grossAmount,
            'total_discount' => $totalDiscount,
            'total_amount' => $netAmount,
            'total_modal' => $totalModal,
            'total_laba' => $netAmount - $totalModal,
        ];
    }

    /**
     * Tampilkan halaman Laporan Penjualan (Preview & Filter)
     */
    public function salesReport(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $totalCount = Medicine::count() + CompoundedProduct::count();

        // Database-level unified pagination: fetch ONLY 20 IDs for the current page
        $medUnion = DB::table('medicines')->selectRaw("'obat_jadi' as item_type, id, nama_obat as nama_item");
        $cpUnion = DB::table('compounded_products')->selectRaw("'racikan' as item_type, id, nama_produk as nama_item");

        $pageRows = $medUnion->unionAll($cpUnion)
            ->orderBy('nama_item', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $medicineIds = $pageRows->where('item_type', 'obat_jadi')->pluck('id')->all();
        $compoundedIds = $pageRows->where('item_type', 'racikan')->pluck('id')->all();

        $medItems = $this->getSalesData($startDate, $endDate, $medicineIds)->keyBy('id');
        $cpItems = $this->getCompoundedSalesData($startDate, $endDate, $compoundedIds)->keyBy(fn($item) => $item['id']);

        $pageItems = $pageRows->map(function ($row) use ($medItems, $cpItems) {
            if ($row->item_type === 'obat_jadi') {
                return $medItems->get($row->id);
            }
            return $cpItems->get('cp-' . $row->id);
        })->filter()->values();

        $paginated = new LengthAwarePaginator(
            $pageItems,
            $totalCount,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Control/Admin/SalesReport', [
            'medicines' => fn() => $paginated,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => fn() => $this->calculateSalesSummary($startDate, $endDate),
        ]);
    }

    /**
     * Hitung KPI ringkasan monitoring kasir dengan agregasi cepat dan rincian tunai vs non-tunai.
     */
    private function calculateCashierKpis(string $startDate, string $endDate): array
    {
        $aggregates = Sale::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw("
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as total_transactions,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN discount_amount ELSE 0 END), 0) as total_discount,
                COALESCE(SUM(CASE WHEN status = 'paid' AND payment_method = 'tunai' THEN total_amount ELSE 0 END), 0) as total_tunai,
                COALESCE(SUM(CASE WHEN status = 'paid' AND payment_method != 'tunai' THEN total_amount ELSE 0 END), 0) as total_non_tunai,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as total_void,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total_amount ELSE 0 END), 0) as total_void_amount
            ")
            ->first();

        $totalSales = (float) ($aggregates->total_sales ?? 0);
        $totalDiscount = (float) ($aggregates->total_discount ?? 0);

        return [
            'total_transactions' => (int) ($aggregates->total_transactions ?? 0),
            'total_sales'        => $totalSales,
            'total_omset'        => $totalSales,
            'gross_amount'       => $totalSales + $totalDiscount,
            'total_discount'     => $totalDiscount,
            'total_tunai'        => (float) ($aggregates->total_tunai ?? 0),
            'total_non_tunai'    => (float) ($aggregates->total_non_tunai ?? 0),
            'total_void'         => (int) ($aggregates->total_void ?? 0),
            'total_void_amount'  => (float) ($aggregates->total_void_amount ?? 0),
        ];
    }

    public function cashierMonitoring(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $sales = Sale::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->whereIn('status', ['paid', 'cancelled'])
            ->with([
                'kasir:id,name',
                'saleItems:id,sale_id,item_type,item_name,quantity,unit,unit_price,subtotal',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $sales->getCollection()->transform(function ($sale) {
            $items = $sale->saleItems->map(fn($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ]);

            return [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number ?? '-',
                'created_at' => $sale->created_at,
                'kasir_name' => $sale->kasir ? $sale->kasir->name : 'Unknown',
                'sale_type' => $sale->sale_type ?? 'otc',
                'payment_method' => $sale->payment_method ?? 'tunai',
                'subtotal' => (float) ($sale->total_amount + $sale->discount_amount),
                'discount_amount' => (float) $sale->discount_amount,
                'total_amount' => (float) $sale->total_amount,
                'paid_amount' => (float) $sale->paid_amount,
                'change_amount' => (float) $sale->change_amount,
                'status' => $sale->status,
                'notes' => $sale->notes,
                'items' => $items,
            ];
        });

        return Inertia::render('Control/Admin/CashierMonitoring', [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'kpis' => fn() => $this->calculateCashierKpis($startDate, $endDate),
            'transactions' => fn() => $sales,
        ]);
    }
}

