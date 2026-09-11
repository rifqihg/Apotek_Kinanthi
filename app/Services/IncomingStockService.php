<?php

namespace App\Services;

use App\Models\Control\AuditTrail;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\WarehouseInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncomingStockService
{
    /**
     * Single-step Faktur Masuk (Admin directly stores and activates stock).
     */
    public function store(array $data, ?UploadedFile $foto, int $userId): array
    {
        return DB::transaction(function () use ($data, $foto, $userId) {
            $fotoPath = $foto ? $foto->store('faktur', 'public') : null;
            $createdStocks = [];

            foreach ($data['items'] as $item) {
                if (empty($item['medicine_id'])) {
                    $medicine = Medicine::create([
                        'nama_obat' => $item['new_medicine_name'],
                        'kategori' => $item['new_medicine_kategori'] ?? null,
                        'satuan_besar' => $item['satuan_input'] ?? $item['new_medicine_satuan_besar'] ?? null,
                        'satuan_menengah' => $item['mid_unit'] ?? null,
                        'satuan_stok' => $item['new_medicine_satuan_kecil'],
                        'rasio_konversi' => $item['unit_conversion_factor'] ?? 1,
                        'threshold_min' => isset($item['min_stock_threshold']) ? (int) $item['min_stock_threshold'] : 10,
                        'stok_total' => 0,
                        'status' => 'active', 
                    ]);
                    $item['medicine_id'] = $medicine->id;

                    AuditTrail::log($userId, 'CREATE_MEDICINE',
                        "Menambah obat baru dari Faktur: {$medicine->nama_obat}",
                        Medicine::class, $medicine->id
                    );
                } else {
                    $medicine = Medicine::find($item['medicine_id']);
                    if (isset($item['min_stock_threshold']) && $item['min_stock_threshold'] !== null && $item['min_stock_threshold'] !== '') {
                        $medicine->update(['threshold_min' => (int) $item['min_stock_threshold']]);
                    }
                }

                // For new medicine, we no longer override unit_conversion_factor with new_medicine_rasio_konversi
                // because the UI now relies on small_to_mid_ratio and mid_to_big_ratio directly, 
                // and sets unit_conversion_factor properly in the frontend.
                $factor = (int) ($item['unit_conversion_factor'] ?? $medicine->rasio_konversi ?? 1);
                $qty_masuk = (int) $item['qty_masuk'];
                $qty_converted = $qty_masuk * $factor;
                $harga_beli_satuan = (float) $item['harga_beli_satuan'];
                $diskon_persen = (float) ($item['diskon_persen'] ?? 0);

                $stock = IncomingStock::create([
                    'faktur_no'              => $data['faktur_no'],
                    'supplier_id'            => $data['supplier_id'],
                    'medicine_id'            => $item['medicine_id'],
                    'batch_no'               => $item['batch_no'] ?? 'BATCH-' . strtoupper(Str::random(5)),
                    'expired_date'           => $item['expired_date'] ?? now()->addYear(),
                    'qty_masuk'              => $qty_masuk,
                    'qty_converted'          => $qty_converted,
                    'qty_fisik_verify'       => $qty_converted,
                    'unit_conversion_factor' => $factor,
                    'satuan_input'           => $item['satuan_input'] ?? $medicine->satuan_besar ?? 'Box',
                    'mid_unit'               => $item['mid_unit'] ?? null,
                    'mid_to_big_ratio'       => (int) ($item['mid_to_big_ratio'] ?? 1),
                    'small_to_mid_ratio'     => (int) ($item['small_to_mid_ratio'] ?? 1),
                    'harga_beli_satuan'      => $harga_beli_satuan,
                    'diskon_persen'          => $diskon_persen,
                    'jumlah_harga'           => ($harga_beli_satuan * (1 - ($diskon_persen / 100))) * $qty_masuk,
                    'ppn_persen'             => $data['ppn_persen'] ?? 11.00,
                    'margin_persen'          => 0,
                    'harga_jual_saat_ini'    => 0,
                    'harga_jual_akhir'       => 0,
                    'tanggal_faktur'         => $data['tanggal_faktur'] ?? now(),
                    'foto_faktur_path'       => $fotoPath,
                    'status_gate'            => 'active',
                    'status_approval'        => 'approved',
                ]);

                if ($medicine->status === 'inactive') {
                    $medicine->update(['status' => 'active']);
                }

                $this->addToWarehouse($stock, $qty_converted);

                $createdStocks[] = $stock;
            }

            AuditTrail::log($userId, 'STORE_INCOMING_STOCK',
                "Admin menyimpan dan mengaktifkan Faktur {$data['faktur_no']} dengan " . count($createdStocks) . " item.",
                IncomingStock::class, $createdStocks[0]->id ?? null
            );

            return $createdStocks;
        });
    }

    private function addToWarehouse(IncomingStock $stock, int $qty, ?string $rackLocation = null): void
    {
        $wi = WarehouseInventory::firstOrCreate(
            ['medicine_id' => $stock->medicine_id, 'batch_no' => $stock->batch_no],
            ['expired_date' => $stock->expired_date, 'stock_on_hand' => 0, 'status' => 'active']
        );
        
        $wi->update([
            'stock_on_hand' => $wi->stock_on_hand + $qty,
            'rack_location' => $rackLocation ?? $wi->rack_location,
            'incoming_stock_id' => $stock->id,
            'transferred_at' => now(),
            'status' => 'active'
        ]);

        $stock->medicine->recalculateStock();
    }
}
