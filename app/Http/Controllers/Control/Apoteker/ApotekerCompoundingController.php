<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\CompoundingDetail;
use App\Models\Control\CompoundingFormula;
use App\Models\Control\CompoundingLabel;
use App\Models\Control\CompoundingStockDeduction;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Models\PrescriptionItem;
use App\Services\StockAuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApotekerCompoundingController extends Controller
{
    /**
     * Display listing of compounding submissions.
     */
    public function submissions()
    {
        return Inertia::render('Control/Apoteker/Submissions', [
            'submissions' => CompoundingSubmission::with(['details.rawMaterial', 'label', 'formula'])->where('user_id', auth()->id())->latest()->paginate(15),
            'formulas' => CompoundingFormula::active()->select('id', 'nama_resep', 'kategori_sediaan')->get(),
            'rawMaterials' => RawMaterial::select('id', 'nama_bahan', 'satuan_ukur')->get(),
        ]);
    }

    /**
     * Store and process a new compounding submission with mass balance and FEFO batch deduction.
     */
    public function storeSubmission(Request $request)
    {
        // Validasi data input dengan penamaan field sesuai spesifikasi terbaru
        $v = $request->validate([
            'nama_racikan' => 'required|string|max:255',
            'formula_id' => 'nullable|exists:compounding_formulas,id',
            'apoteker_note' => 'nullable|string|max:1000',
            'jumlah_unit' => 'required|numeric|min:1', // Target Jadi
            'target_unit' => 'required|string|in:Kapsul,Puyer,Botol,Pot',
            'berat_per_unit' => 'required|numeric|min:0.01', // Berat/Unit (g)
            'bahan_baku' => 'required|array|min:1',
            'bahan_baku.*.id_batch' => 'required|exists:raw_materials,id', // Mapping ke raw_material_id
            'bahan_baku.*.qty_taken_pcs' => 'required|numeric|min:0.01',
            'bahan_baku.*.qty_neto_mass' => 'required|numeric|min:0',
            'label' => 'required|array',
            'label.tanggal_racik' => 'required|date',
            'label.bud_hari' => 'required|integer|min:1',
            'label.aturan_pakai' => 'required|string|max:500',
            'label.keterangan' => 'nullable|string|max:500',
            'label.nama_pasien' => 'nullable|string|max:255',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'prescription_item_id' => 'nullable|exists:prescription_items,id',
        ]);

        $stockErrors = [];
        foreach ($v['bahan_baku'] as $bahan) {
            $rm = RawMaterial::find($bahan['id_batch']);
            if (!$rm) continue;

            $totalAvailable = RawMaterialStock::where('raw_material_id', $bahan['id_batch'])
                ->where('status', 'active')
                ->where('stock_on_hand', '>', 0)
                ->sum('stock_on_hand');

            // Kita cek berdasarkan qty_taken_pcs karena ini yang akan dipotong dari stock_on_hand
            if ($totalAvailable < $bahan['qty_taken_pcs']) {
                $stockErrors[] = $rm->nama_bahan;
            }
        }

        if (!empty($stockErrors)) {
            $errorMsg = "Stok tidak mencukupi untuk: " . implode(', ', $stockErrors);
            return redirect()->back()->with('error', $errorMsg);
        }

        return DB::transaction(function () use ($v, $request) {
            // 1. Kalkulasi Mass Balance & Verifikasi Filler (SOP Logic)
            $totalMassaNetoBahan = round(collect($v['bahan_baku'])->sum('qty_neto_mass'), 2);
            $totalMassaTarget = round($v['jumlah_unit'] * $v['berat_per_unit'], 2);

            // Filler otomatis dibutuhkan jika massa bahan baku < massa target
            $fillerQty = max(0, round($totalMassaTarget - $totalMassaNetoBahan, 2));

            // Estimasi Hasil Unit (Yield)
            $expectedYield = floor(round(($totalMassaNetoBahan + $fillerQty) / $v['berat_per_unit'], 2));

            // 2. Simpan Data Induk Pengajuan Racikan
            $sub = CompoundingSubmission::create([
                'user_id' => $request->user()->id,
                'pharmacist_id' => $request->user()->id,
                'formula_id' => $v['formula_id'] ?? null,
                'nama_racikan' => $v['nama_racikan'],
                'target_quantity' => $v['jumlah_unit'],
                'target_unit' => $v['target_unit'],
                'expected_yield' => $expectedYield,
                'total_net_mass' => $totalMassaNetoBahan,
                'filler_qty' => $fillerQty,
                'unit_weight_target' => $v['berat_per_unit'],
                'apoteker_note' => $v['apoteker_note'] ?? null,
                'status' => 'pending_final_verification',
                'prescription_id' => $v['prescription_id'] ?? null,
                'prescription_item_id' => $v['prescription_item_id'] ?? null,
            ]);

            $totalBaseCost = 0;

            // 3. Proses Detail Bahan & Pengurangan Stok (FEFO)
            foreach ($v['bahan_baku'] as $d) {
                $rm = RawMaterial::findOrFail($d['id_batch']);
                $fefo = CompoundingDetail::suggestFEFO($d['id_batch'], 'raw_material');

                $detail = CompoundingDetail::create([
                    'compounding_id' => $sub->id,
                    'raw_material_id' => $d['id_batch'],
                    'stock_batch_id' => $fefo?->id,
                    'source_type' => 'raw_material',
                    'qty_needed' => $d['qty_taken_pcs'],
                    'net_quantity' => $d['qty_neto_mass'],
                    'volume_to_mass_factor' => $rm->density_factor ?? 1.0,
                    'suggested_batch_no' => $fefo?->batch_no,
                    'suggested_expired_date' => $fefo?->expired_date,
                    'harga_satuan_saat_pakai' => $rm->harga_beli_satuan,
                ]);

                $totalBaseCost += ($detail->qty_needed * $detail->harga_satuan_saat_pakai);

                // Pengurangan Stok Otomatis sesuai Batch FEFO
                $remaining = $d['qty_taken_pcs'];
                $batches = RawMaterialStock::where('raw_material_id', $rm->id)
                    ->where('stock_on_hand', '>', 0)
                    ->notExpired()->fefo()->get();

                foreach ($batches as $batch) {
                    if ($remaining <= 0)
                        break;
                    $deduct = min($remaining, $batch->stock_on_hand);
                    $batch->setMutationContext('out', $sub, "Bahan racikan: {$sub->nama_racikan}");
                    $batch->stock_on_hand -= $deduct;
                    $batch->save();

                    CompoundingStockDeduction::create([
                        'compounding_detail_id' => $detail->id,
                        'stock_source_id' => $batch->id,
                        'stock_source_type' => RawMaterialStock::class,
                        'qty_deducted' => $deduct,
                    ]);
                    $remaining -= $deduct;
                }
                $rm->recalculateStock();

                StockAuditService::log(
                    $rm,
                    'Pengurangan Racikan',
                    (float) ($rm->stok_total + $d['qty_taken_pcs']),
                    (float) $rm->stok_total,
                    "Bahan racikan: {$sub->nama_racikan} (#{$sub->id})"
                );
            }

            // 4. Buat Record Produksi & Label Etiket
            $cp = CompoundedProduct::create([
                'compounding_submission_id' => $sub->id,
                'nama_produk' => $sub->nama_racikan,
                'batch_no' => 'RAC-' . now()->format('Ymd') . '-' . str_pad($sub->id, 4, '0', STR_PAD_LEFT),
                'tanggal_racik' => $v['label']['tanggal_racik'],
                'bud_date' => Carbon::parse($v['label']['tanggal_racik'])->addDays($v['label']['bud_hari']),
                'qty_hasil' => $v['jumlah_unit'],
                'satuan' => $v['target_unit'],
                'base_cost' => $totalBaseCost,
                'status' => 'inactive',
                'pricing_status' => 'pending_price',
                'created_by' => $request->user()->id,
            ]);

            CompoundingLabel::create([
                'compounding_id' => $sub->id,
                'tanggal_racik' => $v['label']['tanggal_racik'],
                'bud_hari' => $v['label']['bud_hari'],
                'tanggal_kadaluarsa_bud' => $cp->bud_date,
                'aturan_pakai' => $v['label']['aturan_pakai'],
                'keterangan' => $v['label']['keterangan'] ?? null,
                'nama_pasien' => $v['label']['nama_pasien'] ?? null,
            ]);

            $sub->update(['total_hpp_bahan' => $totalBaseCost]);

            // Link ke item resep jika berasal dari pengerjaan resep
            if (!empty($v['prescription_item_id'])) {
                $pItem = PrescriptionItem::find($v['prescription_item_id']);
                if ($pItem) {
                    $pItem->update([
                        'compounding_id' => $sub->id,
                        'compounded_product_id' => $cp->id,
                        'status' => 'selesai',
                    ]);
                }
            }

            AuditTrail::log($request->user()->id, 'CREATE_COMPOUNDING', "Submit Racikan: {$sub->nama_racikan}. Stok batch dikunci.", CompoundingSubmission::class, $sub->id);

            return redirect()->back()->with('success', 'Pengajuan racikan berhasil. Menunggu penetapan harga jual dari Admin.');
        });
    }

    /**
     * Display details of a compounding submission.
     */
    public function showSubmission(CompoundingSubmission $submission)
    {
        $submission->load(['details.medicine', 'label', 'formula', 'user', 'approver']);
        return Inertia::render('Control/Apoteker/SubmissionDetail', ['submission' => $submission]);
    }

    /**
     * Display Beyond Use Date (BUD) tracking monitor.
     */
    public function budTracker()
    {
        $activeCompounding = CompoundingSubmission::with(['label', 'user'])
            ->whereHas('label', function ($q) {
                $q->whereNotNull('tanggal_kadaluarsa_bud');
            })
            ->where('status', 'approved')
            ->latest()
            ->paginate(15);

        return Inertia::render('Control/Apoteker/BudTracker', [
            'submissions' => $activeCompounding,
        ]);
    }

    /**
     * Get FEFO suggestion for a specific stock item.
     */
    public function fefoSuggestion(Request $request, int $itemId)
    {
        $type = $request->query('type', 'raw_material');
        return response()->json(['suggestion' => CompoundingDetail::suggestFEFO($itemId, $type)]);
    }

    /**
     * Cancel compounding submission and refund deducted stocks back to source batches.
     */
    public function cancelSubmission(Request $request, CompoundingSubmission $submission)
    {
        return DB::transaction(function () use ($submission, $request) {
            // Guard: Only pending can be cancelled automatically
            if ($submission->status === 'approved') {
                return redirect()->back()->with('error', 'Racikan yang sudah disetujui tidak dapat dibatalkan secara otomatis demi keamanan medis. Silakan hubungi admin untuk penyesuaian stok.');
            }

            if (!in_array($submission->status, ['pending_physical_check', 'pending_final_verification'])) {
                return redirect()->back()->with('error', 'Hanya pengajuan dengan status Pending yang dapat dibatalkan.');
            }

            // Guard: Authority check (Apoteker who submitted or Admin)
            if ($submission->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
                return redirect()->back()->with('error', 'Anda tidak memiliki otoritas untuk membatalkan pengajuan ini.');
            }

            $submission->update(['status' => 'cancelled']);

            // --- REVERSAL LOGIC: Refund stock to original batches ---
            foreach ($submission->details as $detail) {
                foreach ($detail->deductions as $deduction) {
                    $source = $deduction->stockSource;
                    if ($source) {
                        $source->increment('stock_on_hand', $deduction->qty_deducted);

                        // Log to Stock Logs for transparency
                        $rm = $detail->rawMaterial;
                        if ($rm) {
                            $qtyBefore = (float) $rm->stok_total;
                            $rm->recalculateStock();
                            StockAuditService::log(
                                $rm,
                                'Pembatalan Racikan',
                                $qtyBefore,
                                (float) $rm->fresh()->stok_total,
                                "Pembatalan Racikan #{$submission->id} - Stok dikembalikan ke Batch #{$source->id}"
                            );
                        }
                    }
                }

                if ($detail->rawMaterial) {
                    $detail->rawMaterial->recalculateStock();
                }
            }

            AuditTrail::log(
                $request->user()->id,
                'CANCEL_COMPOUNDING',
                "Membatalkan racikan: {$submission->nama_racikan} - Stok dikembalikan ke inventaris.",
                CompoundingSubmission::class,
                $submission->id
            );

            return redirect()->back()->with('success', 'Racikan berhasil dibatalkan. Stok telah berhasil dikembalikan ke inventaris.');
        });
    }
}
