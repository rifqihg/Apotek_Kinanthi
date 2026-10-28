<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\Medicine;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\IncomingStock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class AdminSalesExportController extends Controller
{
    /**
     * Query dasar rekap penjualan produk obat jadi
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
     * Query dasar rekap penjualan produk racikan
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
     * Kalkulasi metrik penjualan satuan obat dengan resolusi nomor batch fisik
     */
    private function calculateMedicineMetric($med, ?IncomingStock $latestStock = null): array
    {
        $stock = $latestStock ?? $med->latestActiveIncomingStock;
        $modal = $stock ? (float) $stock->calculateHppSatuan() : 0;
        $threshold = (int) ($med->threshold_min ?? 10);

        // Resolusi nomor batch fisik riil gudang
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
     * Hitung agregasi KPI penjualan
     */
    private function calculateSalesSummary(string $startDate, string $endDate): array
    {
        $dateFilter = fn($sq) => $sq->where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

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

        $totalTerjual = $medTerjual + $cpTerjual;
        $grossAmount = $medAmount + $cpAmount;

        $totalDiscount = (float) \Illuminate\Support\Facades\DB::table('sales')
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('discount_amount');

        $netAmount = max(0, $grossAmount - $totalDiscount);
        $totalModal = $medModal + $cpModal;
        $totalLaba = $netAmount - $totalModal;

        return [
            'total_items' => $totalTerjual,
            'gross_amount' => $grossAmount,
            'total_discount' => $totalDiscount,
            'total_amount' => $netAmount,
            'total_modal' => $totalModal,
            'total_laba' => $totalLaba,
        ];
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

    /**
     * Export Rekapitulasi Penjualan Produk ke Excel (OpenSpout)
     */
    public function exportSales(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $salesQuery = $this->getSalesQuery($startDate, $endDate);
        if (!$salesQuery->exists() && !CompoundedProduct::exists()) {
            return back()->with('error', 'Tidak ada data penjualan untuk diekspor.');
        }

        $fileName = 'laporan-rekap-penjualan-produk-' . $startDate . '-sd-' . $endDate . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile('php://output');

        // Color Palette & Typography
        $slateColor = '0F172A';
        $darkTextColor = '1E293B';

        // Kop Surat Styles
        $kopTitleStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(16)->setFontColor($slateColor)->setCellAlignment(CellAlignment::CENTER);
        $kopAddressStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setFontColor($darkTextColor)->setCellAlignment(CellAlignment::CENTER);
        $dividerBorder = new Border(new BorderPart(Border::BOTTOM, '0F172A', Border::WIDTH_MEDIUM, Border::STYLE_SOLID));
        $dividerStyle = (new Style())->setBorder($dividerBorder);

        // Metadata Laporan Styles
        $docTitleStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(14)->setFontColor($slateColor)->setCellAlignment(CellAlignment::CENTER);
        $docMetaStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setFontColor($darkTextColor)->setCellAlignment(CellAlignment::CENTER);

        // Standard All Borders Style (Batas Solid Hitam Tegas & Rapi untuk Seluruh Sel Tabel)
        $allBorder = new Border(
            new BorderPart(Border::TOP, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, '000000', Border::WIDTH_THIN, Border::STYLE_SOLID)
        );

        $headerStyle = (new Style())
            ->setFontName('Calibri')
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor($slateColor)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($allBorder);

        // Data Row Styles (All Borders)
        $textStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setBorder($allBorder);
        $centerStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $currencyStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setFormat('"Rp" #,##0')->setBorder($allBorder);

        // Even rows (Zebra striping #F8FAFC)
        $textZebraStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setBackgroundColor('F8FAFC')->setBorder($allBorder);
        $centerZebraStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setBackgroundColor('F8FAFC')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $currencyZebraStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setBackgroundColor('F8FAFC')->setFormat('"Rp" #,##0')->setBorder($allBorder);

        // Semantic status badges
        $statusAmanStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('C6EFCE')->setFontColor('006100')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $statusMenipisStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('FFEB9C')->setFontColor('9C6500')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $statusHabisStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('FFC7CE')->setFontColor('9C0006')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);

        // Summary Total Style (Bawah Garis Tunggal Solid, Bukan Ganda)
        $totalLabelStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $totalCenterStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $totalCurrencyStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setFormat('"Rp" #,##0')->setBorder($allBorder);

        // Merge Kop Surat & Metadata (Kolom A-O: 0 sampai 14)
        $options->mergeCells(0, 1, 14, 1, 0);
        $options->mergeCells(0, 2, 14, 2, 0);
        $options->mergeCells(0, 3, 14, 3, 0);
        $options->mergeCells(0, 4, 14, 4, 0);
        $options->mergeCells(0, 5, 14, 5, 0);
        $options->mergeCells(0, 6, 14, 6, 0);
        $options->mergeCells(0, 7, 14, 7, 0);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Rekap Penjualan');

        // Lebar Kolom
        $colWidths = [
            1  => 8,   // NO (memberi ruang untuk tombol filter)
            2  => 18,  // KODE / NO. BATCH
            3  => 32,  // NAMA PRODUK
            4  => 14,  // KATEGORI
            5  => 12,  // SATUAN
            6  => 16,  // HPP MODAL
            7  => 16,  // HARGA JUAL
            8  => 12,  // QTY AWAL
            9  => 12,  // TERJUAL
            10 => 12,  // SISA STOK
            11 => 18,  // TOTAL OMZET
            12 => 18,  // TOTAL MODAL
            13 => 18,  // LABA KOTOR
            14 => 12,  // MARGIN %
            15 => 20,  // STATUS PENGADAAN
        ];
        foreach ($colWidths as $col => $w) {
            $options->setColumnWidth($w, $col);
        }

        // 1. KOP SURAT FORMAL APOTEK
        $writer->addRow(new Row([Cell::fromValue('APOTEK KINANTHI', $kopTitleStyle)]));
        $writer->addRow(new Row([Cell::fromValue('Jl. Raya Wonosobo - Kertek KM 03, Sidojoyo, Wonosobo, Jawa Tengah 56315', $kopAddressStyle)]));
        $writer->addRow(new Row([Cell::fromValue('Telp/WhatsApp: 0812-3456-7890 | Email: apotek.kinanthi@gmail.com', $kopAddressStyle)]));
        $writer->addRow(new Row(array_fill(0, 15, Cell::fromValue('', $dividerStyle))));

        // 2. METADATA LAPORAN
        $startDateFormatted = Carbon::parse($startDate)->translatedFormat('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->translatedFormat('d F Y');
        $printedBy = auth()->user()?->name ?? 'Administrator';

        $writer->addRow(new Row([Cell::fromValue('LAPORAN REKAPITULASI PENJUALAN PRODUK & ANALISIS STOK', $docTitleStyle)]));
        $writer->addRow(new Row([Cell::fromValue("Periode: $startDateFormatted s/d $endDateFormatted", $docMetaStyle)]));
        $writer->addRow(new Row([Cell::fromValue("Dicetak pada: " . Carbon::now()->format('d/m/Y H:i:s') . " WIB oleh: $printedBy", $docMetaStyle)]));
        $writer->addRow(new Row([])); // Baris 8: Spacing Kosong

        // 3. HEADER TABEL DATA RESMI (Row 9)
        $writer->addRow(new Row([
            Cell::fromValue('NO', $headerStyle),
            Cell::fromValue('KODE / NO. BATCH', $headerStyle),
            Cell::fromValue('NAMA PRODUK', $headerStyle),
            Cell::fromValue('KATEGORI', $headerStyle),
            Cell::fromValue('SATUAN', $headerStyle),
            Cell::fromValue('HPP MODAL', $headerStyle),
            Cell::fromValue('HARGA JUAL', $headerStyle),
            Cell::fromValue('QTY AWAL', $headerStyle),
            Cell::fromValue('TERJUAL', $headerStyle),
            Cell::fromValue('SISA STOK', $headerStyle),
            Cell::fromValue('TOTAL OMZET', $headerStyle),
            Cell::fromValue('TOTAL MODAL', $headerStyle),
            Cell::fromValue('LABA KOTOR', $headerStyle),
            Cell::fromValue('MARGIN %', $headerStyle),
            Cell::fromValue('STATUS PENGADAAN', $headerStyle),
        ]));

        $latestStocks = IncomingStock::where('status_gate', 'active')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('medicine_id')
            ->keyBy('medicine_id');

        $totalRows = Medicine::count() + CompoundedProduct::count();
        // AutoFilter eksklusif pada baris header (Row 9) hingga baris akhir data
        $sheet->setAutoFilter(new AutoFilter(0, 9, 14, 9 + $totalRows));

        $no = 1;
        $sumQtyAwal = 0;
        $sumTerjual = 0;
        $sumSisaStok = 0;

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

            $isEven = ($no % 2 === 0);
            $cStyle = $isEven ? $centerZebraStyle : $centerStyle;
            $tStyle = $isEven ? $textZebraStyle : $textStyle;
            $curStyle = $isEven ? $currencyZebraStyle : $currencyStyle;

            $activeStatusStyle = match ($row['status']) {
                'Habis' => $statusHabisStyle,
                'Menipis' => $statusMenipisStyle,
                default => $statusAmanStyle,
            };

            $marginFormatted = number_format((float) $row['margin_percentage'], 1, ',', '.') . '%';

            $writer->addRow(new Row([
                Cell::fromValue($no++, $cStyle),
                Cell::fromValue($row['kode_obat'], $cStyle),
                Cell::fromValue($row['nama_produk'], $tStyle),
                Cell::fromValue($row['kategori'], $cStyle),
                Cell::fromValue($row['satuan'] ?: 'Pcs', $cStyle),
                Cell::fromValue((float) round($row['modal']), $curStyle),
                Cell::fromValue((float) round($row['harga_jual']), $curStyle),
                Cell::fromValue((int) round($row['qty_awal']), $cStyle),
                Cell::fromValue((int) round($row['terjual']), $cStyle),
                Cell::fromValue((int) round($row['sisa_stok']), $cStyle),
                Cell::fromValue((float) round($row['total_penjualan']), $curStyle),
                Cell::fromValue((float) round($row['total_modal']), $curStyle),
                Cell::fromValue((float) round($row['laba']), $curStyle),
                Cell::fromValue($marginFormatted, $cStyle),
                Cell::fromValue($row['status'], $activeStatusStyle),
            ]));
        }

        // 4. BARIS TOTAL / SUMMARY
        $summary = $this->calculateSalesSummary($startDate, $endDate);
        $totalPenjualanKeseluruhan = (float) round($summary['total_amount']);
        $totalModalKeseluruhan = (float) round($summary['total_modal']);
        $totalLabaKeseluruhan = (float) round($summary['total_laba']);

        $writer->addRow(new Row([
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('TOTAL', $totalLabelStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue((int) round($sumQtyAwal), $totalCenterStyle),
            Cell::fromValue((int) round($sumTerjual), $totalCenterStyle),
            Cell::fromValue((int) round($sumSisaStok), $totalCenterStyle),
            Cell::fromValue($totalPenjualanKeseluruhan, $totalCurrencyStyle),
            Cell::fromValue($totalModalKeseluruhan, $totalCurrencyStyle),
            Cell::fromValue($totalLabaKeseluruhan, $totalCurrencyStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
        ]));

        // 5. BLOK PENGESAHAN & TANDA TANGAN (SIGN-OFF BLOCK)
        $writer->addRow(new Row([])); // Lewati 2 baris kosong
        $writer->addRow(new Row([]));

        // Koordinat penggabungan sel tanda tangan lurus pada kolom L s/d O (index 11 sampai 14)
        $signOffRow1 = 12 + $no;
        $signOffRow2 = 13 + $no;
        $signOffRow3 = 18 + $no;
        $options->mergeCells(11, $signOffRow1, 14, $signOffRow1, 0);
        $options->mergeCells(11, $signOffRow2, 14, $signOffRow2, 0);
        $options->mergeCells(11, $signOffRow3, 14, $signOffRow3, 0);

        $signOffStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setCellAlignment(CellAlignment::CENTER);
        $signOffBoldStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setCellAlignment(CellAlignment::CENTER);

        $emptyCols = array_fill(0, 11, Cell::fromValue(''));

        $writer->addRow(new Row(array_merge($emptyCols, [Cell::fromValue('Wonosobo, ' . Carbon::now()->translatedFormat('d F Y'), $signOffStyle)])));
        $writer->addRow(new Row(array_merge($emptyCols, [Cell::fromValue('Apoteker Pengelola Apotek (APA),', $signOffBoldStyle)])));
        $writer->addRow(new Row([]));
        $writer->addRow(new Row([]));
        $writer->addRow(new Row([]));
        $writer->addRow(new Row([]));
        $writer->addRow(new Row(array_merge($emptyCols, [Cell::fromValue('( Apt. Ika Kantiningtyas,S.Farm,CIPC )', $signOffBoldStyle)])));

        $writer->close();
        exit;
    }

    /**
     * Export Rekapitulasi Penjualan Produk ke PDF resmi A4 Landscape
     */
    public function exportSalesPdf(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $salesQuery = $this->getSalesQuery($startDate, $endDate);
        if (!$salesQuery->exists() && !CompoundedProduct::exists()) {
            return back()->with('error', 'Tidak ada data penjualan untuk diekspor.');
        }

        $latestStocks = IncomingStock::where('status_gate', 'active')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('medicine_id')
            ->keyBy('medicine_id');

        $items = [];
        $sumQtyAwal = 0;
        $sumTerjual = 0;
        $sumSisaStok = 0;

        foreach ($salesQuery->cursor() as $med) {
            $metric = $this->calculateMedicineMetric($med, $latestStocks->get($med->id));
            $sumQtyAwal += $metric['qty_awal'];
            $sumTerjual += $metric['terjual'];
            $sumSisaStok += $metric['sisa_stok'];
            $items[] = $metric;
        }

        foreach ($this->getCompoundedSalesQuery($startDate, $endDate)->cursor() as $cp) {
            $metric = $this->calculateCompoundedMetric($cp);
            $sumQtyAwal += $metric['qty_awal'];
            $sumTerjual += $metric['terjual'];
            $sumSisaStok += $metric['sisa_stok'];
            $items[] = $metric;
        }

        $summary = $this->calculateSalesSummary($startDate, $endDate);
        $summary['sum_qty_awal'] = $sumQtyAwal;
        $summary['sum_terjual'] = $sumTerjual;
        $summary['sum_sisa_stok'] = $sumSisaStok;

        $startDateFormatted = Carbon::parse($startDate)->translatedFormat('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->translatedFormat('d F Y');

        $pdf = Pdf::loadView('exports.sales-report-pdf', [
            'items'          => $items,
            'summary'        => $summary,
            'startDate'      => $startDateFormatted,
            'endDate'        => $endDateFormatted,
            'printedBy'      => auth()->user()?->name ?? 'Administrator',
            'printedAt'      => Carbon::now()->format('d/m/Y H:i:s'),
            'logoKinanthi'   => $this->getLogoBase64(),
            'logoKemenkes'   => $this->getKemenkesLogoBase64(),
            'logoBase64'     => $this->getLogoBase64(),
            'dateNow'        => Carbon::now()->translatedFormat('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-rekap-penjualan-produk-' . $startDate . '-sd-' . $endDate . '.pdf');
    }
}
