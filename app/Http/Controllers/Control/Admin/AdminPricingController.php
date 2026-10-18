<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\SetMedicinePriceRequest;
use App\Models\Control\AuditTrail;
use App\Models\Control\CompoundedProduct;
use App\Models\Control\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
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
        $compoundedProducts = CompoundedProduct::select(['id', 'compounding_submission_id', 'nama_produk', 'batch_no', 'qty_hasil', 'hpp_per_unit', 'pricing_status', 'harga_jual', 'satuan'])
            ->with(['submission' => fn($q) => $q->select(['id', 'target_quantity', 'total_hpp_racikan', 'total_hpp_bahan', 'biaya_tuslah', 'jasa_racik', 'biaya_kemasan', 'harga_jual_racikan'])])
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($product) {
                $qty = max($product->qty_hasil > 0 ? $product->qty_hasil : ($product->submission?->target_quantity ?: 1), 1);
                
                $hppBahan = 0;
                $tuslah = 0;
                $jasaRacik = 0;
                $biayaKemasan = 0;
                $hargaJual = (float)$product->harga_jual;
                $margin = null;

                if ($product->submission) {
                    $hppBahan = (float)$product->submission->total_hpp_bahan / $qty;
                    if ($hppBahan == 0 && $product->hpp_per_unit > 0) {
                        $hppBahan = (float)$product->hpp_per_unit;
                    }
                    $tuslah = (float)$product->submission->biaya_tuslah / $qty;
                    $jasaRacik = (float)$product->submission->jasa_racik / $qty;
                    $biayaKemasan = (float)$product->submission->biaya_kemasan / $qty;
                    
                    if ($hargaJual > 0 && ($hppBahan + $tuslah) > 0) {
                        $modal = $hppBahan + $tuslah;
                        $margin = round((($hargaJual - $modal) / $modal) * 100, 1);
                    }
                } else {
                    $hppBahan = (float)$product->hpp_per_unit;
                }

                $product->calculated_hpp = $hppBahan;
                $product->calculated_tuslah = $tuslah;
                $product->calculated_jasa = $jasaRacik;
                $product->calculated_kemasan = $biayaKemasan;
                $product->calculated_margin = $margin;
                unset($product->submission);
                
                return $product;
            });

        $medicines = Medicine::select([
                'id',
                'kode_obat',
                'nama_obat',
                'satuan_stok',
                'base_unit',
                'selling_price',
                'selling_price_mid',
                'selling_price_large',
            ])
            ->with(['latestIncomingStock' => function ($q) {
                $q->select([
                    'incoming_stocks.id',
                    'incoming_stocks.medicine_id',
                    'incoming_stocks.batch_no',
                    'incoming_stocks.satuan_input',
                    'incoming_stocks.mid_unit',
                    'incoming_stocks.mid_to_big_ratio',
                    'incoming_stocks.small_to_mid_ratio',
                    'incoming_stocks.unit_conversion_factor',
                    'incoming_stocks.qty_converted',
                    'incoming_stocks.harga_beli_satuan',
                    'incoming_stocks.diskon_persen',
                    'incoming_stocks.jumlah_harga',
                    'incoming_stocks.ppn_persen',
                    'incoming_stocks.qty_masuk',
                ]);
            }])
            ->latest('id')
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
                unset($med->latestIncomingStock);

                return $med;
            });

        return Inertia::render('Control/Admin/Pricing', [
            'compoundedProducts' => $compoundedProducts,
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
                $qty = max(1, (float)$product->qty_hasil);
                $totalHppBahan = (float)$product->submission->total_hpp_bahan;
                $jasaRacikTotal = $v['jasa_racik'] * $qty;
                $biayaKemasanTotal = $v['biaya_kemasan'] * $qty;
                $biayaTuslahTotal = $jasaRacikTotal + $biayaKemasanTotal;

                $product->submission->update([
                    'status' => 'active',
                    'biaya_tuslah' => $biayaTuslahTotal,
                    'jasa_racik' => $jasaRacikTotal,
                    'biaya_kemasan' => $biayaKemasanTotal,
                    'total_hpp_racikan' => $totalHppBahan + $biayaTuslahTotal,
                    'harga_jual_racikan' => $v['harga_jual_final'],
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'admin_note' => $v['admin_note'] ?? null,
                ]);
            }

            $product->loadMissing('submission');

            // Otomatis sinkronkan status resep induk jika seluruh item sudah selesai & berharga
            $prescription = null;
            if ($product->submission && $product->submission->prescription_id) {
                $prescription = Prescription::with('prescriptionItems')->find($product->submission->prescription_id);
            }
            if (!$prescription) {
                $pItem = PrescriptionItem::with('prescription.prescriptionItems')
                    ->where('compounded_product_id', $product->id)
                    ->first();
                $prescription = $pItem?->prescription;
            }

            if ($prescription && in_array($prescription->status, ['baru', 'diproses'])) {
                $allItems = $prescription->prescriptionItems;
                $hasUnfinished = $allItems->isEmpty() || $allItems->contains(fn($it) => $it->status !== 'selesai');

                if (!$hasUnfinished) {
                    $allPriced = true;
                    foreach ($allItems->where('item_type', 'racikan') as $rItem) {
                        if ($rItem->compounded_product_id === $product->id) {
                            continue;
                        }
                        $otherCp = $rItem->compoundedProduct ?: ($rItem->compounded_product_id ? CompoundedProduct::find($rItem->compounded_product_id) : null);
                        if (!$otherCp && $rItem->compounding_id) {
                            $otherCp = CompoundedProduct::where('compounding_submission_id', $rItem->compounding_id)->first();
                        }
                        if (!$otherCp || $otherCp->pricing_status !== 'priced' || (float) $otherCp->harga_jual <= 0) {
                            $allPriced = false;
                            break;
                        }
                    }

                    if ($allPriced) {
                        $prescription->update(['status' => 'siap_ambil']);
                        AuditTrail::log(
                            $request->user()->id,
                            'PRESCRIPTION_READY',
                            "Resep {$prescription->prescription_number} otomatis diperbarui menjadi siap_ambil setelah harga racikan divalidasi oleh Admin.",
                            Prescription::class,
                            $prescription->id
                        );
                    }
                }
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