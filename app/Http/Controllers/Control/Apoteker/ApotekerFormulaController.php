<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundingFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApotekerFormulaController extends Controller
{
    /**
     * Display a listing of active compounding formulas.
     */
    public function formulas(Request $request)
    {
        $search = $request->query('search');
        $kategori = $request->query('kategori');

        $query = CompoundingFormula::with(['ingredients.medicine', 'ingredients.rawMaterial'])->active();

        if ($request->filled('kategori') && $kategori !== 'all') {
            $query->where('kategori_sediaan', 'like', "%{$kategori}%");
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $cleanedId = preg_replace('/[^0-9]/', '', $search);
                if (!empty($cleanedId)) {
                    $q->orWhere('id', (int) $cleanedId);
                }
                $q->orWhere('nama_resep', 'like', "%{$search}%")
                  ->orWhere('kategori_sediaan', 'like', "%{$search}%")
                  ->orWhere('instruksi_racik', 'like', "%{$search}%");
            });
        }

        $formulas = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Control/Apoteker/Formulas', [
            'formulas' => $formulas,
            'medicines' => \App\Models\Control\Medicine::select('id', 'nama_obat', 'satuan_stok')->orderBy('nama_obat')->get(),
            'rawMaterials' => \App\Models\Control\RawMaterial::select('id', 'nama_bahan', 'satuan_dasar_penggunaan as satuan')->where('status', 'active')->orderBy('nama_bahan')->get(),
            'filters' => [
                'search' => $search ?? '',
                'kategori' => $kategori ?? 'all',
            ],
        ]);
    }

    /**
     * Store a newly created formula template.
     */
    public function storeFormula(Request $request)
    {
        $v = $request->validate([
            'nama_resep' => 'required|string|max:255',
            'kategori_sediaan' => 'nullable|string|max:100',
            'target_qty' => 'required|numeric|min:0.01',
            'target_unit' => 'required|string|max:50',
            'signa_default' => 'nullable|string|max:255',
            'bud_days' => 'required|integer|min:1',
            'instruksi_racik' => 'nullable|string|max:2000',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.source_type' => 'required|in:medicine,raw_material',
            'ingredients.*.source_id' => 'required|integer',
            'ingredients.*.qty_required' => 'required|numeric|min:0.01',
            'ingredients.*.unit_label' => 'nullable|string',
        ]);

        DB::transaction(function () use ($v, $request) {
            $f = CompoundingFormula::create([
                'nama_resep' => $v['nama_resep'],
                'kategori_sediaan' => $v['kategori_sediaan'],
                'target_qty' => $v['target_qty'],
                'target_unit' => $v['target_unit'],
                'signa_default' => $v['signa_default'],
                'bud_days' => $v['bud_days'],
                'instruksi_racik' => $v['instruksi_racik'],
            ]);

            foreach ($v['ingredients'] as $ing) {
                $f->ingredients()->create([
                    'source_type' => $ing['source_type'],
                    'source_id' => $ing['source_id'],
                    'qty_required' => $ing['qty_required'],
                    'unit' => $ing['unit_label'] ?? null,
                ]);
            }

            AuditTrail::log($request->user()->id, 'CREATE_FORMULA', "Menambah resep: {$f->nama_resep}", CompoundingFormula::class, $f->id);
        });

        return redirect()->back()->with('success', 'Resep berhasil ditambahkan.');
    }
}
