<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundingFormula;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApotekerFormulaController extends Controller
{
    /**
     * Display a listing of active compounding formulas.
     */
    public function formulas()
    {
        return Inertia::render('Control/Apoteker/Formulas', [
            'formulas' => CompoundingFormula::active()->latest()->paginate(15),
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
            'instruksi_racik' => 'nullable|string|max:2000',
        ]);

        $f = CompoundingFormula::create($v);
        AuditTrail::log($request->user()->id, 'CREATE_FORMULA', "Menambah resep: {$f->nama_resep}", CompoundingFormula::class, $f->id);

        return redirect()->back()->with('success', 'Resep berhasil ditambahkan.');
    }
}
