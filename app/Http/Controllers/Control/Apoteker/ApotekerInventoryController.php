<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\ReturnForm;
use App\Models\Control\StockLog;
use App\Models\Control\StockMutation;
use App\Models\Control\WarehouseInventory;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApotekerInventoryController extends Controller
{
    /**
     * Check current physical stocks of raw materials and catalog medicines.
     */
    public function stockCheck(Request $request)
    {
        $search = $request->query('search');

        $rawMaterials = RawMaterial::select(['id', 'kode_bahan', 'nama_bahan', 'bentuk_sediaan', 'satuan_dasar_penggunaan', 'stok_total', 'threshold_min'])
            ->with(['stocks' => fn($q) => $q->select(['id', 'raw_material_id', 'batch_no', 'expired_date', 'stock_on_hand', 'status'])
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date')
            ])
            ->when($search, fn($q) => $q->where(fn($sub) => 
                $sub->where('nama_bahan', 'like', "%{$search}%")
                    ->orWhere('kode_bahan', 'like', "%{$search}%")
            ))
            ->orderBy('nama_bahan')
            ->limit(50)
            ->get();

        $medicines = Medicine::select(['id', 'kode_obat', 'nama_obat', 'satuan_stok', 'base_unit', 'selling_price', 'stok_total', 'category_id'])
            ->with(['warehouseInventories' => fn($q) => $q->select(['id', 'medicine_id', 'batch_no', 'expired_date', 'stock_on_hand', 'status', 'location_type'])
                ->active()
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date')
            ])
            ->where('stok_total', '>', 0)
            ->when($search, fn($q) => $q->where(fn($sub) => 
                $sub->where('nama_obat', 'like', "%{$search}%")
                    ->orWhere('kode_obat', 'like', "%{$search}%")
            ))
            ->orderBy('nama_obat')
            ->limit(50)
            ->get();

        return Inertia::render('Control/Apoteker/StockCheck', [
            'rawMaterials' => $rawMaterials,
            'medicines'    => $medicines,
            'filters'      => [
                'search' => $search ?? '',
            ],
        ]);
    }


    /**
     * Compute SIPNAP data for a given Y-m month.
     */
    private function getSipnapData(string $month): array
    {
        try {
            $carbonMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            $carbonMonth = now()->startOfMonth();
            $month = $carbonMonth->format('Y-m');
        }

        $startOfMonth = $carbonMonth->copy()->startOfDay();
        $endOfMonth = $carbonMonth->copy()->endOfMonth()->endOfDay();

        // 1. Ambil data obat sediaan jadi terkontrol (Narkotika & Psikotropika)
        $controlledMedicines = Medicine::sipnap()
            ->select(['id', 'kode_obat', 'nama_obat', 'kategori', 'classification', 'satuan_stok', 'base_unit'])
            ->orderBy('classification')
            ->orderBy('nama_obat')
            ->get();

        // 2. Ambil data bahan baku murni racikan terkontrol (Narkotika & Psikotropika)
        $controlledRawMaterials = RawMaterial::whereIn('golongan_bahan', ['Narkotika', 'Psikotropika', 'narkotika', 'psikotropika'])
            ->select(['id', 'nama_bahan', 'kode_bahan', 'golongan_bahan', 'satuan_dasar_penggunaan', 'stok_total'])
            ->orderBy('golongan_bahan')
            ->orderBy('nama_bahan')
            ->get();

        $medIds = $controlledMedicines->pluck('id')->all();
        $rmIds = $controlledRawMaterials->pluck('id')->all();

        // 3. Batch query mutasi stok (mengecualikan mutasi distribusi internal agar tidak menggembungkan laporan)
        $mutations = StockMutation::select([
                'id', 'item_id', 'item_type', 'type', 'quantity', 'note', 'reference_type', 'created_at'
            ])
            ->where(function ($q) use ($medIds, $rmIds) {
                if (!empty($medIds)) {
                    $q->where(fn($sub) => $sub->whereIn('item_id', $medIds)->where('item_type', Medicine::class));
                }
                if (!empty($rmIds)) {
                    $q->orWhere(fn($sub) => $sub->whereIn('item_id', $rmIds)->where('item_type', RawMaterial::class));
                }
            })
            ->where('type', 'not like', '%Distribusi%')
            ->where(fn($q) => $q->whereNull('note')->orWhere('note', 'not like', '%Distribusi%'))
            ->get()
            ->groupBy(fn($m) => $m->item_type . '_' . $m->item_id);

        // 4. Batch query stok fisik aktif saat ini & nomor batch aktif
        $medicineStockSummaries = empty($medIds) ? collect() : WarehouseInventory::whereIn('medicine_id', $medIds)
            ->where('status', 'active')
            ->where('is_frozen', false)
            ->selectRaw('medicine_id, SUM(stock_on_hand) as total_stock, GROUP_CONCAT(DISTINCT batch_no SEPARATOR ", ") as batch_nos')
            ->groupBy('medicine_id')
            ->get()
            ->keyBy('medicine_id');

        $rawMaterialStockSummaries = empty($rmIds) ? collect() : RawMaterialStock::whereIn('raw_material_id', $rmIds)
            ->where('stock_on_hand', '>', 0)
            ->selectRaw('raw_material_id, SUM(stock_on_hand) as total_stock, GROUP_CONCAT(DISTINCT batch_no SEPARATOR ", ") as batch_nos')
            ->groupBy('raw_material_id')
            ->get()
            ->keyBy('raw_material_id');

        // Helper hitung stok awal, masuk, keluar, dan stok akhir secara matematis & berantai
        $processItemReport = function ($id, $name, $classification, $satuan, $itemType, $currentStock, $batchNos) use ($startOfMonth, $endOfMonth, $mutations) {
            $itemMutations = $mutations->get($itemType . '_' . $id, collect());

            $masukBulan = 0.0;
            $keluarBulan = 0.0;
            $masukAfter = 0.0;
            $keluarAfter = 0.0;

            foreach ($itemMutations as $mut) {
                $qty = (float) $mut->quantity;
                $typeLower = strtolower($mut->type ?? '');

                $isRetur = in_array($typeLower, ['retur', 'return']) || $mut->reference_type === ReturnForm::class;
                $isMasuk = in_array($typeLower, ['masuk', 'in', 'penyesuaian masuk', 'sale_void']) && !$isRetur;
                $isKeluar = in_array($typeLower, ['keluar', 'out', 'penyesuaian keluar', 'pemusnahan', 'destruction', 'jual']) || $isRetur;

                // Fallback opname adjustment tanpa arah eksplisit
                if (!$isMasuk && !$isKeluar) {
                    if (str_contains($typeLower, 'opname')) {
                        if (str_contains($mut->note ?? '', '+')) {
                            $isMasuk = true;
                        } else {
                            $isKeluar = true;
                        }
                    }
                }

                if ($mut->created_at >= $startOfMonth && $mut->created_at <= $endOfMonth) {
                    if ($isMasuk) $masukBulan += $qty;
                    if ($isKeluar) $keluarBulan += $qty;
                } elseif ($mut->created_at > $endOfMonth) {
                    if ($isMasuk) $masukAfter += $qty;
                    if ($isKeluar) $keluarAfter += $qty;
                }
            }

            // Hitung mundur dari stok fisik riil untuk menjamin ground truth 100% presisi
            $stokAkhir = max(0, (float) $currentStock - $masukAfter + $keluarAfter);
            $stokAwal = max(0, $stokAkhir - $masukBulan + $keluarBulan);

            return [
                'id' => $id,
                'batch_no' => $batchNos ?: '-',
                'nama_obat' => $name,
                'classification' => strtolower($classification),
                'satuan' => $satuan ?: 'Pcs',
                'stok_awal' => $stokAwal,
                'pemasukan' => $masukBulan,
                'pengeluaran' => $keluarBulan,
                'stok_akhir' => $stokAkhir,
            ];
        };

        $reportData = collect();

        foreach ($controlledMedicines as $med) {
            $summary = $medicineStockSummaries->get($med->id);
            $currentStock = (float) ($summary?->total_stock ?? 0);
            $batchNos = $summary?->batch_nos ?? '-';
            $reportData->push($processItemReport(
                $med->id,
                $med->nama_obat,
                $med->classification,
                $med->satuan_terkecil,
                Medicine::class,
                $currentStock,
                $batchNos
            ));
        }

        foreach ($controlledRawMaterials as $rm) {
            $summary = $rawMaterialStockSummaries->get($rm->id);
            $currentStock = (float) ($summary?->total_stock ?? 0);
            $batchNos = $summary?->batch_nos ?? '-';
            $reportData->push($processItemReport(
                $rm->id,
                $rm->nama_bahan . ' (Bahan Baku)',
                $rm->golongan_bahan,
                $rm->satuan_dasar_penggunaan ?: 'Gram',
                RawMaterial::class,
                $currentStock,
                $batchNos
            ));
        }

        $summary = [
            'total_items' => $reportData->count(),
            'total_narkotika' => $reportData->where('classification', 'narkotika')->count(),
            'total_psikotropika' => $reportData->where('classification', 'psikotropika')->count(),
            'total_pemasukan' => $reportData->sum('pemasukan'),
            'total_pengeluaran' => $reportData->sum('pengeluaran'),
        ];

        return [
            'month' => $month,
            'carbonMonth' => $carbonMonth,
            'summary' => $summary,
            'reportData' => $reportData,
        ];
    }

    /**
     * SIPNAP Monthly Controlled Substance Movement Report (Narkotika & Psikotropika).
     */
    public function sipnapReport(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $data = $this->getSipnapData($month);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'month' => $data['month'],
                'summary' => $data['summary'],
                'data' => $data['reportData'],
            ]);
        }

        return Inertia::render('Control/Apoteker/Reports/Sipnap', [
            'month' => $data['month'],
            'summary' => $data['summary'],
            'reportData' => $data['reportData'],
        ]);
    }

    /**
     * Export SIPNAP Monthly Report to PDF (A4 Landscape).
     */
    public function exportSipnapPdf(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $data = $this->getSipnapData($month);

        $monthLabel = $data['carbonMonth']->translatedFormat('F Y');

        $pdf = Pdf::loadView('exports.sipnap-report-pdf', [
            'month'        => $data['month'],
            'monthLabel'   => $monthLabel,
            'reportData'   => $data['reportData'],
            'summary'      => $data['summary'],
            'logoKinanthi' => $this->getLogoBase64(),
            'logoKemenkes' => $this->getKemenkesLogoBase64(),
            'printedBy'    => auth()->user()?->name ?? 'Apoteker Pengelola Apotek',
            'printedAt'    => Carbon::now()->format('d/m/Y H:i'),
            'dateNow'      => Carbon::now()->translatedFormat('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-sipnap-kinanthi-' . $data['month'] . '.pdf');
    }

    private function getLogoBase64(): ?string
    {
        $logoPath = public_path('images/logo-clean.webp');
        if (!file_exists($logoPath)) {
            $logoPath = public_path('images/logo.webp');
        }

        if (file_exists($logoPath)) {
            $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
            return 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($logoPath));
        }

        return null;
    }

    private function getKemenkesLogoBase64(): ?string
    {
        $logoPath = public_path('images/kemenkes-clean.webp');
        if (!file_exists($logoPath)) {
            $logoPath = public_path('images/kemenkes.webp');
        }

        if (file_exists($logoPath)) {
            $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
            return 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($logoPath));
        }

        return null;
    }
}
