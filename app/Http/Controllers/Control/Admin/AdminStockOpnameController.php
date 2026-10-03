<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\Category;
use App\Models\Control\AuditTrail;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\StockOpname;
use App\Models\Control\StockOpnameItem;
use App\Models\Control\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminStockOpnameController extends Controller
{
    /**
     * Display a listing of stock opnames initiated by Admin.
     */
    public function stockOpnameIndex(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $date = $request->query('date');

        $query = StockOpname::with('creator:id,name')
            ->whereHas('creator', fn($q) => $q->where('role', 'admin'))
            ->withCount('items');

        if ($request->filled('status') && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('date')) {
            $query->whereDate('opname_date', $date);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $cleanedId = preg_replace('/[^0-9]/', '', $search);
                if (!empty($cleanedId)) {
                    $q->orWhere('id', (int) $cleanedId);
                }
                $q->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('creator', fn($qu) => $qu->where('name', 'like', "%{$search}%"));
            });
        }

        return Inertia::render('Control/Admin/StockOpname', [
            'opnames' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'date' => $date ?? '',
            ],
            'medicines' => Medicine::where('status', 'active')
                ->select(['id', 'nama_obat', 'satuan_stok', 'category_id'])
                ->with([
                    'warehouseInventories' => fn($q) => $q->where('status', 'active')
                        ->where('stock_on_hand', '>', 0)
                        ->select(['id', 'medicine_id', 'batch_no', 'expired_date', 'stock_on_hand']),
                    'latestActiveIncomingStock'
                ])
                ->orderBy('nama_obat')
                ->get(),
            'categories' => Category::select(['id', 'name'])->orderBy('name')->get(),
            'rawMaterials' => RawMaterial::where('status', 'active')
                ->select(['id', 'nama_bahan', 'satuan_dasar_penggunaan as satuan_ukur', 'category_id'])
                ->with([
                    'stocks' => fn($q) => $q->where('status', 'active')
                        ->where('stock_on_hand', '>', 0)
                        ->select(['id', 'raw_material_id', 'batch_no', 'expired_date', 'stock_on_hand'])
                ])
                ->orderBy('nama_bahan')
                ->get(),
        ]);
    }

    /**
     * Store a new stock opname session and record item counts & differences.
     */
    public function stockOpnameStore(Request $request)
    {
        $v = $request->validate([
            'tanggal_opname' => 'required|date',
            'keterangan' => 'nullable|string|max:1000',
            'mode' => 'nullable|in:worksheet,manual',
            'scope_type' => 'nullable|in:all,category,raw_materials',
            'category_id' => 'nullable|exists:categories,id',
            'items' => 'nullable|array',
            'items.*.type' => 'nullable|in:medicine,raw_material',
            'items.*.medicine_id' => 'nullable|exists:medicines,id',
            'items.*.raw_material_id' => 'nullable|exists:raw_materials,id',
            'items.*.warehouse_inventory_id' => 'nullable|exists:warehouse_inventories,id',
            'items.*.raw_material_stock_id' => 'nullable|exists:raw_material_stocks,id',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.qty_fisik' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($v, $request) {
            $userId = (int) (auth()->id() ?? $request->user()?->id);
            $mode = $v['mode'] ?? (empty($v['items']) ? 'worksheet' : 'manual');

            $opname = StockOpname::create([
                'category_id' => $v['category_id'] ?? null,
                'created_by' => $userId,
                'opname_date' => $v['tanggal_opname'],
                'catatan' => $v['keterangan'] ?? null,
                'is_blind_count' => false,
                'status' => 'draft',
            ]);

            if ($mode === 'worksheet') {
                $scope = $v['scope_type'] ?? 'all';

                if ($scope === 'raw_materials') {
                    $batches = RawMaterialStock::where('status', 'active')
                        ->where('stock_on_hand', '>', 0)
                        ->orderBy('raw_material_id')
                        ->get();

                    foreach ($batches as $batch) {
                        StockOpnameItem::create([
                            'stock_opname_id' => $opname->id,
                            'raw_material_id' => $batch->raw_material_id,
                            'raw_material_stock_id' => $batch->id,
                            'batch_no' => $batch->batch_no ?? '-',
                            'system_stock' => (float) $batch->stock_on_hand,
                            'physical_stock' => (float) $batch->stock_on_hand,
                            'difference' => 0,
                            'reason' => null,
                        ]);
                    }
                } else {
                    $inventoryQuery = WarehouseInventory::where('status', 'active')
                        ->where('stock_on_hand', '>', 0)
                        ->where(fn($q) => $q->where('location_type', '!=', 'kasir')->orWhereNull('location_type'));

                    if ($scope === 'category' && !empty($v['category_id'])) {
                        $catId = $v['category_id'];
                        $inventoryQuery->whereHas('medicine', fn($q) => $q->where('category_id', $catId));
                    }

                    $batches = $inventoryQuery->orderBy('medicine_id')->get();

                    foreach ($batches as $batch) {
                        StockOpnameItem::create([
                            'stock_opname_id' => $opname->id,
                            'medicine_id' => $batch->medicine_id,
                            'warehouse_inventory_id' => $batch->id,
                            'batch_no' => $batch->batch_no ?? '-',
                            'system_stock' => (float) $batch->stock_on_hand,
                            'physical_stock' => (float) $batch->stock_on_hand,
                            'difference' => 0,
                            'reason' => null,
                        ]);
                    }
                }

                AuditTrail::log($userId, 'CREATE_ADMIN_OPNAME', "Admin membuat lembar kerja stock opname otomatis ({$scope})", StockOpname::class, $opname->id);

                return redirect()->route('control.admin.stock-opname.show', $opname->id)->with('success', 'Lembar kerja stock opname berhasil dibuat.');
            }

            // Mode Manual (Sampling)
            $items = $v['items'] ?? [];
            foreach ($items as $item) {
                $systemStock = 0;
                $batchNo = $item['batch_no'] ?? null;
                $warehouseInventoryId = $item['warehouse_inventory_id'] ?? null;
                $rawMaterialStockId = $item['raw_material_stock_id'] ?? null;

                if (!empty($warehouseInventoryId)) {
                    $inv = WarehouseInventory::find($warehouseInventoryId);
                    if ($inv) {
                        $systemStock = (float) $inv->stock_on_hand;
                        $batchNo = $inv->batch_no;
                    }
                } elseif (!empty($rawMaterialStockId)) {
                    $rms = RawMaterialStock::find($rawMaterialStockId);
                    if ($rms) {
                        $systemStock = (float) $rms->stock_on_hand;
                        $batchNo = $rms->batch_no;
                    }
                } elseif (!empty($item['medicine_id'])) {
                    $systemStock = (float) WarehouseInventory::where('medicine_id', $item['medicine_id'])
                        ->where('status', 'active')
                        ->sum('stock_on_hand');
                    $batchNo = 'ALL_BATCHES';
                } elseif (!empty($item['raw_material_id'])) {
                    $systemStock = (float) RawMaterialStock::where('raw_material_id', $item['raw_material_id'])
                        ->where('status', 'active')
                        ->sum('stock_on_hand');
                    $batchNo = 'ALL_BATCHES';
                }

                $qtyFisik = (float) ($item['qty_fisik'] ?? 0);
                $difference = $qtyFisik - $systemStock;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'medicine_id' => $item['medicine_id'] ?? null,
                    'raw_material_id' => $item['raw_material_id'] ?? null,
                    'warehouse_inventory_id' => $warehouseInventoryId,
                    'raw_material_stock_id' => $rawMaterialStockId,
                    'batch_no' => $batchNo ?? 'ALL_BATCHES',
                    'system_stock' => $systemStock,
                    'physical_stock' => $qtyFisik,
                    'difference' => $difference,
                    'reason' => abs($difference) > 0.001 ? ($item['reason'] ?? 'Selisih Opname') : null,
                ]);
            }

            AuditTrail::log($userId, 'CREATE_ADMIN_OPNAME', "Admin membuat stock opname manual", StockOpname::class, $opname->id);

            return redirect()->route('control.admin.stock-opname.show', $opname->id)->with('success', 'Stock Opname berhasil dibuat.');
        });
    }

    /**
     * Display the details of a specific stock opname session.
     */
    public function stockOpnameShow($id)
    {
        return Inertia::render('Control/Admin/StockOpnameDetail', [
            'opname' => StockOpname::with([
                'creator:id,name',
                'finalizer:id,name',
                'items' => fn($q) => $q->orderBy('id', 'asc'),
                'items.medicine:id,nama_obat,satuan_stok,satuan_besar,satuan_menengah,rasio_konversi',
                'items.medicine.latestActiveIncomingStock',
                'items.rawMaterial:id,nama_bahan,satuan_dasar_penggunaan as satuan_ukur',
                'items.productBatch:id,batch_no,expired_date,stock_on_hand',
                'items.rawMaterialBatch:id,batch_no,expired_date,stock_on_hand',
            ])->findOrFail($id),
        ]);
    }

    /**
     * Finalize the stock opname and execute inventory adjustments.
     */
    public function stockOpnameAdjust($id, Request $request)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if ($opname->status !== 'draft') {
            return redirect()->back()->with('error', 'Opname ini sudah difinalisasi.');
        }

        // 1. Simpan Draf (hanya simpan qty fisik & alasan tanpa mengubah stok gudang)
        if ($request->input('action') === 'save_draft') {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:stock_opname_items,id',
                'items.*.physical_stock' => 'required|numeric|min:0',
                'items.*.reason' => 'nullable|string|max:255',
            ]);

            return DB::transaction(function () use ($opname, $validated) {
                foreach ($validated['items'] as $itemData) {
                    $item = $opname->items->firstWhere('id', $itemData['id']);
                    if ($item) {
                        $diff = (float) $itemData['physical_stock'] - (float) $item->system_stock;
                        $item->update([
                            'physical_stock' => (float) $itemData['physical_stock'],
                            'difference' => $diff,
                            'reason' => abs($diff) > 0.001 ? ($itemData['reason'] ?? 'Selisih Fisik') : null,
                        ]);
                    }
                }

                return redirect()->back()->with('success', 'Draf perhitungan fisik berhasil disimpan.');
            });
        }

        // 2. Finalisasi & Sesuaikan Stok
        return DB::transaction(function () use ($opname, $request) {
            // Jika ada payload items terbaru yang disertakan, perbarui data item terlebih dahulu
            if ($request->has('items') && is_array($request->input('items'))) {
                foreach ($request->input('items') as $itemData) {
                    if (isset($itemData['id'])) {
                        $item = $opname->items->firstWhere('id', $itemData['id']);
                        if ($item) {
                            $phys = isset($itemData['physical_stock']) ? (float) $itemData['physical_stock'] : (float) $item->physical_stock;
                            $diff = $phys - (float) $item->system_stock;
                            $item->update([
                                'physical_stock' => $phys,
                                'difference' => $diff,
                                'reason' => abs($diff) > 0.001 ? ($itemData['reason'] ?? $item->reason ?? 'Selisih Opname') : null,
                            ]);
                        }
                    }
                }
                $opname->load('items');
            }

            // SIPNAP Compliance Lock (Ponytail Mode)
            $sipnapViolations = [];
            foreach ($opname->items as $item) {
                if ($item->difference < -0.001 && $item->medicine_id) {
                    $item->loadMissing('medicine');
                    $classification = strtolower($item->medicine->classification ?? '');
                    if (in_array($classification, ['narkotika', 'psikotropika', 'prekursor', 'oot'])) {
                        $sipnapViolations[] = $item->medicine->nama_obat;
                    }
                }
            }

            if (count($sipnapViolations) > 0 && auth()->user()->role !== 'apoteker') {
                return redirect()->back()->with('error', 'Peringatan SIPNAP: Ditemukan selisih stok kurang (hilang) pada obat khusus (' . implode(', ', $sipnapViolations) . '). Pengurangan stok ini ilegal dilakukan sepihak oleh Admin. Wajib disesuaikan langsung oleh Apoteker beserta Berita Acara.');
            }

            $affectedMedicines = [];
            $affectedRawMaterials = [];

            foreach ($opname->items as $item) {
                if (abs($item->difference) > 0.001) {
                    if ($item->warehouse_inventory_id) {
                        $inv = WarehouseInventory::where('id', $item->warehouse_inventory_id)
                            ->lockForUpdate()
                            ->first();
                        if ($inv) {
                            $inv->stock_on_hand = (float) $item->physical_stock;
                            $inv->mutationContext = [
                                'type' => 'opname_adjustment',
                                'reference_type' => StockOpname::class,
                                'reference_id' => $opname->id,
                                'note' => "Penyesuaian Opname Batch {$inv->batch_no}: " . ($item->difference >= 0 ? "+{$item->difference}" : "{$item->difference}") . ($item->reason ? " ({$item->reason})" : ""),
                            ];
                            $inv->save();
                        }
                        if ($item->medicine_id) {
                            $affectedMedicines[$item->medicine_id] = true;
                        }
                    } elseif ($item->raw_material_stock_id) {
                        $rms = RawMaterialStock::where('id', $item->raw_material_stock_id)
                            ->lockForUpdate()
                            ->first();
                        if ($rms) {
                            $rms->stock_on_hand = (float) $item->physical_stock;
                            $rms->mutationContext = [
                                'type' => 'opname_adjustment',
                                'reference_type' => StockOpname::class,
                                'reference_id' => $opname->id,
                                'note' => "Penyesuaian Opname Batch {$rms->batch_no}: " . ($item->difference >= 0 ? "+{$item->difference}" : "{$item->difference}") . ($item->reason ? " ({$item->reason})" : ""),
                            ];
                            $rms->save();
                        }
                        if ($item->raw_material_id) {
                            $affectedRawMaterials[$item->raw_material_id] = true;
                        }
                    } elseif ($item->medicine_id) {
                        $this->applyOpnameAdjustment(
                            WarehouseInventory::class,
                            'medicine_id',
                            (int) $item->medicine_id,
                            (float) $item->difference,
                            (float) $item->physical_stock,
                            $opname->id
                        );
                        $affectedMedicines[$item->medicine_id] = true;
                    } elseif ($item->raw_material_id) {
                        $this->applyOpnameAdjustment(
                            RawMaterialStock::class,
                            'raw_material_id',
                            (int) $item->raw_material_id,
                            (float) $item->difference,
                            (float) $item->physical_stock,
                            $opname->id
                        );
                        $affectedRawMaterials[$item->raw_material_id] = true;
                    }
                }
            }

            // Recalculate stock per obat / bahan baku secara kolektif
            foreach (array_keys($affectedMedicines) as $medId) {
                $med = Medicine::find($medId);
                $med?->recalculateStock();
            }
            foreach (array_keys($affectedRawMaterials) as $rmId) {
                $rm = RawMaterial::find($rmId);
                $rm?->recalculateStock();
            }

            $userId = (int) (auth()->id() ?? $request->user()?->id);
            $opname->update([
                'status' => 'completed',
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            AuditTrail::log($userId, 'FINALIZE_ADMIN_OPNAME', "Admin memfinalisasi stock opname", StockOpname::class, $opname->id);

            return redirect()->back()->with('success', 'Stock Opname berhasil difinalisasi dan stok telah disesuaikan.');
        });
    }

    /**
     * Eksekusi penyesuaian stok opname secara polimorfik (FEFO chain deduction untuk minus, atau alokasi batch untuk surplus).
     */
    private function applyOpnameAdjustment(string $modelClass, string $foreignKey, int $foreignId, float $diff, float $physicalStock, int $opnameId): void
    {
        if ($diff < 0) {
            $remainingToDeduct = abs($diff);
            $batches = $modelClass::where($foreignKey, $foreignId)
                ->where('status', 'active')
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) break;

                $currentStock = (float) $batch->stock_on_hand;
                $deductQty = min($currentStock, $remainingToDeduct);
                $remainingToDeduct -= $deductQty;

                $batch->stock_on_hand = $currentStock - $deductQty;
                $batch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Selisih fisik minus (-{$deductQty})",
                ];
                $batch->save();
            }
        } else {
            $latestBatch = $modelClass::where($foreignKey, $foreignId)
                ->where('status', 'active')
                ->orderBy('expired_date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($latestBatch) {
                $latestBatch->stock_on_hand += $diff;
                $latestBatch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Selisih fisik plus (+{$diff})",
                ];
                $latestBatch->save();
            } else {
                $newBatch = $modelClass::create([
                    $foreignKey => $foreignId,
                    'batch_no' => 'ADJ-' . now()->format('Ymd'),
                    'stock_on_hand' => $physicalStock,
                    'expired_date' => now()->addYear(),
                    'status' => 'active',
                ]);
                $newBatch->mutationContext = [
                    'type' => 'opname_adjustment',
                    'reference_type' => StockOpname::class,
                    'reference_id' => $opnameId,
                    'note' => "Penyesuaian Opname: Batch baru penyesuaian (+{$physicalStock})",
                ];
                $newBatch->save();
            }
        }
    }
}
