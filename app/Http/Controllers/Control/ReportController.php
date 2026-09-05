<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
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

class ReportController extends Controller
{
    /**
     * Tampilkan halaman Laporan Penjualan (Preview & Filter)
     */
    public function salesReport(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        // Query Medicine dengan agregasi SaleItem pada rentang tanggal
        $query = Medicine::query()
            ->with(['incomingStocks' => function($q) {
                $q->where('status_gate', 'active')->latest();
            }])
            ->withSum(['saleItems as terjual' => function($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function($q) use ($startDate, $endDate) {
                    $q->where('status', 'paid')
                      ->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
                });
            }], 'quantity')
            ->withSum(['saleItems as total_penjualan' => function($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function($q) use ($startDate, $endDate) {
                    $q->where('status', 'paid')
                      ->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
                });
            }], 'subtotal')
            ->orderBy('nama_obat');

        // Total KPIs
        $medicinesAll = $query->get();
        $totalItemTerjual = 0;
        $totalPenjualanKeseluruhan = 0;
        $totalModalKeseluruhan = 0;

        foreach ($medicinesAll as $med) {
            $terjual = (float) ($med->terjual ?? 0);
            $totalPenjualan = (float) ($med->total_penjualan ?? 0);
            
            $latestStock = $med->incomingStocks->first();
            $modal = $latestStock ? $latestStock->calculateHppSatuan() : 0;
            $totalModal = $modal * $terjual;

            $totalItemTerjual += $terjual;
            $totalPenjualanKeseluruhan += $totalPenjualan;
            $totalModalKeseluruhan += $totalModal;
        }
        $totalLabaKeseluruhan = $totalPenjualanKeseluruhan - $totalModalKeseluruhan;

        // Paginate for view
        $medicines = $query->paginate(20)->withQueryString();

        // Transform collection to match our table structure
        $medicines->getCollection()->transform(function ($med) {
            $latestStock = $med->incomingStocks->first();
            $modal = $latestStock ? $latestStock->calculateHppSatuan() : 0;
            
            $terjual = (float) ($med->terjual ?? 0);
            $totalPenjualan = (float) ($med->total_penjualan ?? 0);
            $totalModal = $modal * $terjual;
            $laba = $totalPenjualan - $totalModal;
            
            $sisaStok = (float) $med->stok_total;
            $qtyAwal = $sisaStok + $terjual; // estimate
            $status = $sisaStok > 0 ? 'Tersedia' : 'Pesan';

            return [
                'id' => $med->id,
                'nama_produk' => $med->nama_obat,
                'satuan' => $med->satuan_stok ?: $med->base_unit,
                'modal' => $modal,
                'harga_jual' => (float) $med->selling_price,
                'qty_awal' => $qtyAwal,
                'terjual' => $terjual,
                'sisa_stok' => $sisaStok,
                'total_penjualan' => $totalPenjualan,
                'total_modal' => $totalModal,
                'laba' => $laba,
                'status' => $status,
            ];
        });

        return Inertia::render('Control/Admin/SalesReport', [
            'medicines' => $medicines,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_items' => $totalItemTerjual,
                'total_amount' => $totalPenjualanKeseluruhan,
                'total_modal' => $totalModalKeseluruhan,
                'total_laba' => $totalLabaKeseluruhan,
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

        $medicines = Medicine::query()
            ->with(['incomingStocks' => function($q) {
                $q->where('status_gate', 'active')->latest();
            }])
            ->withSum(['saleItems as terjual' => function($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function($q) use ($startDate, $endDate) {
                    $q->where('status', 'paid')
                      ->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
                });
            }], 'quantity')
            ->withSum(['saleItems as total_penjualan' => function($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function($q) use ($startDate, $endDate) {
                    $q->where('status', 'paid')
                      ->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
                });
            }], 'subtotal')
            ->orderBy('nama_obat')
            ->get();

        if ($medicines->isEmpty()) {
            return back()->with('error', 'Tidak ada data produk.');
        }

        // Tentukan nama file
        $fileName = 'rekap-penjualan-' . $startDate . '-sd-' . $endDate . '.xlsx';

        // Persiapkan stream HTTP untuk file .xlsx
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile('php://output');

        // ==== STYLES ====
        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('002060') // Primary Blue
            ->setCellAlignment(CellAlignment::CENTER);
            
        $titleStyle = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);
            
        $titleStyle14 = (clone $titleStyle)->setFontSize(14);
        
        $currencyStyle = (new Style())
            ->setFormat('Rp #,##0');
            
        $boldCurrencyStyle = (new Style())
            ->setFontBold()
            ->setFormat('Rp #,##0');
            
        $boldStyle = (new Style())->setFontBold();
        
        $centerStyle = (new Style())->setCellAlignment(CellAlignment::CENTER);
        $rightStyle = (new Style())->setCellAlignment(CellAlignment::RIGHT);

        $statusPesanStyle = (new Style())->setBackgroundColor('FFC7CE')->setFontColor('9C0006')->setCellAlignment(CellAlignment::CENTER);
        $statusTersediaStyle = (new Style())->setBackgroundColor('C6EFCE')->setFontColor('006100')->setCellAlignment(CellAlignment::CENTER);

        // Merge cells for headers (columns 0 to 11 = A to L)
        $options->mergeCells(0, 1, 11, 1, 0); // Baris 1
        $options->mergeCells(0, 2, 11, 2, 0); // Baris 2
        $options->mergeCells(0, 3, 11, 3, 0); // Baris 3

        // ==========================================
        // SHEET 1: REKAP PENJUALAN
        // ==========================================
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Rekap Penjualan');
        
        // Set lebar kolom
        $options->setColumnWidth(6, 1);   // No
        $options->setColumnWidth(30, 2);  // Nama Produk
        $options->setColumnWidth(10, 3);  // Satuan
        $options->setColumnWidth(15, 4);  // Modal
        $options->setColumnWidth(15, 5);  // Harga Jual
        $options->setColumnWidth(12, 6);  // Qty Awal
        $options->setColumnWidth(12, 7);  // Terjual
        $options->setColumnWidth(12, 8);  // Sisa Stok
        $options->setColumnWidth(20, 9);  // Total Penjualan
        $options->setColumnWidth(20, 10); // Modal
        $options->setColumnWidth(20, 11); // Laba
        $options->setColumnWidth(12, 12); // Status
        
        // --- HEADER ---
        $writer->addRow(new Row([Cell::fromValue('APOTEK KINANTHI', $titleStyle14)]));
        $writer->addRow(new Row([Cell::fromValue('Jl. Dieng No.24, Wonosobo Timur, Kec. Wonosobo', $titleStyle)]));
        
        $startDateFormatted = Carbon::parse($startDate)->format('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->format('d F Y');
        $writer->addRow(new Row([Cell::fromValue("REKAP PENJUALAN TGL $startDateFormatted - $endDateFormatted", $titleStyle)]));
        $writer->addRow(new Row([])); // Baris kosong

        // Menghitung totals
        $totalPenjualanKeseluruhan = 0;
        $totalModalKeseluruhan = 0;
        $totalItemTerjual = 0;

        foreach ($medicines as $med) {
            $terjual = (float) ($med->terjual ?? 0);
            $totalPenjualan = (float) ($med->total_penjualan ?? 0);
            
            $latestStock = $med->incomingStocks->first();
            $modal = $latestStock ? $latestStock->calculateHppSatuan() : 0;
            $totalModal = $modal * $terjual;

            $totalItemTerjual += $terjual;
            $totalPenjualanKeseluruhan += $totalPenjualan;
            $totalModalKeseluruhan += $totalModal;
        }
        $totalLabaKeseluruhan = $totalPenjualanKeseluruhan - $totalModalKeseluruhan;

        // --- SUMMARY KANAN ATAS ---
        $writer->addRow(new Row([
            Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), 
            Cell::fromValue('Total Penjualan', $boldStyle), Cell::fromValue($totalPenjualanKeseluruhan, $boldCurrencyStyle)
        ]));
        $writer->addRow(new Row([
            Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), 
            Cell::fromValue('Total Modal', $boldStyle), Cell::fromValue($totalModalKeseluruhan, $boldCurrencyStyle)
        ]));
        $writer->addRow(new Row([
            Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), 
            Cell::fromValue('Total Laba', $boldStyle), Cell::fromValue($totalLabaKeseluruhan, $boldCurrencyStyle)
        ]));
        $writer->addRow(new Row([])); // Baris kosong

        // Freeze Pane
        $sheetView = new SheetView();
        $sheetView->setFreezeRow(9);
        $sheet->setSheetView($sheetView);
        
        // Header Tabel
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
        
        // AutoFilter
        $autoFilter = new AutoFilter(0, 9, 11, 9 + $medicines->count());
        $sheet->setAutoFilter($autoFilter);

        // Data
        $no = 1;
        $sumQtyAwal = 0;
        $sumTerjual = 0;
        $sumSisaStok = 0;

        foreach ($medicines as $med) {
            $latestStock = $med->incomingStocks->first();
            $modal = $latestStock ? $latestStock->calculateHppSatuan() : 0;
            
            $terjual = (float) ($med->terjual ?? 0);
            $totalPenjualan = (float) ($med->total_penjualan ?? 0);
            $totalModal = $modal * $terjual;
            $laba = $totalPenjualan - $totalModal;
            
            $sisaStok = (float) $med->stok_total;
            $qtyAwal = $sisaStok + $terjual;
            $status = $sisaStok > 0 ? 'Tersedia' : 'Pesan';

            $sumQtyAwal += $qtyAwal;
            $sumTerjual += $terjual;
            $sumSisaStok += $sisaStok;

            $activeStatusStyle = $sisaStok > 0 ? $statusTersediaStyle : $statusPesanStyle;

            $writer->addRow(new Row([
                Cell::fromValue($no++, $centerStyle),
                Cell::fromValue($med->nama_obat),
                Cell::fromValue($med->satuan_stok ?: $med->base_unit, $centerStyle),
                Cell::fromValue((float) $modal, $currencyStyle),
                Cell::fromValue((float) $med->selling_price, $currencyStyle),
                Cell::fromValue((float) $qtyAwal, $centerStyle),
                Cell::fromValue((float) $terjual, $centerStyle),
                Cell::fromValue((float) $sisaStok, $centerStyle),
                Cell::fromValue((float) $totalPenjualan, $currencyStyle),
                Cell::fromValue((float) $totalModal, $currencyStyle),
                Cell::fromValue((float) $laba, $currencyStyle),
                Cell::fromValue($status, $activeStatusStyle),
            ]));
        }
        
        // Baris Total Bawah
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

        // Ambil semua transaksi dalam rentang waktu
        $sales = \App\Models\Sale::with([
                'saleItems.medicine.incomingStocks' => function($q) {
                    $q->where('status_gate', 'active')->latest();
                }
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
                    $hppSatuan = $latestStock ? $latestStock->calculateHppSatuan() : 0;
                    $itemModal = ($hppSatuan * $item->quantity);
                    $modal += $itemModal;
                    
                    // Sisa stok riil dari medicine
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
            
            $subtotal = $sale->total_amount + $sale->discount_amount;
            $laba = $sale->total_amount - $modal;

            return [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number ?? '-',
                'created_at' => $sale->created_at,
                'kasir_name' => $sale->kasir ? $sale->kasir->name : 'Unknown',
                'subtotal' => $subtotal,
                'discount_amount' => $sale->discount_amount,
                'total_amount' => $sale->total_amount,
                'modal' => $modal,
                'laba' => $laba,
                'items' => $items,
            ];
        });

        // Summary KPIs
        $totalTransactions = $transactions->count();
        $totalSales = $transactions->sum('total_amount');
        $totalDiscount = $transactions->sum('discount_amount');
        $totalLaba = $transactions->sum('laba');

        return Inertia::render('Control/Admin/CashierMonitoring', [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'kpis' => [
                'total_transactions' => $totalTransactions,
                'total_sales' => $totalSales,
                'total_discount' => $totalDiscount,
                'total_laba' => $totalLaba,
            ],
            'transactions' => $transactions,
        ]);
    }
}
