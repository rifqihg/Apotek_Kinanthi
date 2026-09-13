<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    rawMaterials: { type: Array, default: () => [] },
    medicines: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) }
});

const activeTab = ref('raw_materials');
const search = ref(props.filters?.search || '');
const isSearching = ref(false);

// Debounced Server-Side Live Search via Inertia Partial Reload
let searchTimer = null;
const performServerSearch = () => {
    isSearching.value = true;
    router.reload({
        data: {
            search: search.value || undefined,
        },
        preserveState: true,
        preserveScroll: true,
        only: ['rawMaterials', 'medicines', 'filters'],
        onFinish: () => {
            isSearching.value = false;
        },
    });
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        performServerSearch();
    }, 300);
});

const filteredRawMaterials = computed(() => {
    if (!search.value) return props.rawMaterials || [];
    const q = search.value.toLowerCase();
    return (props.rawMaterials || []).filter(rm => 
        (rm.nama_bahan && rm.nama_bahan.toLowerCase().includes(q)) ||
        (rm.kode_bahan && rm.kode_bahan.toLowerCase().includes(q))
    );
});

const filteredMedicines = computed(() => {
    if (!search.value) return props.medicines || [];
    const q = search.value.toLowerCase();
    return (props.medicines || []).filter(m => 
        (m.nama_obat && m.nama_obat.toLowerCase().includes(q)) ||
        (m.kode_obat && m.kode_obat.toLowerCase().includes(q)) ||
        (m.kategori && m.kategori.toLowerCase().includes(q))
    );
});

const isExpired = (d) => !!d && new Date(d) < new Date();

const isNearExpiry = (dateStr) => {
    if (!dateStr) return false;
    const exp = new Date(dateStr);
    const now = new Date();
    const diffDays = (exp - now) / (1000 * 60 * 60 * 24);
    return diffDays >= 0 && diffDays <= 90;
};

const stats = computed(() => {
    let nearCount = 0;
    let expiredCount = 0;

    (props.medicines || []).forEach(m => {
        (m.incoming_stocks || []).forEach(b => {
            if (isExpired(b.expired_date)) expiredCount++;
            else if (isNearExpiry(b.expired_date)) nearCount++;
        });
    });

    (props.rawMaterials || []).forEach(rm => {
        if (isExpired(rm.expired_date)) expiredCount++;
        else if (isNearExpiry(rm.expired_date)) nearCount++;
    });

    return { nearCount, expiredCount };
});
</script>

