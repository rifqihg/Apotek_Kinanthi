<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
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

class AdminCashierExportController extends Controller
{
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
     * Export Jurnal Transaksi Kasir ke Excel (OpenSpout)
     */
    public function exportCashierMonitoring(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $salesQuery = Sale::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('kasir:id,name')
            ->orderBy('created_at', 'desc');

        if (!$salesQuery->exists()) {
            return back()->with('error', 'Tidak ada data transaksi kasir.');
        }

        $fileName = 'jurnal-transaksi-kasir-' . $startDate . '-sd-' . $endDate . '.xlsx';

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

        // Status badges
        $statusLunasStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('C6EFCE')->setFontColor('006100')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $statusVoidStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('FFC7CE')->setFontColor('9C0006')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);

        // Summary Total & Section Styles (Bawah Garis Tunggal Solid, Bukan Ganda)
        $totalLabelStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $totalCenterStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setCellAlignment(CellAlignment::CENTER)->setBorder($allBorder);
        $totalCurrencyStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setBackgroundColor('F1F5F9')->setFormat('"Rp" #,##0')->setBorder($allBorder);

        $boldStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11);
        $boldCurrencyStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setFormat('"Rp" #,##0');

        // Merge Kop Surat & Metadata (Kolom A-M: 0 sampai 12)
        $options->mergeCells(0, 1, 12, 1, 0);
        $options->mergeCells(0, 2, 12, 2, 0);
        $options->mergeCells(0, 3, 12, 3, 0);
        $options->mergeCells(0, 4, 12, 4, 0);
        $options->mergeCells(0, 5, 12, 5, 0);
        $options->mergeCells(0, 6, 12, 6, 0);
        $options->mergeCells(0, 7, 12, 7, 0);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Jurnal Kasir');

        $colWidths = [
            1  => 8,   // NO (memberi ruang untuk tombol filter)
            2  => 20,  // WAKTU
            3  => 18,  // NO. FAKTUR
            4  => 18,  // KASIR
            5  => 12,  // JENIS
            6  => 14,  // METODE BAYAR
            7  => 16,  // SUBTOTAL
            8  => 14,  // DISKON
            9  => 16,  // TOTAL AKHIR
            10 => 16,  // DITERIMA
            11 => 16,  // KEMBALIAN
            12 => 14,  // STATUS
            13 => 28,  // CATATAN / VOID
        ];
        foreach ($colWidths as $col => $w) {
            $options->setColumnWidth($w, $col);
        }

        // 1. KOP SURAT FORMAL APOTEK
        $writer->addRow(new Row([Cell::fromValue('APOTEK KINANTHI', $kopTitleStyle)]));
        $writer->addRow(new Row([Cell::fromValue('Jl. Raya Wonosobo - Kertek KM 03, Sidojoyo, Wonosobo, Jawa Tengah 56315', $kopAddressStyle)]));
        $writer->addRow(new Row([Cell::fromValue('Telp/WhatsApp: 0812-3456-7890 | Email: apotek.kinanthi@gmail.com', $kopAddressStyle)]));
        $writer->addRow(new Row(array_fill(0, 13, Cell::fromValue('', $dividerStyle))));

        // 2. METADATA LAPORAN
        $startDateFormatted = Carbon::parse($startDate)->translatedFormat('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->translatedFormat('d F Y');
        $printedBy = auth()->user()?->name ?? 'Administrator';

        $writer->addRow(new Row([Cell::fromValue('JURNAL TRANSAKSI KASIR & REKONSILIASI KAS', $docTitleStyle)]));
        $writer->addRow(new Row([Cell::fromValue("Periode: $startDateFormatted s/d $endDateFormatted", $docMetaStyle)]));
        $writer->addRow(new Row([Cell::fromValue("Dicetak pada: " . Carbon::now()->format('d/m/Y H:i:s') . " WIB oleh: $printedBy", $docMetaStyle)]));
        $writer->addRow(new Row([])); // Baris 8: Spacing Kosong

        // 3. HEADER TABEL DATA RESMI (Row 9)
        $writer->addRow(new Row([
            Cell::fromValue('NO', $headerStyle),
            Cell::fromValue('WAKTU', $headerStyle),
            Cell::fromValue('NO. FAKTUR', $headerStyle),
            Cell::fromValue('KASIR', $headerStyle),
            Cell::fromValue('JENIS', $headerStyle),
            Cell::fromValue('METODE BAYAR', $headerStyle),
            Cell::fromValue('SUBTOTAL', $headerStyle),
            Cell::fromValue('DISKON', $headerStyle),
            Cell::fromValue('TOTAL AKHIR', $headerStyle),
            Cell::fromValue('DITERIMA', $headerStyle),
            Cell::fromValue('KEMBALIAN', $headerStyle),
            Cell::fromValue('STATUS', $headerStyle),
            Cell::fromValue('CATATAN / VOID', $headerStyle),
        ]));

        $totalRows = $salesQuery->count();
        // AutoFilter eksklusif pada baris header (Row 9) hingga baris akhir data
        $sheet->setAutoFilter(new AutoFilter(0, 9, 12, 9 + $totalRows));

        $no = 1;
        $sumSubtotal = 0;
        $sumDiskon = 0;
        $sumTotal = 0;

        foreach ($salesQuery->cursor() as $sale) {
            $subtotal = (float) ($sale->total_amount + $sale->discount_amount);
            $diskon = (float) $sale->discount_amount;
            $total = (float) $sale->total_amount;

            if ($sale->status === 'paid') {
                $sumSubtotal += $subtotal;
                $sumDiskon += $diskon;
                $sumTotal += $total;
            }

            $isEven = ($no % 2 === 0);
            $cStyle = $isEven ? $centerZebraStyle : $centerStyle;
            $tStyle = $isEven ? $textZebraStyle : $textStyle;
            $curStyle = $isEven ? $currencyZebraStyle : $currencyStyle;

            $statusText = $sale->status === 'paid' ? 'Lunas' : 'Batal/Void';
            $statusStyle = $sale->status === 'paid' ? $statusLunasStyle : $statusVoidStyle;

            $writer->addRow(new Row([
                Cell::fromValue($no++, $cStyle),
                Cell::fromValue(Carbon::parse($sale->created_at)->format('d/m/Y H:i:s'), $cStyle),
                Cell::fromValue($sale->sale_number, $cStyle),
                Cell::fromValue($sale->kasir ? $sale->kasir->name : '-', $tStyle),
                Cell::fromValue(strtoupper($sale->sale_type ?? 'OTC'), $cStyle),
                Cell::fromValue(ucfirst($sale->payment_method ?? 'Tunai'), $cStyle),
                Cell::fromValue((float) round($subtotal), $curStyle),
                Cell::fromValue((float) round($diskon), $curStyle),
                Cell::fromValue((float) round($total), $curStyle),
                Cell::fromValue((float) round($sale->paid_amount), $curStyle),
                Cell::fromValue((float) round($sale->change_amount), $curStyle),
                Cell::fromValue($statusText, $statusStyle),
                Cell::fromValue($sale->notes ?: '-', $tStyle),
            ]));
        }

        // 4. BARIS TOTAL / SUMMARY
        $writer->addRow(new Row([
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('TOTAL (LUNAS)', $totalLabelStyle),
            Cell::fromValue((float) round($sumSubtotal), $totalCurrencyStyle),
            Cell::fromValue((float) round($sumDiskon), $totalCurrencyStyle),
            Cell::fromValue((float) round($sumTotal), $totalCurrencyStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
            Cell::fromValue('', $totalCenterStyle),
        ]));

        // 5. RINGKASAN REKONSILIASI KAS (SETELAH TABEL DATA)
        $writer->addRow(new Row([])); // Row 10 + $no: Spacing Kosong
        $summary = $this->calculateCashierKpis($startDate, $endDate);
        
        $rekapLabelCols = array_fill(0, 7, Cell::fromValue(''));
        $writer->addRow(new Row(array_merge($rekapLabelCols, [Cell::fromValue('Setoran Tunai (Laci)', $boldStyle), Cell::fromValue((float) round($summary['total_tunai']), $boldCurrencyStyle)])));
        $writer->addRow(new Row(array_merge($rekapLabelCols, [Cell::fromValue('Non-Tunai (QRIS/Trf)', $boldStyle), Cell::fromValue((float) round($summary['total_non_tunai']), $boldCurrencyStyle)])));
        $writer->addRow(new Row(array_merge($rekapLabelCols, [Cell::fromValue('Total Diskon', $boldStyle), Cell::fromValue((float) round($summary['total_discount']), $boldCurrencyStyle)])));
        $writer->addRow(new Row(array_merge($rekapLabelCols, [Cell::fromValue('Total Void (Batal)', $boldStyle), Cell::fromValue($summary['total_void'] . ' Nota (Rp ' . number_format($summary['total_void_amount'], 0, ',', '.') . ')', $boldStyle)])));
        $writer->addRow(new Row(array_merge($rekapLabelCols, [Cell::fromValue('Grand Total Omzet Bersih', $boldStyle), Cell::fromValue((float) round($summary['total_omset']), $boldCurrencyStyle)])));

        // 6. BLOK PENGESAHAN & TANDA TANGAN (SIGN-OFF BLOCK)
        $writer->addRow(new Row([])); // Lewati 2 baris kosong
        $writer->addRow(new Row([]));

        // Koordinat penggabungan sel tanda tangan lurus pada kolom J s/d M (index 9 sampai 12)
        $signOffRow1 = 18 + $no;
        $signOffRow2 = 19 + $no;
        $signOffRow3 = 24 + $no;
        $options->mergeCells(9, $signOffRow1, 12, $signOffRow1, 0);
        $options->mergeCells(9, $signOffRow2, 12, $signOffRow2, 0);
        $options->mergeCells(9, $signOffRow3, 12, $signOffRow3, 0);

        $signOffStyle = (new Style())->setFontName('Calibri')->setFontSize(11)->setCellAlignment(CellAlignment::CENTER);
        $signOffBoldStyle = (new Style())->setFontName('Calibri')->setFontBold()->setFontSize(11)->setCellAlignment(CellAlignment::CENTER);

        $emptyCols = array_fill(0, 9, Cell::fromValue(''));

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
     * Export Jurnal Transaksi Kasir & Rekonsiliasi Kas ke PDF resmi A4 Landscape
     */
    public function exportCashierMonitoringPdf(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        $salesQuery = Sale::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('kasir:id,name')
            ->orderBy('created_at', 'desc');

        if (!$salesQuery->exists()) {
            return back()->with('error', 'Tidak ada data transaksi kasir.');
        }

        $sales = $salesQuery->get();

        $sumSubtotal = 0;
        $sumDiskon = 0;
        $sumTotal = 0;

        foreach ($sales as $sale) {
            if ($sale->status === 'paid') {
                $sumSubtotal += ($sale->total_amount + $sale->discount_amount);
                $sumDiskon += $sale->discount_amount;
                $sumTotal += $sale->total_amount;
            }
        }

        $summary = $this->calculateCashierKpis($startDate, $endDate);
        $summary['sum_subtotal'] = $sumSubtotal;
        $summary['sum_diskon'] = $sumDiskon;
        $summary['sum_total'] = $sumTotal;

        $startDateFormatted = Carbon::parse($startDate)->translatedFormat('d F Y');
        $endDateFormatted = Carbon::parse($endDate)->translatedFormat('d F Y');

        $pdf = Pdf::loadView('exports.cashier-monitoring-pdf', [
            'sales'          => $sales,
            'kpis'           => $summary,
            'startDate'      => $startDateFormatted,
            'endDate'        => $endDateFormatted,
            'printedBy'      => auth()->user()?->name ?? 'Administrator',
            'printedAt'      => Carbon::now()->format('d/m/Y H:i:s'),
            'logoKinanthi'   => $this->getLogoBase64(),
            'logoKemenkes'   => $this->getKemenkesLogoBase64(),
            'logoBase64'     => $this->getLogoBase64(),
            'dateNow'        => Carbon::now()->translatedFormat('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('jurnal-transaksi-kasir-' . $startDate . '-sd-' . $endDate . '.pdf');
    }
}
