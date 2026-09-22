<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Control\Medicine;
use App\Models\Control\CompoundedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApotekerPrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $date = $request->query('date');

        $query = Prescription::with(['patient', 'prescriptionItems.medicine']);

        if ($request->filled('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        } else {
            $query->whereNotIn('status', ['selesai', 'batal']);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $date);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('prescription_number', 'like', "%{$search}%")
                  ->orWhere('doctor_name', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($qp) use ($search) {
                      $qp->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $prescriptions = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Control/Apoteker/Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $date,
            ],
        ]);
    }

    public function create()
    {
        $patients = Patient::orderBy('name')->get(['id', 'name']);
        $medicines = Medicine::orderBy('nama_obat')->get(['id', 'nama_obat', 'satuan_stok', 'classification']);
        $compounded_products = CompoundedProduct::where('harga_jual', '>', 0)
            ->orderBy('nama_produk')
            ->get(['id', 'nama_produk', 'satuan']);

        return Inertia::render('Control/Apoteker/Prescriptions/Create', [
            'patients' => $patients,
            'medicines' => $medicines,
            'compounded_products' => $compounded_products,
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'patient_weight_kg' => 'nullable|numeric|min:0|max:300',
            'patient_age_years' => 'nullable|integer|min:0|max:150',
            'doctor_name' => 'required|string|max:100',
            'doctor_sip' => 'nullable|string|max:60',
            'clinic_name' => 'nullable|string|max:100',
            'prescription_date' => [
                'required',
                'date',
                'after_or_equal:' . now()->subDays(30)->toDateString()
            ],
            'notes' => 'nullable|string',
            'screening_notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:obat_jadi,racikan',
            'items.*.medicine_id' => 'required_if:items.*.item_type,obat_jadi|nullable|exists:medicines,id',
            'items.*.compounded_product_id' => 'nullable|exists:compounded_products,id',
            'items.*.custom_formula_name' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:20',
            'items.*.usage_instructions' => 'nullable|string',
        ]);

        // Validasi: Jika resep memuat obat bertipe 'narkotika' atau 'psikotropika', kolom 'doctor_sip' wajib diisi
        $hasControlledDrug = false;
        foreach ($v['items'] as $item) {
            if ($item['item_type'] === 'obat_jadi' && !empty($item['medicine_id'])) {
                $med = Medicine::find($item['medicine_id']);
                if ($med && in_array($med->classification, ['narkotika', 'psikotropika'])) {
                    $hasControlledDrug = true;
                    break;
                }
            }
        }

        if ($hasControlledDrug && empty($v['doctor_sip'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'doctor_sip' => 'Resep yang memuat obat golongan Narkotika atau Psikotropika wajib mencantumkan Nomor SIP Dokter.'
            ]);
        }

        return DB::transaction(function () use ($v) {
            $prescription = Prescription::create([
                'patient_id' => $v['patient_id'],
                'patient_weight_kg' => $v['patient_weight_kg'] ?? null,
                'patient_age_years' => $v['patient_age_years'] ?? null,
                'doctor_name' => $v['doctor_name'],
                'doctor_sip' => $v['doctor_sip'] ?? null,
                'clinic_name' => $v['clinic_name'] ?? null,
                'prescription_date' => $v['prescription_date'],
                'status' => 'baru',
                'handled_by' => auth()->id(),
                'notes' => $v['notes'] ?? null,
                'screening_notes' => $v['screening_notes'] ?? null,
            ]);

            foreach ($v['items'] as $item) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'item_type' => $item['item_type'],
                    'medicine_id' => $item['medicine_id'] ?? null,
                    'compounded_product_id' => $item['compounded_product_id'] ?? null,
                    'custom_formula_name' => $item['custom_formula_name'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'usage_instructions' => $item['usage_instructions'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return redirect()->route('control.apoteker.prescriptions.index')
                ->with('success', 'Resep berhasil disimpan.');
        });
    }

    public function show($id)
    {
        $prescription = Prescription::with([
            'patient',
            'prescriptionItems.medicine',
            'prescriptionItems.compoundedProduct',
            'prescriptionItems.compoundingSubmission',
            'handler'
        ])->findOrFail($id);

        return Inertia::render('Control/Apoteker/Prescriptions/Show', [
            'prescription' => $prescription,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $prescription = Prescription::findOrFail($id);

        $v = $request->validate([
            'status' => 'required|string',
        ]);

        $newStatus = $v['status'];
        $currentStatus = $prescription->status;

        $validTransitions = [
            'baru' => 'diproses',
            'diproses' => 'siap_ambil',
        ];

        if (!array_key_exists($currentStatus, $validTransitions) || $validTransitions[$currentStatus] !== $newStatus) {
            return back()->withErrors(['status' => 'Perubahan status tidak valid.']);
        }

        if ($newStatus === 'diproses') {
            $daysOld = \Carbon\Carbon::parse($prescription->prescription_date)->diffInDays(now());
            if ($daysOld > 30) {
                return back()->withErrors(['status' => 'Resep sudah kadaluarsa (lebih dari 30 hari). Tidak dapat diproses.']);
            }
        }

        if ($newStatus === 'siap_ambil') {
            $hasUnfinishedItems = !$prescription->prescriptionItems()->exists() 
                || $prescription->prescriptionItems()->where('status', '!=', 'selesai')->exists();

            if ($hasUnfinishedItems) {
                return back()->withErrors(['status' => 'Semua item resep harus diselesaikan terlebih dahulu sebelum resep siap diambil.']);
            }

            // Periksa apakah terdapat item racikan yang belum divalidasi harganya oleh Admin
            $hasPendingPricing = false;
            $racikanItems = $prescription->prescriptionItems()->where('item_type', 'racikan')->get();
            foreach ($racikanItems as $rItem) {
                $cp = $rItem->compoundedProduct ?: ($rItem->compounded_product_id ? CompoundedProduct::find($rItem->compounded_product_id) : null);
                if (!$cp && $rItem->compounding_id) {
                    $cp = CompoundedProduct::where('compounding_submission_id', $rItem->compounding_id)->first();
                }
                if (!$cp || $cp->pricing_status !== 'priced' || (float) $cp->harga_jual <= 0) {
                    $hasPendingPricing = true;
                    break;
                }
            }

            if ($hasPendingPricing) {
                // Pertahankan status 'diproses' agar kasir TIDAK membacanya sebagai antrean siap bayar
                $prescription->update(['status' => 'diproses']);
                return back()->with('info', 'Item racikan telah selesai disiapkan. Resep tetap berstatus "diproses" menunggu validasi harga dari Admin sebelum siap diambil di kasir.');
            }
        }

        $prescription->update(['status' => $newStatus]);

        return back()->with('success', 'Status resep berhasil diperbarui.');
    }

    public function storePatient(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $patient = Patient::create($v);

        return response()->json([
            'id' => $patient->id,
            'name' => $patient->name,
        ], 201);
    }

    public function startCompounding($id)
    {
        $item = PrescriptionItem::with('prescription')->findOrFail($id);

        if ($item->item_type !== 'racikan') {
            return back()->with('error', 'Hanya item racikan yang dapat dikerjakan.');
        }

        if ($item->status !== 'pending') {
            return back()->with('error', 'Item ini sudah dikerjakan atau selesai.');
        }

        if ($item->prescription->status !== 'diproses') {
            return back()->with('error', 'Resep belum dalam status diproses.');
        }

        $daysOld = \Carbon\Carbon::parse($item->prescription->prescription_date)->diffInDays(now());
        if ($daysOld > 30) {
            return back()->with('error', 'Resep sudah kadaluarsa (lebih dari 30 hari). Tidak dapat diproses.');
        }

        $existing = \App\Models\Control\CompoundingSubmission::where('prescription_item_id', $item->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();

        if ($existing) {
            return redirect()->route('control.apoteker.submissions.show', $existing->id)
                ->with('info', 'Racikan untuk item ini sudah dibuat.');
        }

        DB::transaction(function () use ($item) {
            $item->update(['status' => 'dikerjakan']);
        });

        // The form for creating a submission is on the submissions index page (inside a modal)
        return redirect()->route('control.apoteker.submissions', [
            'prescription_item_id' => $item->id,
            'prescription_id' => $item->prescription_id,
            'nama_racikan' => $item->custom_formula_name ?? '',
            'jumlah_unit' => $item->quantity,
            'target_unit' => $item->unit,
        ]);
    }

    public function completeItem($id)
    {
        $item = PrescriptionItem::with('prescription', 'medicine', 'compoundedProduct')->findOrFail($id);

        if ($item->prescription->status !== 'diproses') {
            return back()->with('error', 'Resep belum dalam status diproses.');
        }

        $item->update(['status' => 'selesai']);

        $name = $item->medicine?->nama_obat ?? ($item->compoundedProduct?->nama_produk ?? $item->custom_formula_name ?? 'Item');
        return back()->with('success', "Item {$name} berhasil disiapkan.");
    }
}
