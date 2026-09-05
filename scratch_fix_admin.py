import sys

with open('app/Http/Controllers/Control/AdminController.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('public function storeReturn')
garble_idx = text.find('    public function destructions()', idx)

if garble_idx > -1 and garble_idx > idx:
    # keep everything before garble_idx
    good_text = text[:garble_idx]
else:
    # if not found, just use the whole text and cut off manually based on what we know
    garble_idx = text.find('    public function destructions()')
    good_text = text[:garble_idx]

missing_store_return = """            ]);

            // Deduct Incoming Stock quantities (Katalog Faktur)
            $qty_terkecil = $v['qty_retur'];
            $incomingStock->qty_converted -= $qty_terkecil;
            if ($incomingStock->qty_fisik_verify !== null && $incomingStock->qty_fisik_verify >= $qty_terkecil) {
                $incomingStock->qty_fisik_verify -= $qty_terkecil;
            }
            $incomingStock->save();

            // Deduct Warehouse Inventory (Kasir sync)
            $wi = \\App\\Models\\Control\\WarehouseInventory::where('incoming_stock_id', $incomingStock->id)
                ->gudang()
                ->where('stock_on_hand', '>', 0)
                ->first();

            if ($wi) {
                $deductAmount = min($wi->stock_on_hand, $qty_terkecil);
                $wi->setMutationContext('out', $returnForm, 'Retur ke Supplier: ' . $v['alasan_retur']);
                $wi->update([
                    'stock_on_hand' => $wi->stock_on_hand - $deductAmount,
                ]);
            }

            // Recalculate medicine total
            if ($incomingStock->medicine) {
                $incomingStock->medicine->recalculateStock();
            }

            \\App\\Models\\Control\\AuditTrail::log(
                $request->user()->id,
                'RETURN_SUPPLIER',
                "Retur obat " . ($incomingStock->medicine ? $incomingStock->medicine->nama_obat : 'Unknown') . " sejumlah {$qty_terkecil} ke supplier",
                \\App\\Models\\Control\\ReturnForm::class,
                $returnForm->id
            );

            return redirect()->back()->with('success', 'Retur ke supplier berhasil diproses dan stok telah disesuaikan.');
        });
    }

"""

with open('scratch_fix_admin_base.txt', 'w', encoding='utf-8') as f:
    f.write(good_text + missing_store_return)
