<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\StockLog;
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

        $controlledMedicines = Medicine::sipnap()
            ->select(['id', 'kode_obat', 'nama_obat', 'kategori', 'classification', 'satuan_stok', 'base_unit'])
            ->orderBy('classification')
            ->orderBy('nama_obat')
            ->get();

        $reportData = $controlledMedicines->map(function ($med) use ($startOfMonth, $endOfMonth) {
            // Ambil semua mutasi dari Ledger (StockMutation)
            $mutations = \App\Models\Control\StockMutation::where('item_id', $med->id)
                ->where('item_type', \App\Models\Control\Medicine::class)
                ->get();

            $masukBefore = 0.0;
            $keluarBefore = 0.0;
            $masukBulan = 0.0;
            $keluarBulan = 0.0;

            foreach ($mutations as $mut) {
                $qty = (float) $mut->quantity;
                $typeLower = strtolower($mut->type ?? '');
                
                $isMasuk = in_array($typeLower, ['masuk', 'distribusi masuk', 'penyesuaian masuk', 'retur']);
                $isKeluar = in_array($typeLower, ['keluar', 'distribusi keluar', 'penyesuaian keluar', 'pemusnahan']);
                
                // Fallback untuk tipe lama (opname_adjustment) tanpa arah eksplisit
                if (!$isMasuk && !$isKeluar) {
                    if (str_contains($typeLower, 'opname_adjustment') || str_contains($typeLower, 'penyesuaian opname')) {
                        if (str_contains($mut->note ?? '', '+')) {
                            $isMasuk = true;
                        } else {
                            $isKeluar = true;
                        }
                    }
                }

                if ($mut->created_at < $startOfMonth) {
                    if ($isMasuk) $masukBefore += $qty;
                    if ($isKeluar) $keluarBefore += $qty;
                } elseif ($mut->created_at <= $endOfMonth) {
                    if ($isMasuk) $masukBulan += $qty;
                    if ($isKeluar) $keluarBulan += $qty;
                }
            }

            // 3. Kalkulasi Berantai
            $stokAwal = max(0, $masukBefore - $keluarBefore);
            $stokAkhir = max(0, $stokAwal + $masukBulan - $keluarBulan);

            $batchNos = \App\Models\Control\WarehouseInventory::where('medicine_id', $med->id)
                ->where('status', 'active')
                ->pluck('batch_no')
                ->filter()
                ->unique()
                ->implode(', ');

            return [
                'id' => $med->id,
                'batch_no' => $batchNos ?: '-',
                'nama_obat' => $med->nama_obat,
                'classification' => $med->classification,
                'satuan' => $med->satuan_terkecil,
                'stok_awal' => $stokAwal,
                'pemasukan' => $masukBulan,
                'pengeluaran' => $keluarBulan,
                'stok_akhir' => $stokAkhir,
            ];
        });

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
