<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jurnal Transaksi Kasir &amp; Rekonsiliasi Kas</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 8pt;
            line-height: 1.5;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        /* ─── KOP SURAT BILATERAL ─── */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .kop-logo-left {
            width: 85px;
            vertical-align: middle;
            text-align: left;
        }
        .kop-logo-left img {
            max-height: 70px;
            max-width: 85px;
            width: auto;
            height: auto;
        }
        .kop-logo-right {
            width: 90px;
            vertical-align: middle;
            text-align: right;
        }
        .kop-logo-right img {
            max-height: 68px;
            max-width: 90px;
            width: auto;
            height: auto;
        }
        .kop-center {
            vertical-align: middle;
            text-align: center;
            padding: 0 10px;
        }
        .kop-name {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            line-height: 1.25;
            margin: 0 0 2px 0;
        }
        .kop-tagline {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.25;
            margin: 0 0 3px 0;
        }
        .kop-address {
            font-size: 12pt;
            line-height: 1.25;
            margin: 0 0 2px 0;
        }
        .kop-contact {
            font-size: 12pt;
            line-height: 1.25;
            margin: 0;
            padding-bottom: 8px;
        }
        .kop-divider {
            border-bottom: 3px double #000000;
            margin-top: 0;
            margin-bottom: 15px;
            height: 0;
        }

        /* ─── JUDUL & METADATA DOKUMEN ─── */
        .doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.5;
            margin: 0 0 15px 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0 0 15px 0;
        }
        .meta-left {
            text-align: left;
            vertical-align: bottom;
            margin: 0;
            padding: 0;
        }
        .meta-right {
            text-align: right;
            vertical-align: bottom;
            margin: 0;
            padding: 0;
            color: #000000;
        }

        /* ─── TABEL REKAPITULASI EKSEKUTIF 5 KOLOM ─── */
        .rekap-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 0.5px solid #000000;
        }
        .rekap-table th {
            border: 0.5px solid #000000;
            background-color: #F1F5F9;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: center;
            padding: 4px 6px;
            color: #000000;
            text-transform: uppercase;
        }
        .rekap-table td {
            border: 0.5px solid #000000;
            background-color: #FFFFFF;
            font-size: 10.5pt;
            font-weight: bold;
            text-align: center;
            padding: 5px 6px;
            color: #000000;
        }

        /* ─── TABEL DATA AUDIT FORMAL ─── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #E2E8F0;
            color: #000000;
            font-size: 8.5pt;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 3px 2px;
            border: 0.5px solid #000000;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .data-table td {
            background-color: #FFFFFF;
            color: #000000;
            font-size: 9pt;
            padding: 3px 3px;
            border: 0.5px solid #000000;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        /* ─── BARIS TOTAL KESELURUHAN ─── */
        .total-row td {
            background-color: #F1F5F9 !important;
            font-weight: bold;
            font-size: 9pt;
            color: #000000;
            border: 0.5px solid #000000 !important;
            padding: 3px 3px;
            white-space: nowrap;
        }

        /* ─── BLOK PENGESAHAN (SIGN-OFF) ─── */
        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
            margin-top: 14px;
        }
        .signoff-box {
            width: 300px;
            text-align: center;
            float: right;
        }
        .signoff-date {
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
        }
        .signoff-role {
            font-size: 12pt;
            font-weight: bold;
            line-height: 1.5;
            margin: 0;
        }
        .signoff-space {
            height: 75px;
        }
        .signoff-name {
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            line-height: 1.5;
            margin: 0;
        }

        /* ─── PRINT FOOTER ─── */
        .page-footer {
            position: fixed;
            bottom: -6mm;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #64748B;
            text-align: center;
            border-top: 0.5px solid #CBD5E1;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="page-footer">
        Dokumen Laporan Audit Resmi &bull; Sistem Informasi Manajemen Apotek Kinanthi &bull; Dicetak secara otomatis
    </div>

    <!-- KOP SURAT BILATERAL RESMI -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo-left">
                @if(!empty($logoKinanthi))
                    <img src="{{ $logoKinanthi }}" alt="Logo Apotek Kinanthi">
                @elseif(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo Apotek Kinanthi">
                @endif
            </td>
            <td class="kop-center">
                <div class="kop-name">APOTEK KINANTHI</div>
                <div class="kop-tagline">PELAYANAN KEFARMASIAN DAN PENGELOLAAN OBAT</div>
                <div class="kop-address">Jl. Dieng No.24, Wonosobo Timur, Wonosobo Tim., Kec. Wonosobo, Kabupaten Wonosobo, Jawa Tengah 56311</div>
                <div class="kop-contact">Telp: 081238575662 | Email: apt.kinanthi@gmail.com</div>
            </td>
            <td class="kop-logo-right">
                @if(!empty($logoKemenkes))
                    <img src="{{ $logoKemenkes }}" alt="Logo Kemenkes RI">
                @endif
            </td>
        </tr>
    </table>
    <div class="kop-divider"></div>

    <!-- JUDUL DOKUMEN & METADATA -->
    <div class="doc-title">JURNAL TRANSAKSI KASIR &amp; REKONSILIASI KAS</div>
    <table class="meta-table">
        <tr>
            <td class="meta-left">
                Periode Transaksi: {{ $startDate }} s/d {{ $endDate }}
            </td>
            <td class="meta-right">
                Dicetak: {{ $printedAt }} WIB &bull; Oleh: {{ $printedBy }}
            </td>
        </tr>
    </table>

    <!-- TABEL REKAPITULASI EKSEKUTIF 5 KOLOM -->
    <table class="rekap-table">
        <thead>
            <tr>
                <th style="width: 18%;">TOTAL TRANSAKSI (PAID)</th>
                <th style="width: 20%;">PENERIMAAN TUNAI (LACI)</th>
                <th style="width: 20%;">NON-TUNAI (QRIS/TRF)</th>
                <th style="width: 18%;">TOTAL DISKON</th>
                <th style="width: 24%;">TOTAL OMSET BERSIH (NET)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($kpis['total_transactions'] ?? 0, 0, ',', '.') }} Transaksi</td>
                <td>Rp {{ number_format($kpis['total_tunai'] ?? 0, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($kpis['total_non_tunai'] ?? 0, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($kpis['total_discount'] ?? 0, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($kpis['total_sales'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL JURNAL TRANSAKSI (13 KOLOM) -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">NO</th>
                <th style="width: 12%;">NO. TRANSAKSI</th>
                <th style="width: 9%;">WAKTU</th>
                <th style="width: 10.5%;">KASIR</th>
                <th style="width: 6%;">TIPE</th>
                <th style="width: 7%;">METODE</th>
                <th style="width: 8.5%;">SUBTOTAL</th>
                <th style="width: 6.5%;">DISKON</th>
                <th style="width: 9%;">TOTAL BAYAR</th>
                <th style="width: 8.5%;">DITERIMA</th>
                <th style="width: 7.5%;">KEMBALIAN</th>
                <th style="width: 5.5%;">STATUS</th>
                <th style="width: 6%;">CATATAN</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $index => $sale)
                @php
                    $isPaid = $sale->status === 'paid';
                    $subtotal = (float) ($sale->total_amount + $sale->discount_amount);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-bold">{{ $sale->sale_number ?? '-' }}</td>
                    <td class="text-center">{{ $sale->created_at ? $sale->created_at->format('d/m/Y H:i') : '-' }}</td>
                    <td class="text-left">{{ $sale->kasir ? $sale->kasir->name : '-' }}</td>
                    <td class="text-center">{{ strtoupper($sale->sale_type ?? 'OTC') }}</td>
                    <td class="text-center">{{ strtoupper($sale->payment_method ?? 'TUNAI') }}</td>
                    <td class="text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
                    <td class="text-right font-bold">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($sale->paid_amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($sale->change_amount, 0, ',', '.') }}</td>
                    <td class="text-center font-bold">
                        {{ $isPaid ? 'Lunas' : 'Void' }}
                    </td>
                    <td class="text-left">{{ $sale->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="padding: 15px; color: #444444;">
                        Tidak ada transaksi kasir pada periode yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-center font-bold">TOTAL TRANSAKSI BERHASIL (PAID)</td>
                <td class="text-right font-bold">Rp {{ number_format($kpis['sum_subtotal'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold">Rp {{ number_format($kpis['sum_diskon'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold">Rp {{ number_format($kpis['sum_total'] ?? 0, 0, ',', '.') }}</td>
                <td colspan="4" class="text-center font-bold">&bull;</td>
            </tr>
        </tfoot>
    </table>

    <!-- BLOK PENGESAHAN (SIGN-OFF) -->
    <table class="signoff-table">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%;">
                <div class="signoff-box">
                    <div class="signoff-date">Wonosobo, {{ $dateNow }}</div>
                    <div class="signoff-role">Apoteker Pengelola Apotek (APA),</div>
                    <div class="signoff-space"></div>
                    <div class="signoff-name">( Apt. Ika Kantiningtyas,S.Farm,CIPC )</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