<template>
    <Head title="Cek Ketersediaan Batch (FEFO)" />
    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Cek Ketersediaan Batch (FEFO)
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pantau urutan pengeluaran first-expired first-out (FEFO), identifikasi tanggal kedaluwarsa, dan minimalkan risiko stok mati.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Calendar Card with Clock (behind left) -->
                        <rect x="4" y="6" width="30" height="43" rx="4" stroke="#D97706" stroke-width="2.2" fill="#FFFFFF" />
                        <rect x="10" y="3.5" width="4" height="4.5" rx="1.2" fill="#D97706" />
                        <rect x="24" y="3.5" width="4" height="4.5" rx="1.2" fill="#D97706" />
                        <path d="M4 6h30v9H4z" fill="#FEF3C7" />
                        <!-- Clock inside calendar -->
                        <circle cx="19" cy="30" r="9.5" stroke="#D97706" stroke-width="1.8" fill="#FFFBEB" />
                        <path d="M19 25v5l3 2.5" stroke="#D97706" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        <!-- Delivery Medicine Box (in front right) -->
                        <g transform="translate(20, 18)">
                            <rect x="0" y="3" width="30" height="28" rx="3" stroke="#D97706" stroke-width="2.2" fill="#FFFFFF" />
                            <path d="M0 11h30" stroke="#D97706" stroke-width="1.6" />
                            <rect x="12" y="3" width="6.5" height="28" fill="#FEF3C7" stroke="#FDE68A" stroke-width="1.2" />
                            <rect x="3.5" y="15" width="6.5" height="8.5" rx="1" stroke="#FDE68A" stroke-width="1.2" fill="#FFFBEB" />
                            <line x1="5" y1="18" x2="8.5" y2="18" stroke="#D97706" stroke-width="0.9" />
                        </g>
                    </svg>
                </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Mendekati ED -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            MENDEKATI ED (≤ 90 HARI)
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.nearCount > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.nearCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.nearCount }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.nearCount > 0 ? 'Prioritas racik / FEFO' : 'Semua batch aman' }}
                    </p>
                </div>

                <!-- Card 2: Sudah Expired -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            KEDALUWARSA
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.expiredCount > 0 ? 'bg-rose-50 dark:bg-rose-950/50 border-rose-100 dark:border-rose-900 text-rose-600 dark:text-rose-400' : 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-100 dark:border-emerald-900 text-emerald-600 dark:text-emerald-400'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.expiredCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ stats.expiredCount }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.expiredCount > 0 ? 'Perlu karantina segera' : 'Stok bebas kedaluwarsa' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION & FILTER BAR -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-card p-3 sm:px-4 rounded-xl border border-slate-200/80 dark:border-border shadow-2xs">
            <!-- Search input -->
            <div class="relative w-full sm:w-72">
                <input 
                    v-model="search"
                    type="text" 
                    placeholder="Cari nama atau kode bahan/obat..." 
                    class="h-9 w-full pl-9 pr-8 bg-background border border-input rounded-md text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                />
                <svg v-if="!isSearching" class="w-4 h-4 text-muted-foreground absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <svg v-else class="w-4 h-4 animate-spin text-primary absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                <button v-if="search" @click="search = ''" class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground hover:text-foreground text-xs font-medium">
                    ✕
                </button>
            </div>

            <!-- Tab Toggle -->
            <div class="flex items-center gap-1 bg-muted p-1 rounded-lg border border-border shrink-0">
                <button 
                    @click="activeTab = 'raw_materials'" 
                    :class="activeTab === 'raw_materials' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground font-medium'"
                    class="h-7 px-3.5 rounded-md text-xs transition-colors"
                >
                    Bahan Baku ({{ filteredRawMaterials.length }})
                </button>
                <button 
                    @click="activeTab = 'medicines'" 
                    :class="activeTab === 'medicines' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground font-medium'"
                    class="h-7 px-3.5 rounded-md text-xs transition-colors"
                >
                    Obat Jadi ({{ filteredMedicines.length }})
                </button>
            </div>
        </div>

        <!-- TAB 1: RAW MATERIALS -->
        <div v-if="activeTab === 'raw_materials'" class="space-y-6">
            <div v-if="filteredRawMaterials.length === 0" class="bg-card border border-border rounded-lg p-12 text-center shadow-sm">
                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                    <svg class="w-12 h-12 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <p class="font-semibold text-foreground text-sm">Belum ada bahan baku yang terdaftar atau sesuai kriteria pencarian</p>
                    <p class="text-xs text-muted-foreground">Semua bahan racikan akan muncul di sini beserta urutan batch FEFO.</p>
                </div>
            </div>

            <div v-for="rm in filteredRawMaterials" :key="rm.id" class="bg-card border border-border rounded-lg shadow-sm overflow-hidden transition-colors">
                <!-- Header Card -->
                <div class="px-6 py-4 border-b border-border bg-muted/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-11 h-11 rounded-md bg-muted text-foreground border border-border flex items-center justify-center font-bold text-sm uppercase">
                            {{ rm.nama_bahan?.substring(0, 2) || 'RM' }}
                        </div>
                        <div>
                            <h3 class="text-foreground font-semibold text-base leading-tight">{{ rm.nama_bahan || 'Bahan Baku' }}</h3>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                <div class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                    <span class="text-xs text-muted-foreground">Kode:</span>
                                    <span class="text-xs font-medium text-foreground">{{ rm.kode_bahan || '-' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                    <span class="text-xs text-muted-foreground">Bentuk:</span>
                                    <span class="text-xs font-medium text-foreground">{{ rm.bentuk_sediaan || '-' }}</span>
                                </div>
                                <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md border border-emerald-200">{{ rm.satuan_ukur || '-' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:items-end">
                        <span class="text-xs text-muted-foreground mb-1">Total Ketersediaan</span>
                        <div :class="Number(rm.stok_total || 0) < (rm.threshold_min || 0) ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'" class="px-3 py-1 rounded-md font-semibold border text-xs">
                            {{ Number(rm.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ rm.satuan_ukur || '' }}
                        </div>
                    </div>
                </div>

                <!-- Batch Details Grid -->
                <div v-if="rm.stocks?.length" class="bg-card">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-x divide-y md:divide-y-0 divide-border">
                        <div v-for="(inv, index) in rm.stocks" :key="inv.id" class="px-4 py-3 flex flex-col gap-2 hover:bg-muted/40 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">BATCH</span>
                                    <span class="text-xs font-semibold text-foreground tracking-wide">{{ inv.batch_no || 'NO-BATCH' }}</span>
                                </div>
                                <span v-if="index === 0" class="text-xs font-semibold text-white bg-emerald-600 px-2 py-0.5 rounded-md">FEFO</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z"></path></svg>
                                    <span v-if="inv.expired_date" :class="isExpired(inv.expired_date) ? 'text-rose-600 font-semibold' : isNearExpiry(inv.expired_date) ? 'text-amber-600 font-semibold' : 'text-muted-foreground'" class="text-xs">
                                        Exp: {{ new Date(inv.expired_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                    </span>
                                    <span v-else class="text-muted-foreground italic text-xs">Tanpa Kadaluarsa</span>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="text-xs text-muted-foreground leading-none mb-0.5">Batch Qty</span>
                                    <span class="text-foreground font-semibold text-xs tabular-nums">{{ Number(inv.stock_on_hand || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ rm.satuan_ukur || '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-6 text-center bg-muted/20">
                    <span class="text-xs font-medium text-muted-foreground">Tidak ada inventaris batch aktif untuk bahan ini</span>
                </div>
            </div>
        </div>

        <!-- TAB 2: MEDICINES (OBAT JADI) -->
        <div v-else-if="activeTab === 'medicines'" class="space-y-6">
            <div v-if="filteredMedicines.length === 0" class="bg-card border border-border rounded-lg p-12 text-center shadow-sm">
                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                    <svg class="w-12 h-12 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <p class="font-semibold text-foreground text-sm">Belum ada obat jadi yang terdaftar atau sesuai kriteria pencarian</p>
                    <p class="text-xs text-muted-foreground">Semua katalog obat jadi apotek akan tampil di sini dengan ketersediaan batch FEFO.</p>
                </div>
            </div>

            <div v-for="med in filteredMedicines" :key="med.id" class="bg-card border border-border rounded-lg shadow-sm overflow-hidden transition-colors">
                <!-- Header Card -->
                <div class="px-6 py-4 border-b border-border bg-muted/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-11 h-11 rounded-md bg-primary/10 text-primary border border-primary/20 flex items-center justify-center font-bold text-sm uppercase">
                            {{ med.nama_obat?.substring(0, 2) || 'OB' }}
                        </div>
                        <div>
                            <h3 class="text-foreground font-semibold text-base leading-tight">{{ med.nama_obat || 'Obat' }}</h3>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                <div class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                    <span class="text-xs text-muted-foreground">Kode:</span>
                                    <span class="text-xs font-medium text-foreground">{{ med.kode_obat || '-' }}</span>
                                </div>
                                <div v-if="med.kategori" class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                    <span class="text-xs text-muted-foreground">Kategori:</span>
                                    <span class="text-xs font-medium text-foreground">{{ med.kategori }}</span>
                                </div>
                                <span class="text-xs font-medium text-primary bg-primary/10 px-2.5 py-0.5 rounded-md border border-primary/20">{{ med.satuan_stok || med.satuan_terkecil || '-' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:items-end">
                        <span class="text-xs text-muted-foreground mb-1">Total Stok Global</span>
                        <div :class="Number(med.stok_total || 0) <= 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-muted text-foreground border-border'" class="px-3 py-1 rounded-md font-semibold border text-xs">
                            {{ Number(med.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ med.satuan_stok || med.satuan_terkecil || '' }}
                        </div>
                    </div>
                </div>

                <!-- Batch Details Grid -->
                <div v-if="med.warehouse_inventories?.length" class="bg-card">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-x divide-y md:divide-y-0 divide-border">
                        <div v-for="(inv, index) in med.warehouse_inventories" :key="inv.id" class="px-4 py-3 flex flex-col gap-2 hover:bg-muted/40 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">BATCH</span>
                                    <span class="text-xs font-semibold text-foreground tracking-wide">{{ inv.batch_no || 'NO-BATCH' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span v-if="index === 0" class="text-xs font-semibold text-white bg-emerald-600 px-2 py-0.5 rounded-md">FEFO</span>
                                    <span :class="inv.location_type === 'kasir' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-muted text-muted-foreground border-border'" class="text-xs font-medium px-2 py-0.5 rounded-md border">
                                        {{ inv.location_type === 'kasir' ? 'Kasir' : 'Gudang' }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z"></path></svg>
                                    <span v-if="inv.expired_date" :class="isExpired(inv.expired_date) ? 'text-rose-600 font-semibold' : isNearExpiry(inv.expired_date) ? 'text-amber-600 font-semibold' : 'text-muted-foreground'" class="text-xs">
                                        Exp: {{ new Date(inv.expired_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                    </span>
                                    <span v-else class="text-muted-foreground italic text-xs">Tanpa Kadaluarsa</span>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="text-xs text-muted-foreground leading-none mb-0.5">Batch Qty</span>
                                    <span class="text-foreground font-semibold text-xs tabular-nums">{{ Number(inv.stock_on_hand || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ med.satuan_stok || med.satuan_terkecil || '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-6 text-center bg-muted/20">
                    <span class="text-xs font-medium text-muted-foreground">Tidak ada inventaris batch aktif untuk obat ini</span>
                </div>
            </div>
        </div>
    </div>
</template>
