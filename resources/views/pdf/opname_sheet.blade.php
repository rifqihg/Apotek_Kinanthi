<!DOCTYPE html>
<html>
<head>
    <title>Lembar Kerja Stock Opname {{ $opname->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #0d9488; margin-bottom: 5px; }
        .info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { bg-color: #f9fafb; font-weight: bold; }
        .box { width: 100px; height: 25px; border: 1px solid #333; }
        .footer { margin-top: 50px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>STOCK OPNAME WORKSHEET</h1>
        <p>Sesi: {{ $opname->id }} | Tanggal: {{ $opname->opname_date->format('d/m/Y') }}</p>
    </div>

    <div class="info">
        <p><strong>Petugas:</strong> {{ $opname->creator->name }}</p>
        <p><strong>Tipe Audit:</strong> {{ $opname->is_blind_count ? 'Blind Count (Sistem disembunyikan)' : 'Regular Audit' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30">No</th>
                <th>Nama Obat / Bahan</th>
                <th>Batch No</th>
                <th>Rak / Lokasi</th>
                <th>ED</th>
                @if(!$opname->is_blind_count)
                <th width="80">Stok Sistem</th>
                @endif
                <th width="120">Stok Fisik (Input Manual)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($opname->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->medicine ? $item->medicine->nama_obat : $item->rawMaterial->nama_bahan }}</strong><br>
                    <small>{{ $item->medicine ? $item->medicine->kode_obat : $item->rawMaterial->kode_bahan }}</small>
                </td>
                <td>{{ $item->batch_no }}</td>
                <td>
                    @if($item->warehouse_inventory_id)
                        {{ $item->productBatch->rack_location ?? '-' }}
                    @else
                        {{ $item->rawMaterialBatch->rack_location ?? '-' }}
                    @endif
                </td>
                <td>
                    @php
                        $batch = $item->warehouse_inventory_id ? $item->productBatch : $item->rawMaterialBatch;
                        $ed = $batch ? $batch->expired_date : null;
                    @endphp
                    {{ $ed ? $ed->format('d/m/Y') : '-' }}
                </td>
                @if(!$opname->is_blind_count)
                <td>{{ $item->system_stock }}</td>
                @endif
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
        <br><br><br>
        <p>__________________________</p>
        <p>( Tanda Tangan Petugas )</p>
    </div>
</body>
</html>
