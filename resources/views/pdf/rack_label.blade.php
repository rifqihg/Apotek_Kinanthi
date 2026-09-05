<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 10px; text-align: center; }
        .label-container { border: 2px solid #0d9488; border-radius: 10px; padding: 10px; height: 110px; position: relative; }
        .medicine-name { font-size: 14pt; font-weight: 900; color: #0f172a; margin-bottom: 5px; text-transform: uppercase; }
        .batch-info { font-size: 10pt; color: #64748b; font-family: monospace; }
        .rack-location { font-size: 12pt; font-weight: 800; color: #0d9488; margin-top: 10px; }
        .footer { position: absolute; bottom: 5px; width: 100%; font-size: 8pt; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="label-container">
        <div class="medicine-name">{{ $inventory->medicine->nama_obat }}</div>
        <div class="batch-info">BATCH: {{ $inventory->batch_no }} | ED: {{ $inventory->expired_date }}</div>
        <div class="rack-location">RAK: {{ $inventory->rack_location ?: '-' }} / LEMARI: {{ $inventory->shelf_location ?: '-' }}</div>
        <div class="footer">APOTEK KINANTHI — SISTEM LOGISTIK JOSJIS</div>
    </div>
</body>
</html>
