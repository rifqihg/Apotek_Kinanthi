<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Mutasi SIPNAP - {{ $month }}</title>
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
            font-size: 9pt;
            line-height: 1.4;
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
            width: 80px;
            vertical-align: middle;
            text-align: left;
        }
        .kop-logo-left img {
            max-height: 65px;
            max-width: 80px;
            width: auto;
            height: auto;
        }
        .kop-logo-right {
            width: 85px;
            vertical-align: middle;
            text-align: right;
        }
        .kop-logo-right img {
            max-height: 65px;
            max-width: 85px;
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
            line-height: 1.2;
            margin: 0 0 2px 0;
        }
        .kop-tagline {
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .kop-address {
            font-size: 9.5pt;
            line-height: 1.2;
            margin: 0 0 2px 0;
        }
        .kop-contact {
            font-size: 9pt;
            line-height: 1.2;
            margin: 0;
            padding-bottom: 6px;
        }
        .kop-divider {
            border-bottom: 3px double #000000;
            margin-top: 0;
            margin-bottom: 12px;
            height: 0;
        }

        /* ─── JUDUL & METADATA DOKUMEN ─── */
        .doc-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
            margin: 0 0 3px 0;
            letter-spacing: 0.5px;
        }
        .doc-subtitle {
            text-align: center;
            font-size: 9.5pt;
            font-style: italic;
            margin: 0 0 12px 0;
            color: #333333;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            line-height: 1.3;
            margin: 0 0 10px 0;
        }
        .meta-left {
            text-align: left;
            vertical-align: top;
            width: 50%;
        }
        .meta-right {
            text-align: right;
            vertical-align: top;
            width: 50%;
        }

        /* ─── TABEL RINGKASAN EKSEKUTIF ─── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 0.5px solid #000000;
        }
        .summary-table th {
            border: 0.5px solid #000000;
            background-color: #F1F5F9;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            text-align: center;
        }
        .summary-table td {
            border: 0.5px solid #000000;
            padding: 6px 8px;
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            background-color: #FAFAFA;
        }

        /* ─── TABEL MUTASI UTAMA ─── */
        .mutation-table {
            width: 100%;
            border-collapse: collapse;
            border: 0.5px solid #000000;
            font-size: 8.5pt;
        }
        .mutation-table th {
            border: 0.5px solid #000000;
            background-color: #E2E8F0;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 4px;
            text-align: center;
            font-size: 8pt;
        }
        .mutation-table td {
            border: 0.5px solid #000000;
            padding: 4px 6px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }

        .text-narkotika {
            font-weight: bold;
            color: #991B1B;
            text-transform: uppercase;
        }
        .text-psikotropika {
            font-weight: bold;
            color: #5B21B6;
            text-transform: uppercase;
        }

        .total-row td {
            background-color: #F1F5F9;
            font-weight: bold;
            border-top: 1.5px solid #000000;
            padding: 6px 6px;
        }

        /* ─── BLOK PENGESAHAN (SIGN-OFF) ─── */
        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .signoff-box {
            text-align: center;
            font-size: 9.5pt;
        }
        .signoff-date {
            margin-bottom: 3px;
        }
        .signoff-role {
            font-weight: bold;
            margin-bottom: 45px;
        }
        .signoff-name {
            font-weight: bold;
            text-decoration: underline;
            font-size: 10pt;
        }
        .signoff-sipa {
            font-size: 8.5pt;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT BILATERAL -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo-left">
                @if($logoKinanthi)
                    <img src="{{ $logoKinanthi }}" alt="Logo Apotek Kinanthi">
                @endif
            </td>
            <td class="kop-center">
                <div class="kop-name">APOTEK KINANTHI</div>
                <div class="kop-tagline">PELAYANAN KEFARMASIAN & PENGELOLAAN OBAT RESMI</div>
                <div class="kop-address">Jl. Dieng No.24, Wonosobo Timur, Wonosobo Tim., Kec. Wonosobo, Kabupaten Wonosobo, Jawa Tengah 56311</div>
                <div class="kop-contact">Telp: 081238575662 &bull; Email: apt.kinanthi@gmail.com</div>
            </td>
            <td class="kop-logo-right">
                @if($logoKemenkes)
                    <img src="{{ $logoKemenkes }}" alt="Logo Kemenkes">
                @endif
            </td>
        </tr>
    </table>
    <div class="kop-divider"></div>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-title">LAPORAN BULANAN PENGGUNAAN NARKOTIKA &amp; PSIKOTROPIKA (SIPNAP)</div>
    <div class="doc-subtitle">Sistem Pelaporan Narkotika dan Psikotropika &bull; Permenkes RI No. 3 Tahun 2015</div>

    <!-- METADATA DOKUMEN -->
    <table class="meta-table">
        <tr>
            <td class="meta-left">
                <strong>Periode Mutasi:</strong> {{ strtoupper($monthLabel) }} ({{ $month }})<br>
                <strong>Unit Pengelola:</strong> Apotek Kinanthi Wonosobo
            </td>
            <td class="meta-right">
                <strong>Tanggal Cetak:</strong> {{ $printedAt }}<br>
                <strong>Petugas Pelapor:</strong> {{ $printedBy }}
            </td>
        </tr>
    </table>

    <!-- RINGKASAN REKAPITULASI MUTASI -->
    <table class="summary-table">
        <thead>
            <tr>
                <th style="width: 20%;">TOTAL ITEM TERDAFTAR</th>
                <th style="width: 20%;">SEDIAAN NARKOTIKA</th>
                <th style="width: 20%;">SEDIAAN PSIKOTROPIKA</th>
                <th style="width: 20%;">TOTAL PEMASUKAN</th>
                <th style="width: 20%;">TOTAL PENGELUARAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($summary['total_items'] ?? 0, 0, ',', '.') }} Item</td>
                <td style="color: #991B1B;">{{ number_format($summary['total_narkotika'] ?? 0, 0, ',', '.') }} Item</td>
                <td style="color: #5B21B6;">{{ number_format($summary['total_psikotropika'] ?? 0, 0, ',', '.') }} Item</td>
                <td style="color: #065F46;">+{{ number_format($summary['total_pemasukan'] ?? 0, 0, ',', '.') }}</td>
                <td style="color: #991B1B;">-{{ number_format($summary['total_pengeluaran'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL MUTASI FISIK OBAT (SIPNAP CANONICAL) -->
    <table class="mutation-table">
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 11%;">NO. BATCH</th>
                <th style="width: 27%;">NAMA SEDIAAN</th>
                <th style="width: 12%;">GOLONGAN</th>
                <th style="width: 8%;">SATUAN</th>
                <th style="width: 9%;">STOK AWAL</th>
                <th style="width: 9%;">PEMASUKAN<br><span style="font-size: 6.5pt; font-weight: normal;">(Stok Masuk)</span></th>
                <th style="width: 10%;">PENGELUARAN<br><span style="font-size: 6.5pt; font-weight: normal;">(Stok Keluar)</span></th>
                <th style="width: 10%;">STOK AKHIR</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $index => $row)
                <tr>
                    <td class="text-center font-mono">{{ $index + 1 }}</td>
                    <td class="text-center font-bold" style="font-size: 8pt;">{{ $row['batch_no'] ?? '-' }}</td>
                    <td class="text-left font-bold">{{ $row['nama_obat'] }}</td>
                    <td class="text-center">
                        @if($row['classification'] === 'narkotika')
                            <span class="text-narkotika">Narkotika</span>
                        @elseif($row['classification'] === 'psikotropika')
                            <span class="text-psikotropika">Psikotropika</span>
                        @else
                            <span>{{ strtoupper($row['classification'] ?? '-') }}</span>
                        @endif
                    </td>
                    <td class="text-center font-bold">{{ $row['satuan'] ?: '-' }}</td>
                    <td class="text-center font-bold">{{ number_format($row['stok_awal'], 0, ',', '.') }}</td>
                    <td class="text-center font-bold" style="color: #047857;">+{{ number_format($row['pemasukan'], 0, ',', '.') }}</td>
                    <td class="text-center font-bold" style="color: #B91C1C;">-{{ number_format($row['pengeluaran'], 0, ',', '.') }}</td>
                    <td class="text-center font-bold" style="background-color: #F8FAFC;">{{ number_format($row['stok_akhir'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px; color: #555555; font-style: italic;">
                        Tidak ada data pergerakan stok sediaan Narkotika atau Psikotropika pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-center font-bold">TOTAL AGREGAT MUTASI</td>
                <td class="text-center font-bold">{{ number_format(collect($reportData)->sum('stok_awal'), 0, ',', '.') }}</td>
                <td class="text-center font-bold" style="color: #047857;">+{{ number_format(collect($reportData)->sum('pemasukan'), 0, ',', '.') }}</td>
                <td class="text-center font-bold" style="color: #B91C1C;">-{{ number_format(collect($reportData)->sum('pengeluaran'), 0, ',', '.') }}</td>
                <td class="text-center font-bold">{{ number_format(collect($reportData)->sum('stok_akhir'), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- BLOK PENGESAHAN APOTEKER PENGELOLA APOTEK -->
    <table class="signoff-table">
        <tr>
            <td style="width: 65%; vertical-align: top; padding-top: 10px; font-size: 8pt; color: #4B5563;">
                <em>* Arsip resmi Dokumen Rekapitulasi SIPNAP Apotek Kinanthi. Wajib disimpan sekurang-kurangnya 3 (tiga) tahun sesuai Permenkes RI.</em>
            </td>
            <td style="width: 35%; vertical-align: top;">
                <div class="signoff-box">
                    <div class="signoff-date">Wonosobo, {{ $dateNow }}</div>
                    <div class="signoff-role">Apoteker Pengelola Apotek (APA),</div>
                    <div class="signoff-name">( Apt. Ika Kantiningtyas,S.Farm,CIPC )</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
