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

        $fetcher = function () {
            // Double check inside lock or fallback
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
        };

        // 2. Prevent Cache Stampede with Atomic Lock if supported by driver, fallback gracefully if not
        try {
            return Cache::lock('satusehat_auth_lock', 10)->block(5, $fetcher);
        } catch (\BadMethodCallException) {
            return $fetcher();
        }
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
            return [
                'source' => 'local',
                'found'  => true,
                'data'   => $this->formatLocalMedicine($localMed, $type, $clean),
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
        $name = !empty($result['name']) ? $result['name'] : ($result['nama_dagang'] ?? 'Obat Baru KFA');
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
            } elseif (stripos($bentukSediaan, 'sirup') !== false || stripos($bentukSediaan, 'suspensi') !== false || stripos($bentukSediaan, 'cair') !== false || stripos($bentukSediaan, 'drop') !== false || stripos($bentukSediaan, 'tetes') !== false) {
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

        // Tentukan dari UOM jika tersedia dari KFA
        $uomName = is_array($result['uom'] ?? null) ? ($result['uom']['name'] ?? null) : ($result['uom'] ?? null);
        if (!empty($uomName) && is_string($uomName)) {
            $smallUnit = ucfirst(strtolower($uomName));
            if (in_array(strtolower($uomName), ['botol', 'tube', 'ampul', 'vial', 'sachet', 'pen'])) {
                $satuanInput = $smallUnit;
                $noMultiUnit = true;
            }
        }

        // Parse Packaging Array from KFA if present
        $packagings = $result['packaging_ids'] ?? [];
        if (!empty($packagings) && is_array($packagings)) {
            // Smart Packaging Selection: Prioritaskan kemasan faktur apotek (Dus/Box/Multi-level bertingkat)
            $selectedPack = null;

            // Prioritas 1: Kemasan bertingkat lengkap (contoh: "Dus, 10 strip @ 10 tablet")
            foreach ($packagings as $p) {
                $pName = $p['name'] ?? '';
                if (preg_match('/(dus|box|kotak)[,\s]+(\d+)\s*(\w+)\s*[@xX]\s*(\d+)\s*(\w+)/i', $pName)) {
                    $selectedPack = $p;
                    break;
                }
            }

            // Prioritas 2: Kemasan Dus/Box/Kotak dengan kuantitas > 1 (contoh: "Dus isi 100", "Box 50 tablet")
            if (!$selectedPack) {
                foreach ($packagings as $p) {
                    $pName = $p['name'] ?? '';
                    $pQty = (int) ($p['qty'] ?? 0);
                    if ($pQty > 1 && preg_match('/(dus|box|kotak)/i', $pName)) {
                        $selectedPack = $p;
                        break;
                    }
                }
            }

            // Prioritas 3: Kemasan dengan kuantitas (qty) terbesar
            if (!$selectedPack) {
                $maxQty = -1;
                foreach ($packagings as $p) {
                    $pQty = (int) ($p['qty'] ?? 0);
                    if ($pQty > $maxQty) {
                        $maxQty = $pQty;
                        $selectedPack = $p;
                    }
                }
            }

            $firstPack = $selectedPack ?: $packagings[0];
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
            // Regex 2: "Dus isi 100", "Dus isi 60", "Box 100 tablet"
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
            // Cek jika nama produk mengandung pola kemasan faktur (contoh: "Dus, 10 strip @ 10 tablet" atau "Dus isi 100")
            if (preg_match('/(dus|box|kotak)[,\s]+(\d+)\s*(\w+)\s*[@xX]\s*(\d+)\s*(\w+)/i', $name, $m)) {
                $satuanInput = ucfirst(strtolower($m[1]));
                $midToBig = (int) $m[2];
                $midUnit = ucfirst(strtolower($m[3]));
                $smallToMid = (int) $m[4];
                $smallUnit = ucfirst(strtolower($m[5]));
                $conversion = $midToBig * $smallToMid;
                $smallUnitSkipped = false;
                $noMultiUnit = false;
                $requiresConfirmation = false;
                $packagingRaw = $m[0];
            } elseif (preg_match('/(dus|box|kotak)[,\s]+isi\s*(\d+)/i', $name, $m) || preg_match('/(dus|box|kotak)[,\s]+(\d+)\s*(\w+)/i', $name, $m)) {
                $satuanInput = ucfirst(strtolower($m[1]));
                $conversion = (int) $m[2];
                $smallToMid = $conversion;
                $midToBig = 1;
                $smallUnitSkipped = true;
                $noMultiUnit = false;
                $requiresConfirmation = false;
                $packagingRaw = $m[0];
            } else {
                $requiresConfirmation = true;
            }
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

    /**
     * Format local medicine model to uniform frontend payload.
     */
    public function formatLocalMedicine(Medicine $localMed, ?string $type = null, ?string $clean = null): array
    {
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
        ];
    }

    /**
     * Search medications by Product Name (Keyword) with pagination.
     * Priority 1: Local Database (Page 1)
     * Priority 2: SATUSEHAT KFA-v2 API
     * Returns a curated list of matching items with total count and page metadata.
     */
    public function searchByName(string $keyword, int $limit = 10, int $page = 1): array
    {
        $clean = trim($keyword);
        if (strlen($clean) < 2) {
            return [
                'mode'         => 'list',
                'found'        => false,
                'items'        => [],
                'total'        => 0,
                'current_page' => 1,
                'per_page'     => $limit,
                'total_pages'  => 1,
                'message'      => 'Ketik minimal 2 karakter untuk mencari nama obat.',
            ];
        }

        $items = [];
        $existingCodes = [];
        $totalCount = 0;

        // 1. CARI DATABASE LOKAL (Hanya ditampilkan pada halaman 1 agar tidak duplikasi lintas halaman)
        if ($page === 1) {
            $localMeds = Medicine::select([
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
                ->where(function ($q) use ($clean) {
                    $q->where('nama_obat', 'like', "%{$clean}%")
                      ->orWhere('nie', 'like', "%{$clean}%");
                })
                ->limit($limit)
                ->get();

            foreach ($localMeds as $med) {
                $nieClean = strtoupper(trim((string)$med->nie));
                $kfaClean = trim((string)$med->kfa_code);
                if ($nieClean) $existingCodes[$nieClean] = true;
                if ($kfaClean) $existingCodes[$kfaClean] = true;

                $items[] = [
                    'source'         => 'local',
                    'medicine_id'    => $med->id,
                    'nama_obat'      => $med->nama_obat,
                    'nie'            => $med->nie ?: '-',
                    'kfa_code'       => $med->kfa_code ?: '-',
                    'pabrik'         => 'Apotek Kinanthi (Stok Lokal)',
                    'bentuk_sediaan' => $med->satuan_stok ?: 'Tablet',
                    'data'           => $this->formatLocalMedicine($med, 'name', $clean),
                ];
            }
            $totalCount += count($items);
        }

        // 2. CARI SATUSEHAT KFA-v2 (Cached 24 Jam per Page)
        $token = $this->getAccessToken();
        if ($token) {
            $cacheKey = 'kfa_search_name_' . md5(strtolower($clean)) . "_p{$page}_s{$limit}";
            $kfaPayload = Cache::remember($cacheKey, now()->addHours(24), function () use ($token, $clean, $limit, $page) {
                return $this->queryKfaByName($token, $clean, $limit, $page);
            });

            if (!empty($kfaPayload) && is_array($kfaPayload)) {
                $kfaRawList = $kfaPayload['items'] ?? [];
                $kfaTotal = (int) ($kfaPayload['total'] ?? count($kfaRawList));
                $totalCount += $kfaTotal;

                foreach ($kfaRawList as $kItem) {
                    $nie = strtoupper(trim((string)($kItem['nie'] ?? '')));
                    $kfaCode = trim((string)($kItem['kfa_code'] ?? ''));

                    // Cegah duplikasi jika obat ini sudah ada di hasil lokal
                    if (($nie && isset($existingCodes[$nie])) || ($kfaCode && isset($existingCodes[$kfaCode]))) {
                        continue;
                    }

                    $name = !empty($kItem['name']) ? $kItem['name'] : ($kItem['nama_dagang'] ?? 'Obat KFA');
                    $pabrik = $kItem['manufacturer'] ?? $kItem['manufacturer_name'] ?? $kItem['registrar'] ?? $kItem['registrar_name'] ?? '-';
                    $bentukSediaan = $kItem['dosage_form']['name'] ?? $kItem['bentuk_sediaan'] ?? 'Tablet';

                    $items[] = [
                        'source'         => 'kfa',
                        'medicine_id'    => null,
                        'nama_obat'      => $name,
                        'nie'            => $nie ?: '-',
                        'kfa_code'       => $kfaCode ?: '-',
                        'pabrik'         => $pabrik,
                        'bentuk_sediaan' => $bentukSediaan,
                        'data'           => $this->mapKfaResultToItem($kItem, $kfaCode ?: ($nie ?: $clean), $kfaCode ? 'kfa' : 'nie'),
                    ];
                }
            }
        }

        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / max(1, $limit)) : 1;

        return [
            'mode'         => 'list',
            'found'        => count($items) > 0,
            'total'        => $totalCount,
            'current_page' => $page,
            'per_page'     => $limit,
            'total_pages'  => $totalPages,
            'items'        => $items,
            'message'      => count($items) === 0 ? "Tidak ditemukan obat dengan nama '{$clean}' di katalog lokal maupun KFA Kemenkes." : null,
        ];
    }

    /**
     * Query KFA API by Product Name Keyword with pagination.
     * Uses official SATUSEHAT KFA-v2 catalog search endpoint with smart brand extraction & extended timeout.
     */
    protected function queryKfaByName(string $token, string $keyword, int $limit = 10, int $page = 1): ?array
    {
        $kfaUrl = config('services.satusehat.kfa_url', env('SATUSEHAT_KFA_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'));
        $kfaUrl = rtrim($kfaUrl, '/');

        // Optimasi Cerdas: Jika input mengandung merek di dalam tanda kurung seperti "Zat A + Zat B (PRENAMIA)"
        $candidates = [$keyword];
        if (preg_match('/\(([^)]+)\)$/', trim($keyword), $m)) {
            $brand = trim($m[1]);
            if (strlen($brand) >= 2) {
                array_unshift($candidates, $brand); // Coba merek spesifik dahulu (0.7 detik)
            }
        }

        foreach (array_unique($candidates) as $query) {
            try {
                $endpoint = "{$kfaUrl}/products/all?page={$page}&size={$limit}&product_type=farmasi&keyword=" . urlencode($query);
                $res = Http::withToken($token)->timeout(8.5)->get($endpoint);

                if ($res->successful()) {
                    $json = $res->json();
                    $data = $json['items']['data'] ?? $json['result']['items']['data'] ?? $json['result']['data'] ?? null;
                    if (!empty($data) && is_array($data)) {
                        return [
                            'items' => $data,
                            'total' => (int) ($json['total'] ?? count($data)),
                            'page'  => (int) ($json['page'] ?? $page),
                            'size'  => (int) ($json['size'] ?? $limit),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("KFA Name Query Failed for '{$query}': " . $e->getMessage());
            }
        }

        return null;
    }
}

