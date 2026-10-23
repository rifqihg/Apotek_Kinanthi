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

const query = ref('');
const isLoading = ref(false);
const errorMessage = ref('');
const resultData = ref(null);
const resultSource = ref('');
const inputRef = ref(null);

watch(() => props.modelValue, async (isOpen) => {
    if (isOpen) {
        query.value = '';
        errorMessage.value = '';
        resultData.value = null;
        resultSource.value = '';
        await nextTick();
        if (inputRef.value) {
            inputRef.value.focus();
            if (inputRef.value.select) inputRef.value.select();
        }
    }
});

const handleSearch = async () => {
    const clean = query.value.trim();
    if (!clean) {
        errorMessage.value = 'Silakan masukkan Nomor NIE BPOM atau Kode KFA terlebih dahulu.';
        return;
    }

    // Client-side Fail-Fast Check (0ms Lag - Menghemat kuota dan memangkas delay):
    const isNie = /^[a-z]{3}\d+/i.test(clean);
    const isKfa = /^\d{8}$/.test(clean);
    const isBarcode = /^\d{12,14}$/.test(clean);

    if (!isNie && !isKfa && !isBarcode) {
        errorMessage.value = 'Format tidak dikenali. Masukkan Nomor NIE BPOM (contoh: DKL1740809209A1) atau 8 digit Kode KFA.';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';
    resultData.value = null;
    resultSource.value = '';

    try {
        const response = await axios.get(route('control.admin.incoming-stocks.satusehat-lookup'), {
            params: { q: clean },
        });

        const res = response.data;
        if (res.found && res.data) {
            resultData.value = res.data;
            resultSource.value = res.source; // 'kfa' | 'local'
        } else {
            errorMessage.value = res.message || `Data dengan kode '${clean}' tidak ditemukan di SATUSEHAT KFA maupun katalog lokal.`;
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Gagal menghubungi server SATUSEHAT KFA. Periksa koneksi internet Anda.';
    } finally {
        isLoading.value = false;
    }
};

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
                class="bg-card rounded-xl border border-border shadow-2xl w-full max-w-lg overflow-hidden flex flex-col animate-in zoom-in-95 duration-200" 
                @click.stop
            >
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-muted/20">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs border border-emerald-500/30">
                            KFA
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground tracking-tight">Tarik Data Obat KFA BPOM</h3>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-muted text-muted-foreground border border-border font-bold">F2</span>
                            </div>
                            <p class="text-xs text-muted-foreground">Kamus Farmasi & Alat Kesehatan SATUSEHAT Kemenkes</p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        @click="handleClose" 
                        class="w-7 h-7 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4">
                    <!-- Form Input Pencarian -->
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                            Nomor Izin Edar (NIE BPOM) atau Kode KFA
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input 
                                    ref="inputRef"
                                    type="text" 
                                    v-model="query" 
                                    @keydown.enter.prevent="handleSearch"
                                    placeholder="Contoh: GKL2033548344A1 atau 93000455"
                                    class="w-full h-10 bg-background border border-input rounded-md px-3 font-mono text-xs uppercase text-foreground placeholder:text-muted-foreground placeholder:normal-case focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 transition-all"
                                />
                                <button 
                                    v-if="query" 
                                    type="button" 
                                    @click="query = ''; inputRef?.focus()"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground text-xs"
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
                        <p class="text-[11px] text-muted-foreground mt-1.5">
                            Tips: Ketik NIE yang tertera pada kemasan fisik obat (misal diawali DKL, GKL, DBL, GTL).
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="errorMessage" class="p-3 bg-destructive/10 border border-destructive/25 rounded-lg text-xs text-destructive flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1">{{ errorMessage }}</span>
                    </div>

                    <!-- Card Pratinjau Data Ditemukan -->
                    <div v-if="resultData" class="p-4 bg-muted/30 border border-border rounded-lg space-y-3">
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
                                <span class="font-mono font-medium text-foreground">{{ resultData.nie || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-[10px] uppercase font-semibold">Kode KFA</span>
                                <span class="font-mono font-medium text-foreground">{{ resultData.kfa_code || '-' }}</span>
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
                        Tekan <kbd class="px-1 py-0.5 rounded bg-muted border border-border font-mono text-[10px]">Enter</kbd> untuk menyuntikkan data.
                    </span>
                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            @click="handleClose" 
                            class="h-8 px-3 rounded-md text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            :disabled="!resultData" 
                            @click="handleApply" 
                            class="h-8 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
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
