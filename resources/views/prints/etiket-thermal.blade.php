@php
/**
 * @var \App\Models\Control\CompoundingSubmission $submission
 * @var \App\Models\Control\CompoundedProduct|null $compoundedProduct
 * @var bool $isObatLuar
 */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiket - {{ $submission->nama_racikan }}</title>
    <style>
        @page {
            size: 50mm 30mm;
            margin: 1mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            width: 50mm;
            height: 30mm;
            background: #fff;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .label-container {
            width: 48mm;
            height: 28mm;
            border: 1px solid #000;
            border-radius: 2px;
            padding: 1.5mm 2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .header {
            text-align: center;
            border-bottom: 0.75px solid #000;
            padding-bottom: 0.8mm;
        }
        .clinic-name {
            font-size: 7.5pt;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .clinic-sub {
            font-size: 5.5pt;
            color: #222;
            line-height: 1;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 6pt;
            font-family: monospace;
            margin-top: 0.8mm;
            line-height: 1.1;
        }
        .patient-row {
            font-size: 7.5pt;
            font-weight: bold;
            margin-top: 0.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.1;
        }
        .signa-box {
            text-align: center;
            background: #f4f4f4;
            border: 0.5px dashed #000;
            padding: 0.8mm 1mm;
            margin: 0.5mm 0;
            border-radius: 1.5px;
        }
        .signa-main {
            font-size: 8.5pt;
            font-weight: 900;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .signa-sub {
            font-size: 6pt;
            font-style: italic;
            line-height: 1;
        }
        .item-info {
            font-size: 6pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.1;
        }
        .obat-luar-banner {
            background: #000000;
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: 900;
            text-align: center;
            padding: 0.6mm 1mm;
            margin: 0.4mm 0;
            letter-spacing: 0.4px;
            border-radius: 1px;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .footer {
            border-top: 0.5px solid #000;
            padding-top: 0.6mm;
            display: flex;
            justify-content: space-between;
            font-size: 5.5pt;
            font-weight: bold;
            line-height: 1;
        }
        @media print {
            body {
                width: 50mm;
                height: 30mm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $targetUnit = strtolower($submission->target_unit ?? '');
        $namaRacikan = strtolower($submission->nama_racikan ?? '');
        $kategoriFormula = strtolower($submission->formula?->kategori_sediaan ?? '');
        $keteranganInput = strtolower($submission->label?->keterangan ?? '');
        $aturanPakai = $submission->label?->aturan_pakai ?? '3 x Sehari 1 Bungkus';

        $isLuar = isset($isObatLuar) ? $isObatLuar : (
            in_array($targetUnit, ['pot', 'tube']) ||
            preg_match('/(salep|krim|cream|gel|pasta|tetes|kumur|gargle|suppo|lotion|lotio|topikal|oles|luar)/i', $namaRacikan . ' ' . $kategoriFormula . ' ' . $targetUnit . ' ' . $aturanPakai . ' ' . $keteranganInput)
        );

        $noResep = $submission->prescription?->prescription_number ?? ('RAC-' . str_pad($submission->id, 4, '0', STR_PAD_LEFT));
        $tglRacik = \Carbon\Carbon::parse($submission->label?->tanggal_racik ?? now())->format('d/m/Y');
        $namaPasien = $submission->label?->nama_pasien ?: ($submission->prescription?->patient?->name ?: 'Pasien Umum');
        
        if ($isLuar) {
            $defaultKet = 'Gunakan pada area yang sakit';
            $keterangan = $submission->label?->keterangan && !str_contains(strtolower($submission->label->keterangan), 'makan')
                ? $submission->label->keterangan
                : $defaultKet;
        } else {
            $keterangan = $submission->label?->keterangan ?: 'Sesudah Makan';
        }

        $budDate = $submission->label?->tanggal_kadaluarsa_bud 
            ? \Carbon\Carbon::parse($submission->label->tanggal_kadaluarsa_bud)->format('d/m/Y')
            : ($compoundedProduct?->bud_date ? \Carbon\Carbon::parse($compoundedProduct->bud_date)->format('d/m/Y') : '-');
        $sediaan = $submission->nama_racikan . ' (' . (int)$submission->target_quantity . ' ' . ($submission->target_unit ?: 'Bungkus') . ')';
    @endphp

    <div class="label-container">
        <div class="header">
            <div class="clinic-name">APOTEK KINANTHI</div>
            <div class="clinic-sub">SIA: 503/SIA/01/2024 &bull; Wonosobo &bull; Telp: 0812-3456-7890</div>
        </div>

        <div class="meta-row">
            <span>No: {{ $noResep }}</span>
            <span>Tgl: {{ $tglRacik }}</span>
        </div>

        <div class="patient-row">
            Pasien: {{ $namaPasien }}
        </div>

        @if($isLuar)
            <div class="obat-luar-banner">
                [ OBAT LUAR - TIDAK BOLEH DITELAN ]
            </div>
        @endif

        <div class="signa-box">
            <div class="signa-main">{{ $aturanPakai }}</div>
            <div class="signa-sub">{{ $keterangan }}</div>
        </div>

        <div class="item-info">
            Obat: {{ $sediaan }}
        </div>

        <div class="footer">
            <span>BUD: {{ $budDate }}</span>
            <span>{{ $isLuar ? 'Simpan Sejuk / Suhu Kamar' : 'Simpan Sejuk & Kering' }}</span>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
