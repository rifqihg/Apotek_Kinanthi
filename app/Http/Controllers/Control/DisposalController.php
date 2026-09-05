<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\InventoryLoss;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Services\StockAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DisposalController extends Controller
{
    public function index()
    {
        return Inertia::render('Control/Apoteker/DisposalIndex', [
            'disposals' => InventoryLoss::with(['user', 'rawMaterial'])->latest()->paginate(15),
            'rawMaterials' => RawMaterial::where('stok_total', '>', 0)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'raw_material_id' => 'required|exists:raw_materials,id',
            'qty_lost' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'evidence' => 'required|image|max:5120', // Max 5MB
        ]);

        return DB::transaction(function () use ($v, $request) {
            $rm = RawMaterial::findOrFail($v['raw_material_id']);
            
            if ($rm->stok_total < $v['qty_lost']) {
                return redirect()->back()->with('error', 'Stok tidak mencukupi untuk pemusnahan.');
            }

            $path = $request->file('evidence')->store('evidence/disposals', 'public');
            
            $loss = InventoryLoss::create([
                'raw_material_id' => $v['raw_material_id'],
                'qty_lost' => $v['qty_lost'],
                'reason' => $v['reason'],
                'evidence_path' => $path,
                'user_id' => $request->user()->id,
                'status' => 'pending',
            ]);

            return redirect()->back()->with('success', 'Pengajuan pemusnahan berhasil dikirim. Menunggu persetujuan Admin.');
        });
    }

    public function auditLogs()
    {
        return Inertia::render('Control/Apoteker/StockAudit', [
            'logs' => \App\Models\Control\StockLog::with(['user', 'rawMaterial', 'medicine'])->latest()->paginate(25),
        ]);
    }
}
