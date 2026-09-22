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
    public function formulas(Request $request)
    {
        $search = $request->query('search');
        $kategori = $request->query('kategori');

        $query = CompoundingFormula::active();

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
            'instruksi_racik' => 'nullable|string|max:2000',
        ]);

        $f = CompoundingFormula::create($v);
        AuditTrail::log($request->user()->id, 'CREATE_FORMULA', "Menambah resep: {$f->nama_resep}", CompoundingFormula::class, $f->id);

        return redirect()->back()->with('success', 'Resep berhasil ditambahkan.');
    }
}
