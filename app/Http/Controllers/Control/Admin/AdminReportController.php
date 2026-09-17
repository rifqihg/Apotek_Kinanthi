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
use Inertia\Inertia;

class AdminReportController extends Controller
{
    /**
     * Query dasar rekap penjualan produk obat jadi berdasarkan rentang tanggal
     */
    private function getSalesQuery(string $startDate, string $endDate)
    {
        $paidFilter = fn($q) => $q->where('item_type', 'obat_jadi')
            ->whereHas('sale', fn($sq) => $sq->where('status', 'paid')->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate));

        return Medicine::query()
            ->with(['latestActiveIncomingStock', 'warehouseInventories:id,medicine_id,batch_no'])
            ->withSum(['saleItems as terjual' => $paidFilter], 'quantity')
            ->withSum(['saleItems as total_penjualan' => $paidFilter], 'subtotal')
            ->orderBy('nama_obat');
    }

    /**
     * Query dasar rekap penjualan produk racikan berdasarkan rentang tanggal
     */
    private function getCompoundedSalesQuery(string $startDate, string $endDate)
    {
        $paidFilter = fn($q) => $q->where('item_type', 'racikan')
            ->whereHas('sale', fn($sq) => $sq->where('status', 'paid')->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate));

        return CompoundedProduct::query()
            ->withSum(['saleItems as terjual' => $paidFilter], 'quantity')
            ->withSum(['saleItems as total_penjualan' => $paidFilter], 'subtotal')
            ->orderBy('nama_produk');
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
        $dateFilter = fn($sq) => $sq->where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // 1. Agregasi Obat Jadi
        $soldMedicines = Medicine::whereHas('saleItems.sale', $dateFilter)
            ->with('latestActiveIncomingStock')
            ->withSum(['saleItems as terjual' => fn($q) => $q->where('item_type', 'obat_jadi')->whereHas('sale', $dateFilter)], 'quantity')
            ->withSum(['saleItems as total_penjualan' => fn($q) => $q->where('item_type', 'obat_jadi')->whereHas('sale', $dateFilter)], 'subtotal')
            ->get();

        $medTerjual = (float) $soldMedicines->sum('terjual');
        $medAmount = (float) $soldMedicines->sum('total_penjualan');
        $medModal = (float) $soldMedicines->sum(function($med) {
            $latestStock = $med->latestActiveIncomingStock;
            $modal = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
            return $modal * (float) ($med->terjual ?? 0);
        });

        // 2. Agregasi Produk Racikan
        $soldCompounded = CompoundedProduct::whereHas('saleItems.sale', $dateFilter)
            ->withSum(['saleItems as terjual' => fn($q) => $q->where('item_type', 'racikan')->whereHas('sale', $dateFilter)], 'quantity')
            ->withSum(['saleItems as total_penjualan' => fn($q) => $q->where('item_type', 'racikan')->whereHas('sale', $dateFilter)], 'subtotal')
            ->get();

        $cpTerjual = (float) $soldCompounded->sum('terjual');
        $cpAmount = (float) $soldCompounded->sum('total_penjualan');
        $cpModal = (float) $soldCompounded->sum(function($cp) {
            $modal = (float) ($cp->hpp_per_unit > 0 ? $cp->hpp_per_unit : $cp->base_cost);
            return $modal * (float) ($cp->terjual ?? 0);
        });

        $totalAmount = $medAmount + $cpAmount;
        $totalModal = $medModal + $cpModal;

        return [
            'total_items' => $medTerjual + $cpTerjual,
            'total_amount' => $totalAmount,
            'total_modal' => $totalModal,
            'total_laba' => $totalAmount - $totalModal,
        ];
    }

    /**
     * Tampilkan halaman Laporan Penjualan (Preview & Filter)
     */
    public function salesReport(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));
        $page = (int) $request->query('page', 1);
        $perPage = 20;

        $medItems = $this->getSalesQuery($startDate, $endDate)->get()->map(fn($m) => $this->calculateMedicineMetric($m));
        $cpItems = $this->getCompoundedSalesQuery($startDate, $endDate)->get()->map(fn($cp) => $this->calculateCompoundedMetric($cp));

        $allItems = $medItems->concat($cpItems)->sortBy('nama_produk')->values();

        $paginated = new LengthAwarePaginator(
            $allItems->forPage($page, $perPage)->values(),
            $allItems->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Control/Admin/SalesReport', [
            'medicines' => $paginated,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $this->calculateSalesSummary($startDate, $endDate),
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

        return [
            'total_transactions' => (int) ($aggregates->total_transactions ?? 0),
            'total_sales'        => $totalSales,
            'total_omset'        => $totalSales,
            'total_discount'     => (float) ($aggregates->total_discount ?? 0),
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
            'kpis' => $this->calculateCashierKpis($startDate, $endDate),
            'transactions' => $sales,
        ]);
    }
}

