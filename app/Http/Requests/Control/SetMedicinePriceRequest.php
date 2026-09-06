<?php

namespace App\Http\Requests\Control;

use App\Models\Control\IncomingStock;
use App\Models\Control\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SetMedicinePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user() && in_array($this->user()->role, ['admin', 'apoteker']));
    }

    public function rules(): array
    {
        return [
            'selling_price_large' => 'nullable|numeric|min:1',
            'selling_price_mid'   => 'nullable|numeric|min:1',
            'selling_price'       => 'nullable|numeric|min:1',
        ];
    }

    /**
     * Configure validator with business rule safeguards to prevent margins > 300% or selling at loss.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $medicine = $this->route('medicine');
            $medicine = $medicine instanceof Medicine ? $medicine : Medicine::find($medicine);
            if (!$medicine) {
                return;
            }

            $latestStock = $medicine->incomingStocks()->latest()->first();
            if (!$latestStock instanceof IncomingStock) {
                return;
            }

            $hppKecil = (float) $latestStock->calculateHppSatuan();
            if ($hppKecil <= 0) {
                return;
            }

            $hppMenengah = $hppKecil * (float) ($latestStock->small_to_mid_ratio ?: 1);
            $hppBesar = $latestStock->mid_unit
                ? $hppMenengah * (float) ($latestStock->mid_to_big_ratio ?: 1)
                : $hppKecil * (float) ($latestStock->unit_conversion_factor ?: 1);

            $tiers = [
                ['field' => 'selling_price',       'hpp' => $hppKecil,    'label' => $medicine->satuan_stok ?: 'Primer', 'active' => true],
                ['field' => 'selling_price_mid',   'hpp' => $hppMenengah, 'label' => $latestStock->mid_unit,             'active' => (bool) $latestStock->mid_unit],
                ['field' => 'selling_price_large', 'hpp' => $hppBesar,    'label' => $latestStock->satuan_input,         'active' => (bool) $latestStock->satuan_input],
            ];

            foreach ($tiers as $tier) {
                if (!$tier['active'] || !$this->filled($tier['field'])) {
                    continue;
                }

                $sellPrice = (float) $this->input($tier['field']);
                $margin = (($sellPrice - $tier['hpp']) / $tier['hpp']) * 100;
                $label = $tier['label'];
                $formattedPrice = 'Rp ' . number_format($sellPrice, 0, ',', '.');
                $formattedHpp = 'Rp ' . number_format($tier['hpp'], 0, ',', '.');

                if ($margin > 300) {
                    $validator->errors()->add(
                        $tier['field'],
                        "Harga jual {$label} ({$formattedPrice}) menghasilkan margin " . number_format($margin, 1) . "% (Modal HPP: {$formattedHpp}). Melebihi batas toleransi wajar (300%). Mohon periksa kembali input nominal Anda."
                    );
                } elseif ($sellPrice < $tier['hpp']) {
                    $validator->errors()->add(
                        $tier['field'],
                        "Harga jual {$label} ({$formattedPrice}) lebih rendah dari modal HPP ({$formattedHpp}). Apotek tidak boleh menjual rugi."
                    );
                }
            }
        });
    }
}
