<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\SetMedicinePriceRequest;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminPricingController extends Controller
{
    /**
     * Display pricing management interface for compounded products and catalog medicines.
     */
    public function pricingIndex()
    {
        $pendingProducts = CompoundedProduct::select(['id', 'compounding_submission_id', 'nama_produk', 'batch_no', 'qty_hasil', 'hpp_per_unit', 'pricing_status', 'created_at'])
            ->with(['submission' => fn($q) => $q->select(['id', 'target_quantity', 'total_hpp_racikan'])])
            ->where('pricing_status', 'pending_price')
            ->latest()
            ->get()
            ->map(function ($product) {
                $hpp = $product->hpp_per_unit;
                if ($hpp <= 0 && $product->submission) {
                    $qty = $product->qty_hasil > 0 ? $product->qty_hasil : ($product->submission->target_quantity > 0 ? $product->submission->target_quantity : 1);
                    $hpp = $product->submission->total_hpp_racikan / $qty;
                }
                $product->calculated_hpp = $hpp;
                return $product;
            });

        $medicines = Medicine::select([
                'id',
                'category_id',
                'kode_obat',
                'nama_obat',
                'satuan_stok',
                'base_unit',
                'selling_price',
                'selling_price_mid',
                'selling_price_large',
                'threshold_min',
                'rasio_konversi',
                'conversion_factor',
                'created_at',
            ])
            ->with('latestIncomingStock')
            ->latest()
            ->get()
            ->map(function ($med) {
                $latestStock = $med->latestIncomingStock;
                $hppKecil = $latestStock ? (float) $latestStock->calculateHppSatuan() : 0;
                $hppMenengah = 0;
                $hppBesar = 0;

                if ($latestStock) {
                    // Strictly follow latest faktur
                    $med->satuan_besar = $latestStock->satuan_input;
                    $med->satuan_menengah = $latestStock->mid_unit;
                    $med->mid_to_big_ratio = $latestStock->mid_to_big_ratio;
                    $med->small_to_mid_ratio = $latestStock->small_to_mid_ratio;
                    $med->latest_batch_no = $latestStock->batch_no;

                    $smallToMid = (float) ($latestStock->small_to_mid_ratio ?: 1);
                    $hppMenengah = $hppKecil * $smallToMid;

                    if ($latestStock->mid_unit) {
                        $midToBig = (float) ($latestStock->mid_to_big_ratio ?: 1);
                        $hppBesar = $hppMenengah * $midToBig;
                    } else {
                        $conversionFactor = (float) ($latestStock->unit_conversion_factor ?: 1);
                        $hppBesar = $hppKecil * $conversionFactor;
                    }

                    // Fix for 2-layer disguise (where mid_unit is identical to base_unit with ratio 1)
                    if (strtolower($med->satuan_menengah) === strtolower($med->satuan_stok) && $med->small_to_mid_ratio == 1) {
                        $med->satuan_menengah = null;
                        $med->small_to_mid_ratio = $med->mid_to_big_ratio; // Conversion from Besar directly to Kecil(Base)
                        $med->mid_to_big_ratio = null;
                    }
                }
                $med->base_unit = $med->satuan_stok;
                $med->hpp_kecil = $hppKecil;
                $med->hpp_menengah = $hppMenengah;
                $med->hpp_besar = $hppBesar;

                return $med;
            });

        return Inertia::render('Control/Admin/Pricing', [
            'pendingProducts' => $pendingProducts,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Set selling price, packaging fee, and compounding service fee for compounded products.
     */
    public function setPriceCompounded(Request $request, CompoundedProduct $product)
    {
        $v = $request->validate([
            'biaya_kemasan' => 'required|numeric|min:0',
            'jasa_racik' => 'required|numeric|min:0',
            'margin_percentage' => 'required|numeric|min:0',
            'total_modal_buat' => 'required|numeric|min:0',
            'harga_jual_final' => 'required|numeric|min:1',
            'admin_note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($product, $v, $request) {
            $product->update([
                'harga_jual' => $v['harga_jual_final'],
                'pricing_status' => 'priced',
                'status' => 'active',
            ]);

            if ($product->submission) {
                $product->submission->update([
                    'status' => 'active',
                    'biaya_tuslah' => $v['jasa_racik'] + $v['biaya_kemasan'],
                    'total_hpp_bahan' => max(0, $v['total_modal_buat'] - ($v['jasa_racik'] + $v['biaya_kemasan'])),
                    'total_hpp_racikan' => $v['total_modal_buat'],
                    'harga_jual_racikan' => $v['harga_jual_final'],
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'admin_note' => $v['admin_note'] ?? null,
                ]);
            }

            AuditTrail::log(
                $request->user()->id,
                'PRICE_SET',
                "Penetapan harga racikan: {$product->nama_produk} (Modal: Rp " . number_format($v['total_modal_buat']) . " -> Jual: Rp " . number_format($v['harga_jual_final']) . ")",
                CompoundedProduct::class,
                $product->id
            );

            return redirect()->back()->with('success', 'Harga jual produk racikan berhasil ditetapkan dan siap dijual.');
        });
    }

    /**
     * Set selling prices across packaging tiers for catalog medicine.
     */
    public function setPriceMedicine(SetMedicinePriceRequest $request, Medicine $medicine)
    {
        $v = $request->validated();

        return DB::transaction(function () use ($medicine, $v, $request) {
            $medicine->update([
                'selling_price_large' => $v['selling_price_large'] ?? null,
                'selling_price_mid' => $v['selling_price_mid'] ?? null,
                'selling_price' => $v['selling_price'] ?? null,
            ]);

            AuditTrail::log($request->user()->id, 'PRICE_SET', "Penetapan harga obat jadi: {$medicine->nama_obat}", Medicine::class, $medicine->id);

            return redirect()->back()->with('success', 'Harga jual obat jadi berhasil ditetapkan.');
        });
    }
}