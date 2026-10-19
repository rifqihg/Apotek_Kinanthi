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
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Control\RawMaterialStock;
use App\Models\Control\WarehouseInventory;
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
    public function submissions(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $unit = $request->query('unit');

        $query = CompoundingSubmission::with(['details.rawMaterial', 'details.medicine', 'label', 'formula', 'user', 'compoundedProduct'])
            ->where('user_id', auth()->id());

        if ($request->filled('status') && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('unit') && $unit !== 'all') {
            $query->where('target_unit', $unit);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_racikan', 'like', "%{$search}%")
                  ->orWhere('target_unit', 'like', "%{$search}%")
                  ->orWhereHas('formula', function ($qf) use ($search) {
                      $qf->where('nama_resep', 'like', "%{$search}%")
                         ->orWhere('kategori_sediaan', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $submissions = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Control/Apoteker/Submissions', [
            'submissions' => $submissions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unit' => $unit,
            ],
        ]);
    }

    /**
     * Async load of reference data for Submissions modal to keep SPA navigation instant
     */
    public function submissionsReference()
    {
        return response()->json([
            'formulas' => CompoundingFormula::with('ingredients')->active()->get(),
            'rawMaterials' => RawMaterial::select('id', 'nama_bahan', 'satuan_dasar_penggunaan', 'satuan_pembelian', 'rasio_konversi')->get(),
            'medicines' => Medicine::select('id', 'nama_obat', 'satuan_stok', 'stok_total')->where('stok_total', '>', 0)->orderBy('nama_obat')->get(),
        ]);
    }

    /**
     * Store and process a new compounding submission with mass balance and FEFO batch deduction.
     */
    public function storeSubmission(Request $request)
    {
        // Validasi data input fleksibel untuk bahan baku murni maupun obat jadi pabrik
        $v = $request->validate([
            'nama_racikan' => 'required|string|max:255',
            'formula_id' => 'nullable|exists:compounding_formulas,id',
            'apoteker_note' => 'nullable|string|max:1000',
            'jumlah_unit' => 'required|numeric|min:1', // Target Jadi
            'target_unit' => 'required|string|in:Kapsul,Puyer,Botol,Pot,Tube,Sachet',
            'berat_per_unit' => 'required|numeric|min:0', // 0 diizinkan jika sediaan non-massa (sirup/cair)
            'bahan_baku' => 'required|array|min:1',
            'bahan_baku.*.source_type' => 'nullable|in:raw_material,medicine',
            'bahan_baku.*.id_batch' => 'required', // ID raw_material atau medicine
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
            $sourceType = $bahan['source_type'] ?? 'raw_material';
            $itemId = $bahan['id_batch'];

            if ($sourceType === 'medicine') {
                $med = Medicine::find($itemId);
                if (!$med) continue;

                $totalAvailable = (float) WarehouseInventory::where('medicine_id', $med->id)
                    ->where('location_type', 'gudang')
                    ->where('stock_on_hand', '>', 0)
                    ->where('status', 'active')
                    ->where('is_frozen', false)
                    ->whereDate('expired_date', '>', now())
                    ->sum('stock_on_hand');

                if ($totalAvailable < (float) $bahan['qty_taken_pcs']) {
                    $stockErrors[] = "{$med->nama_obat} (Obat Jadi - Sisa: {$totalAvailable})";
                }
            } else {
                $rm = RawMaterial::find($itemId);
                if (!$rm) continue;

                $totalAvailable = (float) RawMaterialStock::where('raw_material_id', $rm->id)
                    ->where('status', 'active')
                    ->where('stock_on_hand', '>', 0)
                    ->notExpired()
                    ->sum('stock_on_hand');

                if ($totalAvailable < (float) $bahan['qty_taken_pcs']) {
                    $stockErrors[] = "{$rm->nama_bahan} (Bahan Baku - Sisa: {$totalAvailable})";
                }
            }
        }

        if (!empty($stockErrors)) {
            $errorMsg = "Stok aktif tidak mencukupi untuk: " . implode(', ', $stockErrors);
            return redirect()->back()->with('error', $errorMsg);
        }

        // Validasi USP <795>: Tanggal BUD tidak boleh melebihi tanggal kedaluwarsa fisik terdekat dari bahan-bahan penyusunnya
        $calculatedBud = Carbon::parse($v['label']['tanggal_racik'])->addDays((int) $v['label']['bud_hari'])->endOfDay();
        $earliestExpiry = null;
        $earliestItemName = null;

        foreach ($v['bahan_baku'] as $bahan) {
            $sourceType = $bahan['source_type'] ?? 'raw_material';
            $itemId = $bahan['id_batch'];

            if ($sourceType === 'medicine') {
                $nearestBatchExp = WarehouseInventory::where('medicine_id', $itemId)
                    ->where('location_type', 'gudang')
                    ->where('stock_on_hand', '>', 0)
                    ->where('status', 'active')
                    ->where('is_frozen', false)
                    ->whereDate('expired_date', '>', now())
                    ->orderBy('expired_date', 'asc')
                    ->value('expired_date');
                
                if ($nearestBatchExp) {
                    $exp = Carbon::parse($nearestBatchExp)->endOfDay();
                    if ($earliestExpiry === null || $exp->lt($earliestExpiry)) {
                        $earliestExpiry = $exp;
                        $earliestItemName = Medicine::find($itemId)?->nama_obat ?? 'Obat Jadi';
                    }
                }
            } else {
                $nearestBatchExp = RawMaterialStock::where('raw_material_id', $itemId)
                    ->where('status', 'active')
                    ->where('stock_on_hand', '>', 0)
                    ->notExpired()
                    ->orderBy('expired_date', 'asc')
                    ->value('expired_date');

                if ($nearestBatchExp) {
                    $exp = Carbon::parse($nearestBatchExp)->endOfDay();
                    if ($earliestExpiry === null || $exp->lt($earliestExpiry)) {
                        $earliestExpiry = $exp;
                        $earliestItemName = RawMaterial::find($itemId)?->nama_bahan ?? 'Bahan Baku';
                    }
                }
            }
        }

        if ($earliestExpiry && $calculatedBud->gt($earliestExpiry)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label.bud_hari' => "Sesuai standar USP <795>, Beyond Use Date ({$calculatedBud->format('d/m/Y')}) tidak boleh melebihi tanggal kedaluwarsa fisik terdekat bahan '{$earliestItemName}' ({$earliestExpiry->format('d/m/Y')}). Kurangi durasi BUD hari."
            ]);
        }

        try {
            return DB::transaction(fn () => $this->processSubmissionTransaction($v, $request->user()));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Process transaction logic for storing compounding submission.
     */
    private function processSubmissionTransaction(array $v, $user)
    {
        // 1. Kalkulasi Mass Balance & Verifikasi Filler (SOP Logic)
        $totalMassaNetoBahan = round(collect($v['bahan_baku'])->sum('qty_neto_mass'), 2);
        $totalMassaTarget = round($v['jumlah_unit'] * $v['berat_per_unit'], 2);

        // Filler otomatis dibutuhkan jika massa bahan baku < massa target
        $fillerQty = max(0, round($totalMassaTarget - $totalMassaNetoBahan, 2));

        // Estimasi Hasil Unit (Yield)
        $expectedYield = floor(round(($totalMassaNetoBahan + $fillerQty) / $v['berat_per_unit'], 2));

        // 2. Simpan Data Induk Pengajuan Racikan
        /** @var CompoundingSubmission $sub */
        $sub = CompoundingSubmission::create([
            'user_id' => $user->id,
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

        // 3. Proses Detail Bahan & Pengurangan Stok (FEFO dengan Pessimistic Lock)
        foreach ($v['bahan_baku'] as $d) {
            $sourceType = $d['source_type'] ?? 'raw_material';
            $itemId = $d['id_batch'];
            $fefo = CompoundingDetail::suggestFEFO($itemId, $sourceType);

            if ($sourceType === 'medicine') {
                $med = Medicine::with('latestIncomingStock')->findOrFail($itemId);
                $hppUnit = (float) ($med->latestIncomingStock?->calculateHppSatuan() ?? ($med->selling_price > 0 ? $med->selling_price : 0));

                $detail = CompoundingDetail::create([
                    'compounding_id' => $sub->id,
                    'medicine_id' => $med->id,
                    'raw_material_id' => null,
                    'stock_batch_id' => null,
                    'source_type' => 'medicine',
                    'qty_needed' => $d['qty_taken_pcs'],
                    'net_quantity' => $d['qty_neto_mass'],
                    'volume_to_mass_factor' => 1.0,
                    'suggested_batch_no' => $fefo?->batch_no,
                    'suggested_expired_date' => $fefo?->expired_date,
                    'harga_satuan_saat_pakai' => $hppUnit,
                ]);

                $totalBaseCost += ($detail->qty_needed * $detail->harga_satuan_saat_pakai);

                // Pengurangan Stok Otomatis sesuai Batch FEFO WarehouseInventory dengan Kunci Pesimistik (Race-Free)
                $remaining = (float) $d['qty_taken_pcs'];
                /** @var \Illuminate\Database\Eloquent\Collection<int, WarehouseInventory> $inventories */
                $inventories = WarehouseInventory::where('medicine_id', $med->id)
                    ->where('location_type', 'gudang')
                    ->where('stock_on_hand', '>', 0)
                    ->where('status', 'active')
                    ->where('is_frozen', false)
                    ->whereDate('expired_date', '>', now())
                    ->orderBy('expired_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = (float) $inventories->sum('stock_on_hand');
                if ($totalAvailable < $remaining) {
                    throw new \Exception("Stok tidak mencukupi untuk obat jadi '{$med->nama_obat}' saat penguncian batch. Dibutuhkan: {$remaining}, Tersedia: {$totalAvailable}.");
                }

                /** @var WarehouseInventory $inv */
                foreach ($inventories as $inv) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, (float) $inv->stock_on_hand);
                    $inv->setMutationContext('out', $sub, "Bahan Racikan: {$sub->nama_racikan}");
                    $inv->stock_on_hand = (string) round((float) $inv->stock_on_hand - $deduct, 2);
                    $inv->save();

                    if ($inv->incomingStock) {
                        $incoming = $inv->incomingStock;
                        $incoming->qty_converted = max(0, $incoming->qty_converted - $deduct);
                        $incoming->saveQuietly();
                    }

                    CompoundingStockDeduction::create([
                        'compounding_detail_id' => $detail->id,
                        'stock_source_id' => $inv->id,
                        'stock_source_type' => WarehouseInventory::class,
                        'qty_deducted' => $deduct,
                    ]);
                    $remaining -= $deduct;
                }

                $med->recalculateStock();

                StockAuditService::log(
                    $med,
                    'Pengurangan Racikan',
                    (float) ($med->stok_total + $d['qty_taken_pcs']),
                    (float) $med->stok_total,
                    "Bahan racikan: {$sub->nama_racikan} (#{$sub->id})"
                );
            } else {
                $rm = RawMaterial::findOrFail($itemId);
                $detail = CompoundingDetail::create([
                    'compounding_id' => $sub->id,
                    'raw_material_id' => $rm->id,
                    'medicine_id' => null,
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

                // Pengurangan Stok Otomatis sesuai Batch FEFO RawMaterialStock dengan Kunci Pesimistik (Race-Free)
                $remaining = (float) $d['qty_taken_pcs'];
                /** @var \Illuminate\Database\Eloquent\Collection<int, RawMaterialStock> $batches */
                $batches = RawMaterialStock::where('raw_material_id', $rm->id)
                    ->where('stock_on_hand', '>', 0)
                    ->notExpired()
                    ->fefo()
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = (float) $batches->sum('stock_on_hand');
                if ($totalAvailable < $remaining) {
                    throw new \Exception("Stok tidak mencukupi untuk bahan baku '{$rm->nama_bahan}' saat penguncian batch. Dibutuhkan: {$remaining}, Tersedia: {$totalAvailable}.");
                }

                /** @var RawMaterialStock $batch */
                foreach ($batches as $batch) {
                    if ($remaining <= 0)
                        break;
                    $deduct = min($remaining, (float) $batch->stock_on_hand);
                    $batch->setMutationContext('out', $sub, "Bahan racikan: {$sub->nama_racikan}");
                    $batch->stock_on_hand = (string) round((float) $batch->stock_on_hand - $deduct, 2);
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
        }

        // 4. Buat Record Produksi & Label Etiket (PENDING PRICING)
        $hppPerUnit = $totalBaseCost / max($v['jumlah_unit'], 1);

        $cp = CompoundedProduct::create([
            'compounding_submission_id' => $sub->id,
            'nama_produk' => $sub->nama_racikan,
            'batch_no' => 'RAC-' . now()->format('Ymd') . '-' . str_pad($sub->id, 4, '0', STR_PAD_LEFT),
            'tanggal_racik' => $v['label']['tanggal_racik'],
            'bud_date' => Carbon::parse($v['label']['tanggal_racik'])->addDays($v['label']['bud_hari']),
            'qty_hasil' => $v['jumlah_unit'],
            'satuan' => $v['target_unit'],
            'base_cost' => $totalBaseCost,
            'hpp_per_unit' => $hppPerUnit,
            'harga_jual' => 0, // Akan di-set oleh Admin di menu Penetapan Harga
            'status' => 'draft',             // Menunggu approval admin
            'pricing_status' => 'pending_price', // Dilempar ke antrean pricing admin
            'created_by' => $user->id,
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
            /** @var PrescriptionItem|null $pItem */
            $pItem = PrescriptionItem::find($v['prescription_item_id']);
            if ($pItem) {
                $pItem->update([
                    'compounding_id' => $sub->id,
                    'compounded_product_id' => $cp->id,
                    'status' => 'selesai',
                ]);
            }
        }

        AuditTrail::log($user->id, 'CREATE_COMPOUNDING', "Submit Racikan: {$sub->nama_racikan}. Stok batch dikunci. Menunggu penetapan harga oleh Admin.", CompoundingSubmission::class, $sub->id);

        return redirect()->back()->with('success', 'Racikan Selesai! Data diteruskan ke Admin untuk Penetapan Harga (Biaya Kemasan & Margin).');
    }

    /**
     * Display details of a compounding submission.
     */
    public function showSubmission(CompoundingSubmission $submission)
    {
        abort_if($submission->user_id !== auth()->id() && auth()->user()->role !== 'admin', 403, 'Akses ditolak.');

        $submission->load(['details.medicine', 'label', 'formula', 'user', 'approver']);
        return Inertia::render('Control/Apoteker/SubmissionDetail', ['submission' => $submission]);
    }

    /**
     * Display Beyond Use Date (BUD) tracking monitor.
     */
    public function budTracker()
    {
        $activeCompounding = CompoundingSubmission::select(['id', 'nama_racikan', 'user_id', 'status', 'target_unit', 'created_at'])
            ->with([
                'label:id,compounding_id,tanggal_racik,bud_hari,tanggal_kadaluarsa_bud,aturan_pakai,nama_pasien',
                'user:id,name'
            ])
            ->whereHas('label', function ($q) {
                $q->whereNotNull('tanggal_kadaluarsa_bud');
            })
            ->whereIn('status', ['active', 'pending_final_verification'])
            ->latest()
            ->paginate(20);

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
        $suggestion = CompoundingDetail::suggestFEFO($itemId, $type);
        if ($suggestion) {
            if ($type === 'medicine') {
                $suggestion->loadMissing('medicine');
            } else {
                $suggestion->loadMissing('rawMaterial');
            }
        }
        return response()->json(['suggestion' => $suggestion]);
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
                        if ($detail->source_type === 'medicine' && $detail->medicine) {
                            $med = $detail->medicine;
                            $qtyBefore = (float) $med->stok_total;
                            $med->recalculateStock();
                            StockAuditService::log(
                                $med,
                                'Pembatalan Racikan',
                                $qtyBefore,
                                (float) $med->fresh()->stok_total,
                                "Pembatalan Racikan #{$submission->id} - Stok obat jadi dikembalikan ke Batch #{$source->id}"
                            );
                        } elseif ($detail->rawMaterial) {
                            $rm = $detail->rawMaterial;
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

                if ($detail->source_type === 'medicine' && $detail->medicine) {
                    $detail->medicine->recalculateStock();
                } elseif ($detail->rawMaterial) {
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

    /**
     * Print thermal label (etiket) for compounding submission.
     */
    public function printLabel($id)
    {
        $submission = CompoundingSubmission::with([
            'label',
            'prescription.patient',
            'details.medicine',
            'details.rawMaterial',
            'user',
            'formula',
        ])->findOrFail($id);

        $compoundedProduct = CompoundedProduct::where('compounding_submission_id', $submission->id)->first();

        $targetUnit = strtolower($submission->target_unit ?? '');
        $namaRacikan = strtolower($submission->nama_racikan ?? '');
        $kategori = strtolower($submission->formula?->kategori_sediaan ?? '');
        $isObatLuar = in_array($targetUnit, ['pot', 'tube']) 
            || preg_match('/(salep|krim|cream|gel|pasta|tetes|kumur|gargle|suppo|lotion|lotio|topikal|oles|luar)/i', $namaRacikan . ' ' . $kategori . ' ' . $targetUnit);

        return view('prints.etiket-thermal', [
            'submission' => $submission,
            'compoundedProduct' => $compoundedProduct,
            'isObatLuar' => $isObatLuar,
        ]);
    }
}
