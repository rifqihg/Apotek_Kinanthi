<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use App\Models\Control\ReturnForm;
use App\Models\Control\Supplier;
use App\Models\Control\WarehouseInventory;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminInventoryController extends Controller
{
    public function returns(Request $request)
    {
        $search = $request->query('search');
        $date = $request->query('date');
        $timeFilter = $request->query('time_filter', 'all');
        $supplierId = $request->query('supplier_id');

        $query = ReturnForm::select([
            'id', 'incoming_stock_id', 'supplier_id', 'qty_retur', 
            'alasan_retur', 'status', 'created_by', 'created_at'
        ])->with([
            'incomingStock:id,faktur_no,medicine_id,batch_no',
            'incomingStock.medicine:id,nama_obat,kode_obat,satuan_stok',
            'supplier:id,nama_pbf',
            'creator:id,name'
        ]);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($timeFilter === 'today') {
            $query->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
        } elseif ($timeFilter === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($request->filled('date')) {
            $query->whereBetween('created_at', [
                date('Y-m-d 00:00:00', strtotime($date)), 
                date('Y-m-d 23:59:59', strtotime($date))
            ]);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $cleanedId = preg_replace('/[^0-9]/', '', $search);
                if (!empty($cleanedId)) {
                    $q->orWhere('id', (int) $cleanedId);
                }
                $q->orWhere('alasan_retur', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('nama_pbf', 'like', "%{$search}%");
                  })
                  ->orWhereHas('incomingStock.medicine', function ($mq) use ($search) {
                      $mq->where('nama_obat', 'like', "%{$search}%")
                         ->orWhere('kode_obat', 'like', "%{$search}%");
                  })
                  ->orWhereHas('incomingStock', function ($iq) use ($search) {
                      $iq->where('faktur_no', 'like', "%{$search}%")
                         ->orWhere('batch_no', 'like', "%{$search}%");
                  });
            });
        }

        $returns = $query->latest()
            ->paginate(50)
            ->withQueryString();

        // 1 Single lightweight aggregate query instead of 5 separate full scans
        $statsRow = DB::table('return_forms')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as this_month,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as today,
                COALESCE(SUM(qty_retur), 0) as total_units,
                COUNT(DISTINCT supplier_id) as unique_suppliers
            ', [
                now()->startOfMonth(), 
                now()->endOfMonth(), 
                today()->startOfDay(), 
                today()->endOfDay()
            ])
            ->first();

        $stats = [
            'total' => (int) ($statsRow->total ?? 0),
            'this_month' => (int) ($statsRow->this_month ?? 0),
            'today' => (int) ($statsRow->today ?? 0),
            'total_units' => (int) ($statsRow->total_units ?? 0),
            'unique_suppliers' => (int) ($statsRow->unique_suppliers ?? 0),
        ];

        return Inertia::render('Control/Admin/Returns', [
            'returns' => $returns,
            'stats' => $stats,
            'suppliers' => fn () => Supplier::select(['id', 'nama_pbf'])->orderBy('nama_pbf')->get(),
            'activeIncomingStocks' => fn () => IncomingStock::select([
                    'id', 'faktur_no', 'supplier_id', 'medicine_id', 'batch_no', 
                    'expired_date', 'qty_converted', 'satuan_input', 'mid_unit',
                    'unit_conversion_factor', 'mid_to_big_ratio', 'small_to_mid_ratio'
                ])
                ->with([
                    'medicine:id,nama_obat,satuan_stok', 
                    'supplier:id,nama_pbf'
                ])
                ->where('qty_converted', '>', 0)
                ->whereIn('status_gate', ['pending_admin_verify', 'pending_admin_final', 'active'])
                ->orderBy('created_at', 'desc')
                ->get(),
            'filters' => [
                'search' => $search ?? '',
                'date' => $date ?? '',
                'time_filter' => $timeFilter,
                'supplier_id' => $supplierId ?? '',
            ],
        ]);
    }

    public function storeReturn(Request $request)
    {
        $v = $request->validate([
            'incoming_stock_id' => 'required|exists:incoming_stocks,id',
            'qty_retur' => 'required|integer|min:1',
            'alasan_retur' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($v, $request) {
            $incomingStock = IncomingStock::with('medicine')->findOrFail($v['incoming_stock_id']);

            if ($incomingStock->qty_converted < $v['qty_retur']) {
                return redirect()->back()->with('error', 'Kuantitas retur melebihi sisa stok di faktur.');
            }

            // Create Return Form
            $returnForm = ReturnForm::create([
                'incoming_stock_id' => $incomingStock->id,
                'supplier_id' => $incomingStock->supplier_id,
                'qty_retur' => $v['qty_retur'],
                'alasan_retur' => $v['alasan_retur'],
                'status' => 'sent', // Langsung dikirim berdasarkan existing status flow
                'created_by' => $request->user()->id,
            ]);

            // Deduct Incoming Stock quantities (Katalog Faktur)
            $qty_terkecil = $v['qty_retur'];
            $incomingStock->qty_converted -= $qty_terkecil;
            if ($incomingStock->qty_fisik_verify !== null && $incomingStock->qty_fisik_verify >= $qty_terkecil) {
                $incomingStock->qty_fisik_verify -= $qty_terkecil;
            }
            $incomingStock->save();

            // Deduct Warehouse Inventory (Kasir sync)
            $wi = WarehouseInventory::where('incoming_stock_id', $incomingStock->id)
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

            AuditTrail::log(
                $request->user()->id,
                'RETURN_SUPPLIER',
                "Retur obat " . ($incomingStock->medicine ? $incomingStock->medicine->nama_obat : 'Unknown') . " sejumlah {$qty_terkecil} ke supplier",
                ReturnForm::class,
                $returnForm->id
            );

            return redirect()->back()->with('success', 'Retur ke supplier berhasil diproses dan stok telah disesuaikan.');
        });
    }

    public function distribution(Request $request)
    {
        $kasirs = User::where('role', 'kasir')->get(['id', 'name']);
        $selectedKasirId = $request->query('kasir_id');
        if (!$selectedKasirId && $kasirs->isNotEmpty()) {
            $selectedKasirId = $kasirs->first()->id;
        }
        $search = $request->query('search', '');
        
        $medicines = collect();
        if ($selectedKasirId) {
            $query = Medicine::with([
                'category',
                'latestIncomingStock',
                'warehouseInventories' => function($q) use ($selectedKasirId) {
                    $q->where('stock_on_hand', '>', 0)
                      ->where(function($sq) use ($selectedKasirId) {
                          $sq->where('location_type', 'gudang')
                             ->orWhere(function($ssq) use ($selectedKasirId) {
                                 $ssq->where('location_type', 'kasir')->where('location_id', $selectedKasirId);
                             });
                      })
                      ->orderBy('expired_date', 'asc');
                }
            ])->whereHas('warehouseInventories', function($q) use ($selectedKasirId) {
                $q->where('stock_on_hand', '>', 0)
                  ->where(function($sq) use ($selectedKasirId) {
                      $sq->where('location_type', 'gudang')
                         ->orWhere(function($ssq) use ($selectedKasirId) {
                             $ssq->where('location_type', 'kasir')->where('location_id', $selectedKasirId);
                         });
                  });
            });
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama_obat', 'like', "%{$search}%")
                      ->orWhere('kode_obat', 'like', "%{$search}%")
                      ->orWhereHas('warehouseInventories', function($sq) use ($search) {
                          $sq->where('batch_no', 'like', "%{$search}%")
                             ->orWhere('shelf_location', 'like', "%{$search}%")
                             ->orWhere('rack_location', 'like', "%{$search}%");
                      });
                });
            }
            
            $medicinesData = $query->paginate(12)->withQueryString();
            
            // Map the medicines to include aggregate totals, base unit, and packaging info
            $medicinesData->getCollection()->transform(function ($medicine) {
                $gudangStock = (float) $medicine->warehouseInventories->where('location_type', 'gudang')->sum('stock_on_hand');
                $kasirStock = (float) $medicine->warehouseInventories->where('location_type', 'kasir')->sum('stock_on_hand');
                
                $satuanTerkecil = $medicine->satuan_stok ?: ($medicine->base_unit ?: 'Pcs');
                $medicine->stok_gudang = $gudangStock;
                $medicine->stok_kasir = $kasirStock;
                $medicine->satuan_terkecil = $satuanTerkecil;
                
                // Ambil data kemasan dari incoming_stock terakhir atau master obat
                $latestIncoming = $medicine->latestIncomingStock;
                $kemasan = $satuanTerkecil;
                if ($latestIncoming) {
                    if ($latestIncoming->satuan_input && $latestIncoming->mid_unit && $latestIncoming->small_to_mid_ratio > 0) {
                        $kemasan = "1 {$latestIncoming->satuan_input} = {$latestIncoming->mid_to_big_ratio} {$latestIncoming->mid_unit} × {$latestIncoming->small_to_mid_ratio} {$satuanTerkecil}";
                    } elseif ($latestIncoming->satuan_input && $latestIncoming->unit_conversion_factor > 1) {
                        $kemasan = "1 {$latestIncoming->satuan_input} = {$latestIncoming->unit_conversion_factor} {$satuanTerkecil}";
                    } elseif ($latestIncoming->satuan_input && strtolower($latestIncoming->satuan_input) !== strtolower($satuanTerkecil)) {
                        $kemasan = "{$latestIncoming->satuan_input} / {$satuanTerkecil}";
                    }
                } elseif ($medicine->satuan_besar && $medicine->rasio_konversi > 1) {
                    $kemasan = "1 {$medicine->satuan_besar} = {$medicine->rasio_konversi} {$satuanTerkecil}";
                }
                $medicine->kemasan = $kemasan;
                
                return $medicine;
            });
            
            $medicines = $medicinesData;
        }

        return Inertia::render('Control/Admin/Distribution', [
            'kasirs' => $kasirs,
            'selectedKasirId' => $selectedKasirId,
            'medicines' => $medicines,
            'filters' => ['search' => $search]
        ]);
    }

    public function processDistribution(Request $request)
    {
        $v = $request->validate([
            'kasir_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.action' => 'required|in:transfer,return',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);
        
        return DB::transaction(function () use ($v, $request) {
            $kasirId = $v['kasir_id'];
            
            foreach ($v['items'] as $item) {
                $medicineId = $item['medicine_id'];
                $qtyNeeded = floatval($item['quantity']);
                $action = $item['action'];
                
                if ($action === 'transfer') {
                    // Transfer: Gudang -> Kasir
                    // 1. Ambil stok Gudang dengan FEFO
                    $gudangInventories = WarehouseInventory::where('medicine_id', $medicineId)
                        ->gudang()
                        ->where('stock_on_hand', '>', 0)
                        ->orderBy('expired_date', 'asc')
                        ->lockForUpdate()
                        ->get();
                        
                    $totalAvailable = $gudangInventories->sum('stock_on_hand');
                    if ($totalAvailable < $qtyNeeded) {
                        throw new Exception("Stok gudang tidak mencukupi untuk obat ID {$medicineId}.");
                    }
                    
                    foreach ($gudangInventories as $sourceInv) {
                        if ($qtyNeeded <= 0) break;
                        
                        $deductAmount = min($sourceInv->stock_on_hand, $qtyNeeded);
                        
                        // Deduct from Gudang
                        $sourceInv->setMutationContext('internal_transfer', null, "Distribusi ke Kasir ID: {$kasirId}");
                        $sourceInv->stock_on_hand -= $deductAmount;
                        $sourceInv->save();
                        
                        // Add to Kasir
                        $destInv = WarehouseInventory::firstOrNew([
                            'incoming_stock_id' => $sourceInv->incoming_stock_id,
                            'medicine_id' => $sourceInv->medicine_id,
                            'batch_no' => $sourceInv->batch_no,
                            'expired_date' => $sourceInv->expired_date,
                            'location_type' => 'kasir',
                            'location_id' => $kasirId
                        ]);
                        
                        $destInv->stock_on_hand += $deductAmount;
                        $destInv->status = $sourceInv->status;
                        $destInv->is_frozen = $sourceInv->is_frozen;
                        $destInv->setMutationContext('internal_transfer', null, "Terima Distribusi dari Gudang (Admin: {$request->user()->id})");
                        $destInv->save();
                        
                        $qtyNeeded -= $deductAmount;
                    }

                    AuditTrail::log(
                        $request->user()->id,
                        'inventory.distribution',
                        "Distribusi stok obat ID {$medicineId} sebanyak {$item['quantity']} dari gudang ke kasir ID {$kasirId}",
                        WarehouseInventory::class,
                        $destInv?->id ?? null
                    );
                    
                } else {
                    // Return: Kasir -> Gudang
                    // 1. Ambil stok Kasir dengan FEFO
                    $kasirInventories = WarehouseInventory::where('medicine_id', $medicineId)
                        ->kasir($kasirId)
                        ->where('stock_on_hand', '>', 0)
                        ->orderBy('expired_date', 'asc')
                        ->lockForUpdate()
                        ->get();
                        
                    $totalAvailable = $kasirInventories->sum('stock_on_hand');
                    if ($totalAvailable < $qtyNeeded) {
                        throw new Exception("Stok kasir tidak mencukupi untuk obat ID {$medicineId}.");
                    }
                    
                    $destInv = null;
                    foreach ($kasirInventories as $sourceInv) {
                        if ($qtyNeeded <= 0) break;
                        
                        $deductAmount = min($sourceInv->stock_on_hand, $qtyNeeded);
                        
                        // Deduct from Kasir
                        $sourceInv->setMutationContext('internal_transfer', null, "Return dari Kasir ID: {$kasirId} ke Gudang");
                        $sourceInv->stock_on_hand -= $deductAmount;
                        $sourceInv->save();
                        
                        // Add to Gudang
                        $destInv = WarehouseInventory::firstOrNew([
                            'incoming_stock_id' => $sourceInv->incoming_stock_id,
                            'medicine_id' => $sourceInv->medicine_id,
                            'batch_no' => $sourceInv->batch_no,
                            'expired_date' => $sourceInv->expired_date,
                            'location_type' => 'gudang',
                            'location_id' => null
                        ]);
                        
                        $destInv->stock_on_hand += $deductAmount;
                        $destInv->status = $sourceInv->status;
                        $destInv->is_frozen = $sourceInv->is_frozen;
                        $destInv->setMutationContext('internal_transfer', null, "Return Distribusi dari Kasir ID: {$kasirId}");
                        $destInv->save();
                        
                        $qtyNeeded -= $deductAmount;
                    }

                    AuditTrail::log(
                        $request->user()->id,
                        'inventory.return_distribution',
                        "Return stok obat ID {$medicineId} sebanyak {$item['quantity']} dari kasir ID {$kasirId} ke gudang",
                        WarehouseInventory::class,
                        $destInv?->id ?? null
                    );
                }
            }
            
            return redirect()->back()->with('success', 'Semua distribusi stok berhasil diproses.');
        });
    }
}
