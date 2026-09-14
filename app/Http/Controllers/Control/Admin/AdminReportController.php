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
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

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
            ->with('latestActiveIncomingStock')
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
        return $this->formatMetricRow($med->id, $med->nama_obat, $med->satuan_stok ?: $med->base_unit, $modal, (float) $med->selling_price, (float) $med->stok_total, (float) ($med->terjual ?? 0), (float) ($med->total_penjualan ?? 0));
    }

    /**
     * Kalkulasi metrik penjualan produk racikan
     */
    private function calculateCompoundedMetric($cp): array
    {
        $modal = (float) ($cp->hpp_per_unit > 0 ? $cp->hpp_per_unit : $cp->base_cost);
        return $this->formatMetricRow('cp-' . $cp->id, $cp->nama_produk . ' (Racikan)', $cp->satuan ?: 'Paket', $modal, (float) $cp->harga_jual, (float) $cp->qty_hasil, (float) ($cp->terjual ?? 0), (float) ($cp->total_penjualan ?? 0));
    }

    private function formatMetricRow($id, string $nama, ?string $satuan, float $modal, float $hargaJual, float $sisaStok, float $terjual, float $totalPenjualan): array
    {
        $totalModal = $modal * $terjual;
        return [
            'id' => $id,
            'nama_produk' => $nama,
            'satuan' => $satuan,
            'modal' => $modal,
            'harga_jual' => $hargaJual,
            'qty_awal' => $sisaStok + $terjual,
            'terjual' => $terjual,
            'sisa_stok' => $sisaStok,
            'total_penjualan' => $totalPenjualan,
            'total_modal' => $totalModal,
            'laba' => $totalPenjualan - $totalModal,
            'status' => $sisaStok > 0 ? 'Tersedia' : 'Pesan',
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
     * Export Rekapitulasi Penjualan ke format .xlsx (Excel) menggunakan OpenSpout
     */
    public function exportSales(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $salesQuery = $this->getSalesQuery($startDate, $endDate);
        if (!$salesQuery->exists()) {
            return back()->with('error', 'Tidak ada data produk.');
        }

        $fileName = 'rekap-penjualan-' . $startDate . '-sd-' . $endDate . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile('php://output');

        // Styles
        $headerStyle = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('002060')->setCellAlignment(CellAlignment::CENTER);
        $titleStyle = (new Style())->setFontBold()->setCellAlignment(CellAlignment::CENTER);
        $titleStyle14 = (clone $titleStyle)->setFontSize(14);
        $currencyStyle = (new Style())->setFormat('Rp #,##0');
        $boldCurrencyStyle = (new Style())->setFontBold()->setFormat('Rp #,##0');
        $boldStyle = (new Style())->setFontBold();
        $centerStyle = (new Style())->setCellAlignment(CellAlignment::CENTER);
        $statusPesanStyle = (new Style())->setBackgroundColor('FFC7CE')->setFontColor('9C0006')->setCellAlignment(CellAlignment::CENTER);
        $statusTersediaStyle = (new Style())->setBackgroundColor('C6EFCE')->setFontColor('006100')->setCellAlignment(CellAlignment::CENTER);

        $options->mergeCells(0, 1, 11, 1, 0);
        $options->mergeCells(0, 2, 11, 2, 0);
        $options->mergeCells(0, 3, 11, 3, 0);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Rekap Penjualan');
        
        $colWidths = [1 => 6, 2 => 30, 3 => 10, 4 => 15, 5 => 15, 6 => 12, 7 => 12, 8 => 12, 9 => 20, 10 => 20, 11 => 20, 12 => 12];
        foreach ($colWidths as $col => $w) {
            $options->setColumnWidth($w, $col);
        }

        $writer->addRow(new Row([Cell::fromValue('APOTEK KINANTHI', $titleStyle14)]));
        $writer->addRow(new Row([Cell::fromValue('Jl. Dieng No.24, Wonosobo Timur, Kec. Wonosobo', $titleStyle)]));
        
        $startDateFormatted = Carbon::parse($startDate)->format('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->format('d F Y');
        $writer->addRow(new Row([Cell::fromValue("REKAP PENJUALAN TGL $startDateFormatted - $endDateFormatted", $titleStyle)]));
        $writer->addRow(new Row([]));

        // Ringkasan Agregasi KPI Penjualan
        $summary = $this->calculateSalesSummary($startDate, $endDate);
        $totalPenjualanKeseluruhan = $summary['total_amount'];
        $totalModalKeseluruhan = $summary['total_modal'];
        $totalLabaKeseluruhan = $summary['total_laba'];

        // Summary Atas
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue('Total Penjualan', $boldStyle), Cell::fromValue($totalPenjualanKeseluruhan, $boldCurrencyStyle)]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue('Total Modal', $boldStyle), Cell::fromValue($totalModalKeseluruhan, $boldCurrencyStyle)]));
        $writer->addRow(new Row([Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue('Total Laba', $boldStyle), Cell::fromValue($totalLabaKeseluruhan, $boldCurrencyStyle)]));
        $writer->addRow(new Row([]));

        $sheetView = new SheetView();
        $sheetView->setFreezeRow(9);
        $sheet->setSheetView($sheetView);
        
        $writer->addRow(new Row([
            Cell::fromValue('NO', $headerStyle),
            Cell::fromValue('NAMA PRODUK', $headerStyle),
            Cell::fromValue('SATUAN', $headerStyle),
            Cell::fromValue('MODAL', $headerStyle),
            Cell::fromValue('HARGA JUAL', $headerStyle),
            Cell::fromValue('QTY AWAL', $headerStyle),
            Cell::fromValue('TERJUAL', $headerStyle),
            Cell::fromValue('SISA STOK', $headerStyle),
            Cell::fromValue('TOTAL PENJUALAN', $headerStyle),
            Cell::fromValue('MODAL', $headerStyle),
            Cell::fromValue('LABA', $headerStyle),
            Cell::fromValue('STATUS', $headerStyle),
        ]));
        
        // Preload active incoming stocks map untuk mencegah N+1 saat cursor stream
        $latestStocks = IncomingStock::where('status_gate', 'active')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('medicine_id')
            ->keyBy('medicine_id');

        $totalRows = Medicine::count() + CompoundedProduct::count();
        $sheet->setAutoFilter(new AutoFilter(0, 9, 11, 9 + $totalRows));

        $no = 1;
        $sumQtyAwal = 0;
        $sumTerjual = 0;
        $sumSisaStok = 0;

        // Generator baris gabungan Obat Jadi & Racikan
        $rowsGenerator = function () use ($salesQuery, $latestStocks, $startDate, $endDate) {
            foreach ($salesQuery->cursor() as $med) {
                yield $this->calculateMedicineMetric($med, $latestStocks->get($med->id));
            }
            foreach ($this->getCompoundedSalesQuery($startDate, $endDate)->cursor() as $cp) {
                yield $this->calculateCompoundedMetric($cp);
            }
        };

        foreach ($rowsGenerator() as $row) {
            $sumQtyAwal += $row['qty_awal'];
            $sumTerjual += $row['terjual'];
            $sumSisaStok += $row['sisa_stok'];
            $activeStatusStyle = $row['sisa_stok'] > 0 ? $statusTersediaStyle : $statusPesanStyle;

            $writer->addRow(new Row([
                Cell::fromValue($no++, $centerStyle),
                Cell::fromValue($row['nama_produk']),
                Cell::fromValue($row['satuan'], $centerStyle),
                Cell::fromValue($row['modal'], $currencyStyle),
                Cell::fromValue($row['harga_jual'], $currencyStyle),
                Cell::fromValue($row['qty_awal'], $centerStyle),
                Cell::fromValue($row['terjual'], $centerStyle),
                Cell::fromValue($row['sisa_stok'], $centerStyle),
                Cell::fromValue($row['total_penjualan'], $currencyStyle),
                Cell::fromValue($row['total_modal'], $currencyStyle),
                Cell::fromValue($row['laba'], $currencyStyle),
                Cell::fromValue($row['status'], $activeStatusStyle),
            ]));
        }
        
        $writer->addRow(new Row([
            Cell::fromValue(''),
            Cell::fromValue('TOTAL', (new Style())->setFontBold()->setCellAlignment(CellAlignment::CENTER)),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue((float) $sumQtyAwal, (new Style())->setFontBold()->setCellAlignment(CellAlignment::CENTER)),
            Cell::fromValue((float) $sumTerjual, (new Style())->setFontBold()->setCellAlignment(CellAlignment::CENTER)),
            Cell::fromValue((float) $sumSisaStok, (new Style())->setFontBold()->setCellAlignment(CellAlignment::CENTER)),
            Cell::fromValue((float) $totalPenjualanKeseluruhan, $boldCurrencyStyle),
            Cell::fromValue((float) $totalModalKeseluruhan, $boldCurrencyStyle),
            Cell::fromValue((float) $totalLabaKeseluruhan, $boldCurrencyStyle),
            Cell::fromValue(''),
        ]));

        $writer->close();
        exit;
    }

    /**
     * Hitung KPI ringkasan monitoring kasir secara tertunda (Inertia defer)
     */
    private function calculateCashierKpis(string $startDate, string $endDate): array
    {
        $baseQuery = Sale::where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        $totalTransactions = (clone $baseQuery)->count();
        $totalSales = (float) ((clone $baseQuery)->sum('total_amount') ?? 0);
        $totalDiscount = (float) ((clone $baseQuery)->sum('discount_amount') ?? 0);

        // Ambil total modal dari summary terpadu tanpa duplikasi kueri
        $summary = $this->calculateSalesSummary($startDate, $endDate);
        $totalModal = $summary['total_modal'];

        return [
            'total_transactions' => $totalTransactions,
            'total_sales' => $totalSales,
            'total_discount' => $totalDiscount,
            'total_laba' => $totalSales - $totalModal,
        ];
    }

    public function cashierMonitoring(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $sales = Sale::where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with([
                'kasir',
                'saleItems.medicine.latestActiveIncomingStock',
                'saleItems.compoundedProduct',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $sales->getCollection()->transform(function ($sale) {
            $modal = 0;
            $items = [];

            foreach ($sale->saleItems as $item) {
                $itemModal = 0;
                $sisaStok = 0;
                
                if ($item->item_type === 'obat_jadi' && $item->medicine) {
                    $latestStock = $item->medicine->latestActiveIncomingStock;
                    $hppSatuan = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
                    $itemModal = ($hppSatuan * $item->quantity);
                    $modal += $itemModal;
                    $sisaStok = $item->medicine->stok_total;
                } elseif ($item->item_type === 'racikan') {
                    $cp = $item->compoundedProduct;
                    $hppSatuan = $cp ? (float) ($cp->hpp_per_unit > 0 ? $cp->hpp_per_unit : $cp->base_cost) : 0;
                    $itemModal = ($hppSatuan * $item->quantity);
                    $modal += $itemModal;
                    $sisaStok = $cp ? $cp->qty_hasil : 0;
                }
                
                $items[] = [
                    'id' => $item->id,
                    'item_name' => $item->item_name ?: ($item->medicine ? $item->medicine->nama_obat : ($item->compoundedProduct ? $item->compoundedProduct->nama_produk : 'Racikan')),
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'item_type' => $item->item_type,
                    'modal' => $itemModal,
                    'sisa_stok' => $sisaStok,
                ];
            }
            
            return [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number ?? '-',
                'created_at' => $sale->created_at,
                'kasir_name' => $sale->kasir ? $sale->kasir->name : 'Unknown',
                'subtotal' => $sale->total_amount + $sale->discount_amount,
                'discount_amount' => $sale->discount_amount,
                'total_amount' => $sale->total_amount,
                'modal' => $modal,
                'laba' => $sale->total_amount - $modal,
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
