<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
use App\Models\Sale;
use Illuminate\Http\Request;
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
     * Query dasar rekap penjualan produk berdasarkan rentang tanggal
     */
    private function getSalesQuery(string $startDate, string $endDate)
    {
        return Medicine::query()
            ->with(['incomingStocks' => fn($q) => $q->where('status_gate', 'active')->latest()])
            ->withSum(['saleItems as terjual' => fn($q) => $q->whereHas('sale', fn($sq) => $sq->where('status', 'paid')->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate))], 'quantity')
            ->withSum(['saleItems as total_penjualan' => fn($q) => $q->whereHas('sale', fn($sq) => $sq->where('status', 'paid')->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate))], 'subtotal')
            ->orderBy('nama_obat');
    }

    /**
     * Kalkulasi metrik penjualan satuan obat (single source of truth)
     */
    private function calculateMedicineMetric($med): array
    {
        $latestStock = $med->incomingStocks->first();
        $modal = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
        $terjual = (float) ($med->terjual ?? 0);
        $totalPenjualan = (float) ($med->total_penjualan ?? 0);
        $totalModal = $modal * $terjual;
        $sisaStok = (float) $med->stok_total;

        return [
            'id' => $med->id,
            'nama_produk' => $med->nama_obat,
            'satuan' => $med->satuan_stok ?: $med->base_unit,
            'modal' => $modal,
            'harga_jual' => (float) $med->selling_price,
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
     * Tampilkan halaman Laporan Penjualan (Preview & Filter)
     */
    public function salesReport(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $query = $this->getSalesQuery($startDate, $endDate);
        $allMetrics = (clone $query)->get()->map(fn($m) => $this->calculateMedicineMetric($m));

        $medicines = $query->paginate(20)->withQueryString();
        $medicines->getCollection()->transform(fn($m) => $this->calculateMedicineMetric($m));

        return Inertia::render('Control/Admin/SalesReport', [
            'medicines' => $medicines,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_items' => $allMetrics->sum('terjual'),
                'total_amount' => $allMetrics->sum('total_penjualan'),
                'total_modal' => $allMetrics->sum('total_modal'),
                'total_laba' => $allMetrics->sum('laba'),
            ]
        ]);
    }

    /**
     * Export Rekapitulasi Penjualan ke format .xlsx (Excel) menggunakan OpenSpout
     */
    public function exportSales(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $rows = $this->getSalesQuery($startDate, $endDate)->get()->map(fn($m) => $this->calculateMedicineMetric($m));
        if ($rows->isEmpty()) {
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

        $totalPenjualanKeseluruhan = $rows->sum('total_penjualan');
        $totalModalKeseluruhan = $rows->sum('total_modal');
        $totalLabaKeseluruhan = $rows->sum('laba');

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
        
        $sheet->setAutoFilter(new AutoFilter(0, 9, 11, 9 + $rows->count()));

        $no = 1;
        $sumQtyAwal = 0;
        $sumTerjual = 0;
        $sumSisaStok = 0;

        foreach ($rows as $row) {
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

    public function cashierMonitoring(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $sales = Sale::with([
                'kasir',
                'saleItems.medicine.incomingStocks' => fn($q) => $q->where('status_gate', 'active')->latest()
            ])
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $transactions = $sales->map(function ($sale) {
            $modal = 0;
            $items = [];

            foreach ($sale->saleItems as $item) {
                $itemModal = 0;
                $sisaStok = 0;
                
                if ($item->item_type === 'obat_jadi' && $item->medicine) {
                    $latestStock = $item->medicine->incomingStocks->first();
                    $hppSatuan = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
                    $itemModal = ($hppSatuan * $item->quantity);
                    $modal += $itemModal;
                    $sisaStok = $item->medicine->stok_total;
                }
                
                $items[] = [
                    'id' => $item->id,
                    'item_name' => $item->item_name ?: ($item->medicine ? $item->medicine->nama_obat : 'Racikan'),
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
            'kpis' => [
                'total_transactions' => $transactions->count(),
                'total_sales' => $transactions->sum('total_amount'),
                'total_discount' => $transactions->sum('discount_amount'),
                'total_laba' => $transactions->sum('laba'),
            ],
            'transactions' => $transactions,
        ]);
    }
}
