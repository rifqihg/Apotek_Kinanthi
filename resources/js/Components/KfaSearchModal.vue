<script setup>
import { ref, watch, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

const searchType = ref('name'); // 'name' | 'nie' | 'kfa'
const query = ref('');
const isLoading = ref(false);
const errorMessage = ref('');

// Single-result state (untuk pencarian exact NIE / Kode KFA)
const resultData = ref(null);
const resultSource = ref('');

// Multi-result state (untuk pencarian Nama Produk)
const listResults = ref([]);
const hasSearched = ref(false);
const isSelectingCode = ref(null);
const currentPage = ref(1);
const totalPages = ref(1);
const totalCount = ref(0);

// Client-side cache untuk navigasi instan tanpa roundtrip berulang (0ms delay)
const pageCache = ref({});
// Client-side cache untuk detail kemasan lengkap obat (0ms instant injection)
const detailCache = ref({});

const prefetchDetail = async (code, type) => {
    const key = `${type}_${code}`;
    if (detailCache.value[key]) return;

    try {
        const response = await axios.get(route('control.admin.incoming-stocks.satusehat-lookup'), {
            params: { q: code, type: type },
        });
        if (response.data?.found && response.data?.data) {
            detailCache.value[key] = response.data.data;
            const target = listResults.value.find(i => (type === 'kfa' ? i.kfa_code === code : i.nie === code));
            if (target) {
                target.data = response.data.data;
            }
        }
    } catch {
        // silent
    }
};

const prefetchTopDetails = (items) => {
    if (!items || !items.length) return;
    items.forEach((item) => {
        if (item.source === 'kfa') {
            const queryCode = (item.kfa_code && item.kfa_code !== '-') ? item.kfa_code : ((item.nie && item.nie !== '-') ? item.nie : null);
            const queryType = (item.kfa_code && item.kfa_code !== '-') ? 'kfa' : 'nie';
            if (queryCode) {
                prefetchDetail(queryCode, queryType);
            }
        }
    });
};

// Silent background prefetching: Mengisi cache server & browser untuk halaman berikutnya di latar belakang
const prefetchPage = async (keyword, targetPage) => {
    const key = `${keyword.toLowerCase()}_${targetPage}`;
    if (pageCache.value[key]) return;

    try {
        const response = await axios.get(route('control.admin.incoming-stocks.satusehat-lookup'), {
            params: {
                q: keyword,
                type: 'name',
                page: targetPage,
                limit: 10,
            },
        });
        if (response.data?.found && response.data?.mode === 'list') {
            pageCache.value[key] = response.data;
            prefetchTopDetails(response.data.items);
        }
    } catch {
        // Prefetch kegagalan diabaikan secara silent, tidak mengganggu pengguna
    }
};

const inputRef = ref(null);

watch(() => props.modelValue, async (isOpen) => {
    if (isOpen) {
        query.value = '';
        errorMessage.value = '';
        resultData.value = null;
        resultSource.value = '';
        listResults.value = [];
        hasSearched.value = false;
        isSelectingCode.value = null;
        currentPage.value = 1;
        totalPages.value = 1;
        totalCount.value = 0;
        pageCache.value = {};
        await nextTick();
        if (inputRef.value) {
            inputRef.value.focus();
            if (inputRef.value.select) inputRef.value.select();
        }
    }
});

const handleTypeChange = (type) => {
    searchType.value = type;
    errorMessage.value = '';
    resultData.value = null;
    resultSource.value = '';
    listResults.value = [];
    hasSearched.value = false;
    currentPage.value = 1;
    totalPages.value = 1;
    totalCount.value = 0;
    pageCache.value = {};
    nextTick(() => {
        inputRef.value?.focus();
    });
};

const handleSearch = async (page = 1) => {
    const clean = query.value.trim();
    if (!clean) {
        errorMessage.value = searchType.value === 'name' 
            ? 'Silakan masukkan nama produk obat terlebih dahulu.'
            : (searchType.value === 'nie' ? 'Silakan masukkan Nomor NIE BPOM terlebih dahulu.' : 'Silakan masukkan 8 digit Kode KFA.');
        return;
    }

    // Client-side Validation (Fail-Fast 0ms Lag)
    if (searchType.value === 'nie') {
        const isNie = /^[a-z]{3}\d+/i.test(clean);
        if (!isNie) {
            errorMessage.value = 'Format NIE tidak sesuai. Nomor Izin Edar BPOM biasanya diawali 3 huruf diikuti angka (contoh: DKL1740809209A1, GKL, DBL).';
            return;
        }
    } else if (searchType.value === 'kfa') {
        const isKfa = /^\d{8}$/.test(clean);
        if (!isKfa) {
            errorMessage.value = 'Format Kode KFA tidak sesuai. Kode KFA resmi Kemenkes terdiri dari tepat 8 digit angka (contoh: 93000455).';
            return;
        }
    } else if (searchType.value === 'name') {
        if (clean.length < 2) {
            errorMessage.value = 'Ketik minimal 2 karakter untuk menelusuri nama obat.';
            return;
        }
    }

    // 0ms Cache Hit: Jika halaman sudah ada di cache browser, langsung tampilkan seketika!
    const cacheKey = `${clean.toLowerCase()}_${page}`;
    if (searchType.value === 'name' && pageCache.value[cacheKey]) {
        const cached = pageCache.value[cacheKey];
        listResults.value = cached.items || [];
        totalCount.value = cached.total || 0;
        totalPages.value = cached.total_pages || 1;
        currentPage.value = page;
        hasSearched.value = true;
        errorMessage.value = '';
        isLoading.value = false;

        // Otomatis siapkan halaman berikutnya di background jika belum ada
        if (totalPages.value > page && !pageCache.value[`${clean.toLowerCase()}_${page + 1}`]) {
            prefetchPage(clean, page + 1);
        }
        prefetchTopDetails(cached.items);
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';
    resultData.value = null;
    resultSource.value = '';
    hasSearched.value = true;
    currentPage.value = page;

    try {
        const response = await axios.get(route('control.admin.incoming-stocks.satusehat-lookup'), {
            params: { 
                q: clean,
                type: searchType.value,
                page: page,
                limit: 10,
            },
        });

        const res = response.data;

        if (res.mode === 'list') {
            // Mode Pencarian Daftar (Nama Produk)
            if (res.found && res.items && res.items.length) {
                listResults.value = res.items;
                totalCount.value = res.total || res.items.length;
                totalPages.value = res.total_pages || 1;
                currentPage.value = res.current_page || page;

                // Simpan ke client-side cache
                pageCache.value[cacheKey] = res;

                // Silent Background Prefetching halaman berikutnya & detail kemasan
                if (totalPages.value > currentPage.value && !pageCache.value[`${clean.toLowerCase()}_${currentPage.value + 1}`]) {
                    prefetchPage(clean, currentPage.value + 1);
                }
                prefetchTopDetails(res.items);
            } else {
                listResults.value = [];
                totalCount.value = 0;
                totalPages.value = 1;
                errorMessage.value = res.message || `Tidak ditemukan obat dengan nama '${clean}' di katalog lokal maupun KFA Kemenkes.`;
            }
        } else {
            // Mode Pencarian Tunggal (NIE / Kode KFA)
            if (res.found && res.data) {
                resultData.value = res.data;
                resultSource.value = res.source; // 'kfa' | 'local'
            } else {
                errorMessage.value = res.message || `Data obat '${clean}' tidak ditemukan di SATUSEHAT KFA maupun katalog lokal.`;
            }
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal menghubungi server SATUSEHAT KFA. Periksa koneksi internet Anda.';
    } finally {
        isLoading.value = false;
    }
};

// Pilih obat dari daftar pencarian nama produk (0ms Instant Injection dengan Auto-Enrichment)
const handleSelectListItem = async (item) => {
    if (!item?.data) return;

    const queryCode = (item.kfa_code && item.kfa_code !== '-') ? item.kfa_code : ((item.nie && item.nie !== '-') ? item.nie : null);
    const queryType = (item.kfa_code && item.kfa_code !== '-') ? 'kfa' : 'nie';
    const detailKey = queryCode ? `${queryType}_${queryCode}` : null;

    // 1. Cek apakah detail kemasan lengkap sudah siap di memori cache (0ms INSTAN!)
    if (detailKey && detailCache.value[detailKey]) {
        emit('select', {
            ...detailCache.value[detailKey],
            _source: 'kfa',
        });
        emit('update:modelValue', false);
        return;
    }

    // 2. Jika obat lokal atau data kemasannya sudah bertingkat (unit_conversion_factor > 1), suntikkan seketika (0ms)!
    if (item.source === 'local' || (item.data?.unit_conversion_factor && item.data.unit_conversion_factor > 1)) {
        emit('select', {
            ...item.data,
            _source: item.source,
        });
        emit('update:modelValue', false);
        return;
    }

    // 3. Fallback jika user mengklik sangat cepat (< 100ms) sebelum background prefetch selesai
    if (item.source === 'kfa' && queryCode) {
        isSelectingCode.value = item.kfa_code || item.nie;
        try {
            const response = await axios.get(route('control.admin.incoming-stocks.satusehat-lookup'), {
                params: {
                    q: queryCode,
                    type: queryType,
                },
            });

            if (response.data?.found && response.data?.data) {
                detailCache.value[detailKey] = response.data.data;
                emit('select', {
                    ...response.data.data,
                    _source: 'kfa',
                });
                emit('update:modelValue', false);
                return;
            }
        } catch (err) {
            console.warn('Gagal memuat kemasan spesifik KFA, menggunakan fallback data obat:', err);
        } finally {
            isSelectingCode.value = null;
        }
    }

    emit('select', {
        ...item.data,
        _source: item.source,
    });
    emit('update:modelValue', false);
};

// Terapkan obat dari single preview card (NIE / KFA)
const handleApply = () => {
    if (!resultData.value) return;
    emit('select', {
        ...resultData.value,
        _source: resultSource.value,
    });
    emit('update:modelValue', false);
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="modelValue" 
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-150"
            @keydown.esc="handleClose"
        >
            <div 
                class="bg-card rounded-xl border border-border shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-200" 
                @click.stop
            >
                <!-- Modal Header -->
                <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-muted/20">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs border border-emerald-500/30">
                            KFA
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground tracking-tight">Tarik Data Obat KFA SATUSEHAT</h3>
                                <span class="text-[10px] font-sans font-semibold tracking-wider px-1.5 py-0.5 rounded bg-muted text-muted-foreground border border-border">F2</span>
                            </div>
                            <p class="text-xs text-muted-foreground">Pencarian Standar Farmasi Kemenkes RI & Katalog Obat Apotek</p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        @click="handleClose" 
                        class="w-7 h-7 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
                        title="Tutup (ESC)"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- 3 Pilihan Mode Pencarian (Tab Filter KFA Browser) -->
                <div class="px-5 pt-4 pb-2 bg-muted/10 border-b border-border">
                    <div class="flex items-center p-1 bg-muted/50 rounded-lg border border-border text-xs gap-1">
                        <button 
                            type="button" 
                            @click="handleTypeChange('name')"
                            :class="searchType === 'name' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="flex-1 py-1.5 px-3 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span>Nama Produk (Faktur PBF)</span>
                        </button>

                        <button 
                            type="button" 
                            @click="handleTypeChange('nie')"
                            :class="searchType === 'nie' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="flex-1 py-1.5 px-3 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Nomor NIE BPOM</span>
                        </button>

                        <button 
                            type="button" 
                            @click="handleTypeChange('kfa')"
                            :class="searchType === 'kfa' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="flex-1 py-1.5 px-3 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span>Kode KFA (8 Digit)</span>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-5 overflow-y-auto space-y-4 flex-1">
                    <!-- Form Input Pencarian -->
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                            {{ 
                                searchType === 'name' 
                                    ? 'Ketik Nama Dagang / Merek Obat' 
                                    : (searchType === 'nie' ? 'Nomor Izin Edar BPOM (Kemasan Fisik)' : 'Kode KFA Kemenkes (8 Digit)') 
                            }}
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input 
                                    ref="inputRef"
                                    type="text" 
                                    v-model="query" 
                                    @keydown.enter.prevent="handleSearch"
                                    :placeholder="
                                        searchType === 'name' 
                                            ? 'Contoh: Sanmol, Amoxicillin, Panadol, Biogesic, Cefixime...' 
                                            : (searchType === 'nie' ? 'Contoh: DKL1740809209A1 atau GKL2033548344A1' : 'Contoh: 93000455 (8 digit angka)')
                                    "
                                    :class="searchType === 'name' ? 'font-sans text-sm normal-case' : 'font-sans text-sm font-medium uppercase'"
                                    class="w-full h-10 bg-background border border-input rounded-md px-3 text-foreground placeholder:text-muted-foreground placeholder:normal-case placeholder:text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 transition-all"
                                />
                                <button 
                                    v-if="query" 
                                    type="button" 
                                    @click="query = ''; inputRef?.focus()"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground text-xs p-1"
                                >
                                    ✕
                                </button>
                            </div>
                            <button 
                                type="button" 
                                @click="handleSearch" 
                                :disabled="isLoading || !query.trim()"
                                class="h-10 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-semibold rounded-md transition-colors flex items-center gap-2 shrink-0 shadow-2xs cursor-pointer"
                            >
                                <svg v-if="isLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <span>{{ isLoading ? 'Mencari...' : 'Cari Data' }}</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5 flex items-center gap-1.5">
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">Tips:</span>
                            <span v-if="searchType === 'name'">Cukup ketik nama yang tertulis di kertas faktur supplier. Sistem akan mendeteksi stok lokal apotek maupun cloud resmi Kemenkes.</span>
                            <span v-else-if="searchType === 'nie'">Ketik kode NIE yang tercetak pada kemasan dus/botol obat (diawali DKL, GKL, DBL, GTL).</span>
                            <span v-else>Masukkan 8 digit kode KFA SatuSehat untuk penarikan identik.</span>
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="errorMessage" class="p-3 bg-destructive/10 border border-destructive/25 rounded-lg text-xs text-destructive flex items-start gap-2 animate-in fade-in">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1 leading-relaxed">{{ errorMessage }}</span>
                    </div>

                    <!-- ─── TAMPILAN 1: LIST RESULTS (Untuk Mode Nama Produk) ─── -->
                    <div v-if="searchType === 'name' && listResults.length > 0" class="space-y-2.5">
                        <div class="flex items-center justify-between text-xs text-muted-foreground px-0.5">
                            <span>
                                Menampilkan 
                                <strong class="text-foreground">{{ listResults.length }}</strong> 
                                dari 
                                <strong class="text-foreground">{{ (totalCount || listResults.length).toLocaleString('id-ID') }}</strong> 
                                produk KFA
                                <span v-if="totalPages > 1" class="text-muted-foreground font-normal"> (Halaman {{ currentPage }} dari {{ totalPages }})</span>
                            </span>
                            <span class="text-[11px] hidden sm:inline">Klik <strong>Pilih</strong> untuk mengisi otomatis</span>
                        </div>

                        <div class="space-y-2 max-h-[46vh] overflow-y-auto pr-1">
                            <div 
                                v-for="(item, idx) in listResults" 
                                :key="idx"
                                @click="handleSelectListItem(item)"
                                class="p-3 bg-muted/20 hover:bg-muted/50 border border-border hover:border-emerald-500/50 rounded-lg transition-all flex items-start justify-between gap-3 cursor-pointer group"
                            >
                                <div class="space-y-1.5 flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Badge Sumber Data -->
                                        <span 
                                            class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                                            :class="item.source === 'local' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30'"
                                        >
                                            {{ item.source === 'local' ? 'Stok Lokal Apotek' : 'KFA Kemenkes' }}
                                        </span>
                                        <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground border border-border">
                                            {{ item.bentuk_sediaan }}
                                        </span>
                                        <span v-if="item.nie && item.nie !== '-'" class="text-[10px] font-sans font-medium text-muted-foreground">
                                            NIE: {{ item.nie }}
                                        </span>
                                    </div>

                                    <h4 class="text-sm font-bold text-foreground group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors leading-snug truncate">
                                        {{ item.nama_obat }}
                                    </h4>

                                    <p class="text-xs text-muted-foreground flex items-center gap-2 truncate">
                                        <span v-if="item.pabrik" class="truncate font-medium">Pabrik: {{ item.pabrik }}</span>
                                        <span v-if="item.kfa_code && item.kfa_code !== '-'" class="font-sans font-medium text-[11px] text-muted-foreground shrink-0">
                                            (KFA: {{ item.kfa_code }})
                                        </span>
                                    </p>
                                </div>

                                <button 
                                    type="button" 
                                    @click.stop="handleSelectListItem(item)"
                                    :disabled="isSelectingCode === item.kfa_code"
                                    class="shrink-0 h-8 px-3.5 bg-emerald-600 group-hover:bg-emerald-700 text-white text-xs font-semibold rounded-md transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer mt-1 disabled:opacity-75 disabled:cursor-wait"
                                >
                                    <svg v-if="isSelectingCode === item.kfa_code" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>{{ isSelectingCode === item.kfa_code ? 'Menyiapkan...' : 'Pilih' }}</span>
                                    <svg v-if="isSelectingCode !== item.kfa_code" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Pagination Navigation Bar -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between pt-2.5 border-t border-border mt-2 text-xs">
                            <button
                                type="button"
                                @click="handleSearch(currentPage - 1)"
                                :disabled="currentPage <= 1 || isLoading"
                                class="px-3 py-1.5 rounded-md border border-border bg-card hover:bg-muted text-foreground font-medium disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Sebelumnya</span>
                            </button>

                            <span class="text-xs text-muted-foreground font-medium">
                                Halaman <strong class="text-foreground">{{ currentPage }}</strong> dari <strong class="text-foreground">{{ totalPages }}</strong>
                            </span>

                            <button
                                type="button"
                                @click="handleSearch(currentPage + 1)"
                                :disabled="currentPage >= totalPages || isLoading"
                                class="px-3 py-1.5 rounded-md border border-border bg-card hover:bg-muted text-foreground font-medium disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                <span>Selanjutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- ─── TAMPILAN 2: SINGLE PREVIEW CARD (Untuk Mode NIE / Kode KFA) ─── -->
                    <div v-else-if="resultData" class="p-4 bg-muted/30 border border-border rounded-lg space-y-3 animate-in fade-in">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span 
                                    class="inline-block text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full mb-1"
                                    :class="resultSource === 'local' ? 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'"
                                >
                                    {{ resultSource === 'local' ? 'Terdaftar di Katalog Lokal' : 'Master Resmi KFA SATUSEHAT' }}
                                </span>
                                <h4 class="text-sm font-bold text-foreground leading-snug">
                                    {{ resultData.nama_obat }}
                                </h4>
                                <p v-if="resultData.pabrik" class="text-xs text-muted-foreground">
                                    {{ resultData.pabrik }}
                                </p>
                            </div>
                        </div>

                        <!-- Grid Info Detail -->
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border text-xs">
                            <div>
                                <span class="text-muted-foreground block text-[10px] uppercase font-semibold">Nomor NIE BPOM</span>
                                <span class="font-sans font-semibold text-foreground">{{ resultData.nie || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-[10px] uppercase font-semibold">Kode KFA</span>
                                <span class="font-sans font-semibold text-foreground">{{ resultData.kfa_code || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-[10px] uppercase font-semibold">Bentuk Sediaan</span>
                                <span class="font-medium text-foreground">{{ resultData.kategori || 'Obat Jadi' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-[10px] uppercase font-semibold">Satuan Stok Terkecil</span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ resultData.satuan_stok }}</span>
                            </div>
                        </div>

                        <!-- Kemasan & Rasio Konversi -->
                        <div class="p-2.5 bg-background rounded-md border border-border text-xs space-y-1">
                            <div class="flex items-center justify-between text-muted-foreground text-[11px]">
                                <span class="font-semibold uppercase tracking-wider">Struktur Kemasan:</span>
                                <span v-if="resultData.packaging_raw" class="font-medium text-foreground truncate max-w-[200px]" :title="resultData.packaging_raw">
                                    {{ resultData.packaging_raw }}
                                </span>
                            </div>
                            <div class="font-medium text-foreground flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded bg-muted font-bold text-[11px]">{{ resultData.satuan_input }}</span>
                                <span class="text-muted-foreground">isi</span>
                                <span v-if="resultData.mid_unit" class="text-muted-foreground">
                                    {{ resultData.mid_to_big_ratio }} {{ resultData.mid_unit }} @ {{ resultData.small_to_mid_ratio }} {{ resultData.satuan_stok }}
                                </span>
                                <span v-else class="text-muted-foreground">
                                    {{ resultData.unit_conversion_factor }} {{ resultData.satuan_stok }}
                                </span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold ml-auto text-[11px]">
                                    (Total: {{ resultData.unit_conversion_factor }} {{ resultData.satuan_stok }})
                                </span>
                            </div>
                        </div>

                        <!-- Active Ingredients -->
                        <div v-if="resultData.active_ingredients && resultData.active_ingredients.length" class="text-[11px] text-muted-foreground">
                            <span class="font-semibold text-foreground">Zat Aktif: </span>
                            <span>{{ resultData.active_ingredients.join(', ') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3 border-t border-border bg-muted/20 flex items-center justify-between gap-2">
                    <span class="text-[11px] text-muted-foreground">
                        <span v-if="searchType === 'name'">Pilih salah satu obat dari daftar hasil pencarian.</span>
                        <span v-else>Tekan <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border font-sans font-semibold text-[10px] uppercase">Enter</kbd> untuk menyuntikkan data.</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            @click="handleClose" 
                            class="h-8 px-3 rounded-md text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button 
                            v-if="searchType !== 'name' && resultData"
                            type="button" 
                            @click="handleApply" 
                            class="h-8 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Suntikkan ke Faktur</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
