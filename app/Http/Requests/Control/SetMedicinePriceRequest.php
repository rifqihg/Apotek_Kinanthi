<?php

namespace App\Http\Requests\Control;

use App\Models\Control\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SetMedicinePriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'apoteker']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'selling_price_large' => 'nullable|numeric|min:1',
            'selling_price_mid'   => 'nullable|numeric|min:1',
            'selling_price'       => 'nullable|numeric|min:1',
        ];
    }

    /**
     * Configure the validator instance with business rule safeguards.
     * Mencegah salah input manual yang menghasilkan margin tidak masuk akal (> 300%).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Medicine|null $medicine */
            $medicine = $this->route('medicine');
            if (!$medicine instanceof Medicine) {
                $medicine = Medicine::find($medicine);
            }
            if (!$medicine) {
                return;
            }

            // Ambil data batch faktur terakhir untuk mendapatkan referensi modal HPP riil
            $latestStock = $medicine->incomingStocks()->latest()->first();
            if (!$latestStock) {
                return; // Jika belum ada faktur masuk, lewati validasi terhadap HPP faktur
            }

            // 1. Hitung HPP Satuan Primer (Terkecil)
            $hppKecil = (float) $latestStock->calculateHppSatuan();
            if ($hppKecil <= 0) {
                return;
            }

            // 2. Hitung HPP Satuan Sekunder (Menengah)
            $smallToMid = (float) ($latestStock->small_to_mid_ratio ?: 1);
            $hppMenengah = $hppKecil * $smallToMid;

            // 3. Hitung HPP Satuan Tersier (Terbesar/Dus)
            if ($latestStock->mid_unit) {
                $midToBig = (float) ($latestStock->mid_to_big_ratio ?: 1);
                $hppBesar = $hppMenengah * $midToBig;
            } else {
                $conversionFactor = (float) ($latestStock->unit_conversion_factor ?: 1);
                $hppBesar = $hppKecil * $conversionFactor;
            }

            $maxAllowedMargin = 300.0; // Batas toleransi margin wajar (300%)

            // ─── VALIDASI SATUAN PRIMER (Kecil: Tablet / Kapsul / Strip Utama) ───
            if ($this->filled('selling_price')) {
                $sellPrice = (float) $this->input('selling_price');
                $marginPercent = (($sellPrice - $hppKecil) / $hppKecil) * 100;
                $satuanLabel = $medicine->satuan_stok ?: 'Primer';

                if ($marginPercent > $maxAllowedMargin) {
                    $validator->errors()->add(
                        'selling_price',
                        "Harga jual {$satuanLabel} (Rp " . number_format($sellPrice, 0, ',', '.') . 
                        ") menghasilkan margin " . number_format($marginPercent, 1) . "% (Modal HPP: Rp " . 
                        number_format($hppKecil, 0, ',', '.') . "). Melebihi batas toleransi wajar (300%). " . 
                        "Mohon periksa kembali apakah Anda salah input nominal atau salah kolom satuan."
                    );
                } elseif ($sellPrice < $hppKecil) {
                    $validator->errors()->add(
                        'selling_price',
                        "Harga jual {$satuanLabel} (Rp " . number_format($sellPrice, 0, ',', '.') . 
                        ") lebih rendah dari modal HPP (Rp " . number_format($hppKecil, 0, ',', '.') . 
                        "). Apotek tidak boleh menjual rugi."
                    );
                }
            }

            // ─── VALIDASI SATUAN SEKUNDER (Menengah: Strip / Box) ───
            if ($this->filled('selling_price_mid') && $latestStock->mid_unit) {
                $sellPriceMid = (float) $this->input('selling_price_mid');
                $marginMidPercent = (($sellPriceMid - $hppMenengah) / $hppMenengah) * 100;
                $satuanMidLabel = $latestStock->mid_unit;

                if ($marginMidPercent > $maxAllowedMargin) {
                    $validator->errors()->add(
                        'selling_price_mid',
                        "Harga jual {$satuanMidLabel} (Rp " . number_format($sellPriceMid, 0, ',', '.') . 
                        ") menghasilkan margin " . number_format($marginMidPercent, 1) . "% (Modal HPP: Rp " . 
                        number_format($hppMenengah, 0, ',', '.') . "). Melebihi batas wajar (300%). " . 
                        "Mohon periksa kembali input nominal Anda."
                    );
                } elseif ($sellPriceMid < $hppMenengah) {
                    $validator->errors()->add(
                        'selling_price_mid',
                        "Harga jual {$satuanMidLabel} tidak boleh lebih rendah dari modal HPP (Rp " . 
                        number_format($hppMenengah, 0, ',', '.') . ")."
                    );
                }
            }

            // ─── VALIDASI SATUAN TERSIER (Besar: Dus / Box Utama) ───
            if ($this->filled('selling_price_large') && $latestStock->satuan_input) {
                $sellPriceLarge = (float) $this->input('selling_price_large');
                $marginLargePercent = (($sellPriceLarge - $hppBesar) / $hppBesar) * 100;
                $satuanLargeLabel = $latestStock->satuan_input;

                if ($marginLargePercent > $maxAllowedMargin) {
                    $validator->errors()->add(
                        'selling_price_large',
                        "Harga jual {$satuanLargeLabel} (Rp " . number_format($sellPriceLarge, 0, ',', '.') . 
                        ") menghasilkan margin " . number_format($marginLargePercent, 1) . "% (Modal HPP: Rp " . 
                        number_format($hppBesar, 0, ',', '.') . "). Melebihi batas wajar (300%). " . 
                        "Mohon periksa kembali input nominal Anda."
                    );
                } elseif ($sellPriceLarge < $hppBesar) {
                    $validator->errors()->add(
                        'selling_price_large',
                        "Harga jual {$satuanLargeLabel} tidak boleh lebih rendah dari modal HPP (Rp " . 
                        number_format($hppBesar, 0, ',', '.') . ")."
                    );
                }
            }
        });
    }
}
