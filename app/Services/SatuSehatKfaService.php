<?php

namespace App\Services;

use App\Models\Control\Medicine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SatuSehatKfaService
{
    /**
     * Get OAuth 2.0 Access Token with Safety Skew Buffer & Atomic Lock.
     */
    public function getAccessToken(): ?string
    {
        // 1. Check existing cached token
        $cachedToken = Cache::get('satusehat_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        // 2. Prevent Cache Stampede / Dogpiling with Atomic Lock
        return Cache::lock('satusehat_auth_lock', 10)->block(5, function () {
            // Double check inside lock
            $cachedToken = Cache::get('satusehat_access_token');
            if ($cachedToken) {
                return $cachedToken;
            }

            $authUrl = config('services.satusehat.auth_url', env('SATUSEHAT_AUTH_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1'));
            $clientId = config('services.satusehat.client_id', env('SATUSEHAT_CLIENT_ID'));
            $clientSecret = config('services.satusehat.client_secret', env('SATUSEHAT_CLIENT_SECRET'));

            if (empty($clientId) || empty($clientSecret)) {
                Log::warning('SATUSEHAT: Client ID atau Secret belum disetel di environment.');
                return null;
            }

            try {
                $endpoint = rtrim($authUrl, '/') . '/accesstoken?grant_type=client_credentials';
                $response = Http::asForm()->timeout(10)->post($endpoint, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

                if (!$response->successful()) {
                    Log::error('SATUSEHAT Auth Failed', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $expiresIn = (int) ($data['expires_in'] ?? 3600);

                if ($token) {
                    // Safety Skew Buffer: Cache TTL dipotong 300 detik (5 menit) sebelum token kedaluwarsa riil
                    $ttl = max(60, $expiresIn - 300);
                    Cache::put('satusehat_access_token', $token, $ttl);
                    return $token;
                }

                return null;
            } catch (\Throwable $e) {
                Log::error('SATUSEHAT Auth Exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Look up medication by NIE BPOM, KFA Code, or Barcode.
     * Priority 1: Local Database
     * Priority 2: SATUSEHAT KFA-v2 API
     */
    public function lookup(string $query, ?string $type = null): array
    {
        $clean = trim($query);
        if (empty($clean)) {
            return [
                'source'  => 'none',
                'found'   => false,
                'message' => 'Parameter pencarian KFA (NIE / Kode KFA) tidak boleh kosong.',
            ];
        }

        // ─── TAHAP 0: REGEX AUTO-DETECTION & EARLY GUARD ─────────────────────
        if (empty($type) || $type === 'auto') {
            if (preg_match('/^[a-z]{3}\d+/i', $clean)) {
                $type = 'nie';
            } elseif (ctype_digit($clean) && strlen($clean) === 8) {
                $type = 'kfa';
            } elseif (ctype_digit($clean) && in_array(strlen($clean), [12, 13, 14])) {
                $type = 'barcode';
            } else {
                // Fail Fast (0ms Lag): Jangan kirim HTTP request ke Kemenkes jika format jelas bukan kode resmi
                return [
                    'source'  => 'none',
                    'found'   => false,
                    'message' => 'Format tidak dikenali. Masukkan Nomor Izin Edar BPOM (contoh: DKL, GKL, DBL) atau 8 digit Kode KFA.',
                ];
            }
        }

        // ─── TAHAP 1: LOOKUP DATABASE LOKAL (0ms Lag - Lean Column Selection) ──
        $localMed = Medicine::select([
                'id',
                'nama_obat',
                'barcode',
                'kfa_code',
                'nie',
                'kategori',
                'satuan_stok',
                'satuan_besar',
                'satuan_menengah',
                'rasio_konversi',
                'threshold_min',
            ])
            ->with(['latestIncomingStock' => function ($q) {
                $q->select([
                    'incoming_stocks.id',
                    'incoming_stocks.medicine_id',
                    'incoming_stocks.satuan_input',
                    'incoming_stocks.mid_unit',
                    'incoming_stocks.mid_to_big_ratio',
                    'incoming_stocks.small_to_mid_ratio',
                    'incoming_stocks.unit_conversion_factor',
                    'incoming_stocks.harga_beli_satuan',
                    'incoming_stocks.diskon_persen',
                ]);
            }])
            ->where(function ($q) use ($clean, $type) {
                if ($type === 'nie') {
                    $q->where('nie', $clean)
                      ->orWhere('nie', strtoupper($clean));
                } elseif ($type === 'kfa') {
                    $q->where('kfa_code', $clean);
                } elseif ($type === 'barcode') {
                    $q->where('barcode', $clean);
                } else {
                    $q->where('nie', $clean)
                      ->orWhere('kfa_code', $clean)
                      ->orWhere('barcode', $clean)
                      ->orWhere('kode_obat', $clean);
                }
            })
            ->first();

        if ($localMed) {
            $latest = $localMed->latestIncomingStock;
            $sInput = $latest?->satuan_input ?? $localMed->satuan_besar ?? 'Box';
            $sMid = $latest?->mid_unit ?? $localMed->satuan_menengah ?? null;
            $sBase = $localMed->satuan_stok ?: 'Tablet';
            $rMidToBig = (int) ($latest?->mid_to_big_ratio ?? 1);
            $rSmallToMid = (int) ($latest?->small_to_mid_ratio ?? ($localMed->rasio_konversi ?: 1));
            $rConversion = (int) ($latest?->unit_conversion_factor ?? ($localMed->rasio_konversi ?: 1));

            $smallUnitSkipped = false;
            $noMultiUnit = false;

            if ($sMid && strtolower($sMid) !== strtolower($sBase)) {
                $smallUnitSkipped = false;
                $noMultiUnit = false;
            } elseif (strtolower($sInput) === strtolower($sBase) && $rConversion <= 1) {
                $noMultiUnit = true;
                $smallUnitSkipped = false;
            } else {
                $smallUnitSkipped = true;
                $noMultiUnit = false;
            }

            return [
                'source' => 'local',
                'found'  => true,
                'data'   => [
                    'medicine_id'                => $localMed->id,
                    'is_new_medicine'            => false,
                    'nama_obat'                  => $localMed->nama_obat,
                    'barcode'                    => $localMed->barcode ?: ($type === 'barcode' ? $clean : null),
                    'kfa_code'                   => $localMed->kfa_code ?: ($type === 'kfa' ? $clean : null),
                    'nie'                        => $localMed->nie ?: ($type === 'nie' ? strtoupper($clean) : null),
                    'kategori'                   => $localMed->kategori ?: $this->resolveCategoryFromKfa(['nie' => $localMed->nie], $localMed->nie),
                    'satuan_stok'                => $sBase,
                    'satuan_input'               => $sInput,
                    'mid_unit'                   => $sMid,
                    'mid_to_big_ratio'           => $rMidToBig,
                    'small_to_mid_ratio'         => $rSmallToMid,
                    'unit_conversion_factor'     => $rConversion,
                    'small_unit_skipped'         => $smallUnitSkipped,
                    'no_multi_unit'              => $noMultiUnit,
                    'harga_beli_satuan'          => (float) ($latest?->harga_beli_satuan ?? 0),
                    'diskon_persen'              => (float) ($latest?->diskon_persen ?? 0),
                    'min_stock_threshold'        => (int) ($localMed->threshold_min ?? 10),
                    'requires_unit_confirmation' => false,
                ],
            ];
        }

        // ─── TAHAP 2: LOOKUP SATUSEHAT KFA-v2 API ─────────────────────────────
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'source'  => 'none',
                'found'   => false,
                'message' => 'Gagal menghubungkan ke SATUSEHAT: Access Token tidak tersedia.',
            ];
        }

        // Cache 7 Hari: Mencegah HTTP 429 dan menghemat kuota SATUSEHAT
        $cacheKey = 'kfa_res_' . $type . '_' . strtolower($clean);
        $kfaResult = Cache::remember($cacheKey, now()->addDays(7), function () use ($token, $clean, $type) {
            $result = $this->queryKfaApi($token, $clean, $type);
            return $result ?? ['__not_found' => true];
        });

        if (!$kfaResult || !empty($kfaResult['__not_found'])) {
            $typeLabel = strtoupper($type);
            return [
                'source'  => 'none',
                'found'   => false,
                'message' => "Data obat dengan {$typeLabel} '{$clean}' tidak ditemukan di katalog lokal maupun KFA Kemenkes.",
            ];
        }

        // Map dan standarisasi respon KFA ke skema grid Apotek Kinanthi
        $mapped = $this->mapKfaResultToItem($kfaResult, $clean, $type);

        return [
            'source' => 'kfa',
            'found'  => true,
            'data'   => $mapped,
        ];
    }

    /**
     * Query KFA API secara presisi tanpa waterfall fallback ganda.
     */
    protected function queryKfaApi(string $token, string $code, string $type = 'nie'): ?array
    {
        $kfaUrl = config('services.satusehat.kfa_url', env('SATUSEHAT_KFA_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'));
        $kfaUrl = rtrim($kfaUrl, '/');

        // Identifier terarah presisi (No redundant secondary loop)
        $identifiersToTry = [$type];
        if ($type === 'barcode') {
            $identifiersToTry[] = 'gtin';
        }

        foreach (array_unique($identifiersToTry) as $identifier) {
            try {
                $endpoint = "{$kfaUrl}/products?identifier={$identifier}&code=" . urlencode($code);
                $res = Http::withToken($token)->timeout(3.5)->get($endpoint);

                if ($res->successful()) {
                    $json = $res->json();
                    if (!empty($json['result'])) {
                        return $json['result'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("KFA Query Failed for {$identifier}:{$code} - " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Map KFA Result and parse packaging hierarchy.
     */
    protected function mapKfaResultToItem(array $result, string $originalCode, string $type = 'nie'): array
    {
        $name = $result['nama_dagang'] ?? $result['name'] ?? 'Obat Baru KFA';
        $nie = $result['nie'] ?? ($type === 'nie' ? strtoupper($originalCode) : null);
        $kfaCode = $result['kfa_code'] ?? ($type === 'kfa' ? $originalCode : null);
        $barcode = $result['barcode'] ?? ($type === 'barcode' ? $originalCode : null);
        $bentukSediaan = $result['dosage_form']['name'] ?? $result['bentuk_sediaan'] ?? 'Tablet';
        $pabrik = $result['manufacturer'] ?? $result['manufacturer_name'] ?? $result['registrar'] ?? $result['registrar_name'] ?? null;
        $kategori = $this->resolveCategoryFromKfa($result, $nie);

        // Kemasan default
        $satuanInput = 'Box';
        $midUnit = null;
        $smallUnit = 'Tablet';
        $midToBig = 1;
        $smallToMid = 1;
        $conversion = 1;
        $smallUnitSkipped = true;
        $noMultiUnit = false;
        $requiresConfirmation = false;
        $packagingRaw = '';

        // Tentukan satuan kecil dari bentuk sediaan jika cocok
        if (!empty($bentukSediaan)) {
            if (stripos($bentukSediaan, 'kapsul') !== false) {
                $smallUnit = 'Kapsul';
            } elseif (stripos($bentukSediaan, 'tablet') !== false) {
                $smallUnit = 'Tablet';
            } elseif (stripos($bentukSediaan, 'sirup') !== false || stripos($bentukSediaan, 'suspensi') !== false || stripos($bentukSediaan, 'cair') !== false) {
                $smallUnit = 'Botol';
                $satuanInput = 'Botol';
                $noMultiUnit = true;
                $conversion = 1;
            } elseif (stripos($bentukSediaan, 'salep') !== false || stripos($bentukSediaan, 'krim') !== false) {
                $smallUnit = 'Tube';
                $satuanInput = 'Tube';
                $noMultiUnit = true;
                $conversion = 1;
            } elseif (stripos($bentukSediaan, 'injeksi') !== false || stripos($bentukSediaan, 'ampul') !== false) {
                $smallUnit = 'Ampul';
            } elseif (stripos($bentukSediaan, 'vial') !== false) {
                $smallUnit = 'Vial';
            }
        }

        // Parse Packaging Array from KFA if present
        $packagings = $result['packaging_ids'] ?? [];
        if (!empty($packagings) && is_array($packagings)) {
            $firstPack = $packagings[0];
            $packName = $firstPack['name'] ?? '';
            $packQty = (int) ($firstPack['qty'] ?? 0);
            $packUom = $firstPack['uom_id'] ?? '';
            $packagingRaw = $packName;

            if (!empty($packUom)) {
                $smallUnit = $packUom;
            }

            // Regex 1: "Dus, 10 strip @ 10 tablet" or "Box, 5 blister @ 6 kapsul"
            if (preg_match('/(dus|box|kotak)[,\s]+(\d+)\s*(\w+)\s*[@xX]\s*(\d+)\s*(\w+)/i', $packName, $m)) {
                $satuanInput = ucfirst(strtolower($m[1]));
                $midToBig = (int) $m[2];
                $midUnit = ucfirst(strtolower($m[3]));
                $smallToMid = (int) $m[4];
                $smallUnit = ucfirst(strtolower($m[5]));
                $conversion = $midToBig * $smallToMid;
                $smallUnitSkipped = false;
                $noMultiUnit = false;
            }
            // Regex 2: "Dus isi 60" or "Box 100 tablet"
            elseif ($packQty > 1) {
                $satuanInput = (stripos($packName, 'dus') !== false) ? 'Dus' : 'Box';
                $conversion = $packQty;
                $smallToMid = $packQty;
                $midToBig = 1;
                $midUnit = null;
                $smallUnitSkipped = true;
                $noMultiUnit = false;
            }
            // Regex 3: Single item e.g. "Botol 60 mL"
            elseif ($packQty === 1) {
                $satuanInput = !empty($packUom) ? $packUom : 'Botol';
                $smallUnit = $satuanInput;
                $conversion = 1;
                $noMultiUnit = true;
            } else {
                $requiresConfirmation = true;
            }
        } else {
            // Packaging data tidak tertera di KFA
            $requiresConfirmation = true;
        }

        // Active ingredients formatting
        $ingredients = [];
        if (!empty($result['active_ingredients']) && is_array($result['active_ingredients'])) {
            foreach ($result['active_ingredients'] as $ing) {
                $zat = $ing['zat_aktif'] ?? '';
                $dosis = $ing['kekuatan_zat_aktif'] ?? '';
                if ($zat) {
                    $ingredients[] = trim("{$zat} {$dosis}");
                }
            }
        }

        return [
            'medicine_id'                => null,
            'is_new_medicine'            => true,
            'nama_obat'                  => $name,
            'pabrik'                     => $pabrik,
            'barcode'                    => $barcode,
            'kfa_code'                   => $kfaCode,
            'nie'                        => $nie,
            'kategori'                   => $kategori,
            'satuan_stok'                => $smallUnit,
            'satuan_input'               => $satuanInput,
            'mid_unit'                   => $midUnit,
            'mid_to_big_ratio'           => $midToBig,
            'small_to_mid_ratio'         => $smallToMid,
            'unit_conversion_factor'     => $conversion,
            'small_unit_skipped'         => $smallUnitSkipped,
            'no_multi_unit'              => $noMultiUnit,
            'harga_beli_satuan'          => 0,
            'diskon_persen'              => 0,
            'min_stock_threshold'        => 10,
            'requires_unit_confirmation' => $requiresConfirmation,
            'packaging_raw'              => $packagingRaw,
            'active_ingredients'         => $ingredients,
        ];
    }

    /**
     * Resolve medication category/type from KFA & BPOM regulation.
     * Maps to proper types: Suplemen, Obat Bebas, Obat Bebas Terbatas, Obat Keras,
     * Obat Bahan Alam, Alat Kesehatan, etc. instead of dosage packaging units (Tablet/Capsule).
     */
    protected function resolveCategoryFromKfa(array $result, ?string $nie = null): string
    {
        $nieCode = strtoupper(trim($nie ?? ($result['nie'] ?? '')));

        // 1. Deteksi BPOM NIE Prefix (Standar Klasifikasi Resmi BPOM & Kamus Farmasi)
        if (!empty($nieCode)) {
            // Suplemen Kesehatan (SD = Suplemen Dalam Negeri, SI = Impor, SL = Lisensi)
            if (preg_match('/(?:POM\s*)?S[DIL]/i', $nieCode)) {
                return 'Suplemen';
            }
            // Obat Bahan Alam / Jamu / Herbal (TR = Tradisional Lokal, TI = Impor, TL = Lisensi)
            if (preg_match('/(?:POM\s*)?T[RIL]/i', $nieCode)) {
                return 'Obat Bahan Alam';
            }
            // Obat Herbal Terstandar (HT)
            if (preg_match('/(?:POM\s*)?HT/i', $nieCode)) {
                return 'Obat Herbal Terstandar';
            }
            // Fitofarmaka (FF)
            if (preg_match('/(?:POM\s*)?FF/i', $nieCode)) {
                return 'Fitofarmaka';
            }
            // Kosmetik (NA, NB, NC, ND, NE)
            if (preg_match('/(?:POM\s*)?N[A-E]/i', $nieCode)) {
                return 'Kosmetik';
            }
            // Alat Kesehatan (AKD, AKL)
            if (preg_match('/AK[DL]/i', $nieCode)) {
                return 'Alat Kesehatan';
            }
            // PKRT (PKD, PKL)
            if (preg_match('/PK[DL]/i', $nieCode)) {
                return 'PKRT';
            }
        }

        // 2. Cek atribut Tipe / Product Type eksplisit dari KFA
        if (!empty($result['tipe']) && is_string($result['tipe'])) {
            return ucfirst($result['tipe']);
        }
        if (!empty($result['product_type']) && is_string($result['product_type'])) {
            return ucfirst($result['product_type']);
        }

        // 3. Cek Golongan Obat dari BPOM / Kemenkes (controlled_drug)
        $controlled = $result['controlled_drug']['name'] ?? $result['controlled_drug'] ?? null;
        if (!empty($controlled) && is_string($controlled)) {
            return $controlled;
        }

        // 4. Cek Farmalkes Type (Obat, Vaksin, dll)
        $farmalkes = $result['farmalkes_type']['name'] ?? $result['farmalkes_type'] ?? null;
        if (!empty($farmalkes) && is_string($farmalkes) && strtolower($farmalkes) !== 'medicine') {
            return ucfirst($farmalkes);
        }

        return 'Obat Bebas';
    }
}
