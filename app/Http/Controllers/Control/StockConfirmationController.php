<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\IncomingStock;
use App\Models\Control\RawMaterial;
use App\Models\Control\StockConfirmation;
use App\Services\IncomingStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockConfirmationController extends Controller
{
    /**
     * Confirm stock from supplier (faktur).
     */
    public function confirmSupplier(Request $request, IncomingStock $incomingStock, IncomingStockService $service)
    {
        $v = $request->validate([
            'qty_input' => 'required|numeric|min:0',
            'unit_type' => 'required|string',
            'conversion_rate' => 'required|integer|min:1',
            'condition_note' => 'nullable|string|max:500',
            'evidence_image' => 'nullable|image|max:2048', // Max 2MB
        ]);

        return DB::transaction(function () use ($incomingStock, $v, $request, $service) {
            $imagePath = null;
            if ($request->hasFile('evidence_image')) {
                $imagePath = $request->file('evidence_image')->store('confirmations', 'public');
            }

            // Calculate base quantity
            $qtyFisik = $v['qty_input'] * $v['conversion_rate'];

            // Log the confirmation attempt
            StockConfirmation::create([
                'reference_id' => $incomingStock->id,
                'reference_type' => 'supplier',
                'item_type' => $request->item_type ?? 'obat',
                'user_id' => $request->user()->id,
                'unit_type' => $v['unit_type'],
                'conversion_rate' => $v['conversion_rate'],
                'qty_expected' => $incomingStock->qty_converted,
                'qty_received' => $qtyFisik,
                'condition_note' => $v['condition_note'],
                'evidence_image' => $imagePath,
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            // Delegate to service for inventory adjustment
            $service->physicalVerify(
                $incomingStock, 
                [
                    'batch_no' => $incomingStock->batch_no ?? 'PENDING',
                    'expired_date' => $incomingStock->expired_date ?? now()->addYear(),
                    'qty_fisik' => $qtyFisik,
                    'note' => $v['condition_note'],
                ],
                $request->user()->id
            );

            if ($qtyFisik == $incomingStock->qty_converted) {
                return redirect()->back()->with('success', 'Stok fisik sesuai. Menunggu finalisasi Admin.');
            }

            return redirect()->back()->with('success', 'Selisih stok terdeteksi. Faktur dikirim ke Admin untuk revisi.');
        });
    }

    /**
     * Confirm stock from compounding (racikan).
     */
    public function confirmCompounding(Request $request, CompoundingSubmission $submission)
    {
        $v = $request->validate([
            'qty_input' => 'required|numeric|min:0',
            'unit_type' => 'required|string',
            'conversion_rate' => 'required|integer|min:1',
            'condition_note' => 'nullable|string|max:500',
            'evidence_image' => 'nullable|image|max:2048',
        ]);

        return DB::transaction(function () use ($submission, $v, $request) {
            $imagePath = null;
            if ($request->hasFile('evidence_image')) {
                $imagePath = $request->file('evidence_image')->store('confirmations', 'public');
            }

            $qtyReceived = $v['qty_input'] * $v['conversion_rate'];
            $expectedYield = $submission->target_quantity;
            $loss = $expectedYield - $qtyReceived;
            
            // Find the production record created by Apoteker
            $product = \App\Models\Control\CompoundedProduct::where('compounding_submission_id', $submission->id)->first();

            if (!$product) {
                return redirect()->back()->with('error', 'Data produk racikan tidak ditemukan.');
            }

            // Update Physical Verification Data
            $product->update([
                'actual_yield' => $qtyReceived,
                'production_loss' => $loss,
                'status' => 'pending_final_verification',
            ]);

            $submission->update(['status' => 'pending_final_verification']);

            $confirmation = StockConfirmation::create([
                'reference_id' => $submission->id,
                'reference_type' => 'compounding',
                'item_type' => 'obat',
                'user_id' => $request->user()->id,
                'unit_type' => $v['unit_type'],
                'conversion_rate' => $v['conversion_rate'],
                'qty_expected' => $expectedYield, 
                'qty_received' => $qtyReceived,
                'condition_note' => $v['condition_note'],
                'evidence_image' => $imagePath,
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            AuditTrail::log($request->user()->id, 'CONFIRM_COMPOUNDING_STOCK',
                "Verifikasi Fisik Racikan: {$submission->nama_racikan} (Yield: {$qtyReceived} {$product->satuan}). Menunggu aktivasi harga oleh Admin.",
                StockConfirmation::class, $confirmation->id
            );

            return redirect()->back()->with('success', 'Kuantitas fisik racikan berhasil diverifikasi. Menunggu Gate 2 (Aktivasi Harga) oleh Admin.');
        });
    }

    /**
     * Confirm stock from raw material initialization (bahan baku baru).
     */
    /**
     * Phase 3 (Warehouse): Confirm physical stock for raw material catalog.
     * Admin inputs Batch, ED, and actual_quantity based on expected_quantity.
     */
    public function confirmRawMaterial(Request $request, RawMaterial $rawMaterial)
    {
        $v = $request->validate([
            'qty_input' => 'required|numeric|min:0',
            'batch_no' => 'required|string|max:100',
            'expired_date' => 'required|date|after:today',
            'unit_type' => 'required|string',
            'conversion_rate' => 'required|integer|min:1',
            'condition_note' => 'nullable|string|max:500',
            'evidence_image' => 'nullable|image|max:2048',
        ]);

        return DB::transaction(function () use ($rawMaterial, $v, $request) {
            $imagePath = null;
            if ($request->hasFile('evidence_image')) {
                $imagePath = $request->file('evidence_image')->store('confirmations', 'public');
            }

            $qtyReceived = $v['qty_input'] * $v['conversion_rate'];

            // Find the draft stock created by Apoteker in Stage 1
            $stock = $rawMaterial->stocks()
                ->where('status', 'pending_physical_check')
                ->first();

            if (!$stock) {
                // Fallback if somehow it doesn't exist, but per logic it should
                $stock = new \App\Models\Control\RawMaterialStock(['raw_material_id' => $rawMaterial->id]);
            }

            $oldBatch = $stock->batch_no;
            
            // Update the record with Warehouse measurements
            $stock->fill([
                'batch_no' => $v['batch_no'],
                'expired_date' => $v['expired_date'],
                'actual_quantity' => $qtyReceived,
                'stock_on_hand' => $qtyReceived, // Set for visibility in Gate 2
                'status' => 'pending_final_verification',
                'notes' => $v['condition_note'] ?? 'Verifikasi fisik oleh Admin.',
            ])->save();

            // 2. Log Confirmation for Audit
            $confirmation = StockConfirmation::create([
                'reference_id' => $stock->id,
                'reference_type' => 'raw_material_stock',
                'item_type' => 'bahan_baku',
                'user_id' => $request->user()->id,
                'unit_type' => $v['unit_type'],
                'conversion_rate' => $v['conversion_rate'],
                'qty_expected' => $stock->expected_quantity,
                'qty_received' => $qtyReceived,
                'condition_note' => $v['condition_note'],
                'evidence_image' => $imagePath,
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            // 3. Update RawMaterial status to Phase 4
            $rawMaterial->update([
                'status' => 'pending_final_verification',
                'stok_total' => $qtyReceived,
            ]);

            $logMsg = "Verifikasi fisik Admin: {$rawMaterial->nama_bahan} (Expected: {$stock->expected_quantity}, Actual: {$qtyReceived})";
            if ($oldBatch !== $v['batch_no']) {
                $logMsg .= " [Batch Koreksi: {$oldBatch} -> {$v['batch_no']}]";
            }

            AuditTrail::log($request->user()->id, 'CONFIRM_RAW_MATERIAL_PHYSICAL',
                $logMsg,
                RawMaterial::class, $rawMaterial->id
            );

            return redirect()->back()->with('success', 'Data fisik berhasil diverifikasi. Menunggu Gate 2 (Aktivasi Harga) oleh Admin.');
        });
    }
}
