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

// Silent server sync if needed
let searchTimer = null;
const performServerSearch = () => {
    router.reload({
        data: {
            search: search.value || undefined,
        },
        preserveState: true,
        preserveScroll: true,
        only: ['rawMaterials', 'medicines', 'filters'],
    });
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        performServerSearch();
    }, 400);
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
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Tab Pills (Bahan Baku vs Obat Jadi) & Status Badge -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="activeTab = 'raw_materials'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeTab === 'raw_materials' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Bahan Baku Racikan</span>
                            <span class="sm:hidden">Bahan Baku</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeTab === 'raw_materials' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                            >{{ filteredRawMaterials.length }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'medicines'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeTab === 'medicines' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Obat Jadi Komersial</span>
                            <span class="sm:hidden">Obat Jadi</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeTab === 'medicines' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                            >{{ filteredMedicines.length }}</span>
                        </button>
                    </div>

                    <div class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-900/60 shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline">Urutan FEFO Aktif</span>
                        <span class="sm:hidden">FEFO</span>
                    </div>
                </div>

                <!-- Baris 2: Full-Width Search Input -->
                <div class="relative w-full">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Cari nama atau kode bahan baku / obat jadi..." 
                        class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                    />
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                        <button v-if="search" type="button" @click="search=''" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SISI KANAN: 2 METRIC CARDS (lg:col-span-4) -->
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

        <!-- TITLE BAR -->
        <div class="flex items-center justify-between mt-4">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                Rincian Stok FEFO
            </h2>
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

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
                <details v-for="rm in filteredRawMaterials" :key="rm.id" class="group bg-card border border-border rounded-md shadow-sm overflow-hidden transition-colors">
                    <!-- Header Card -->
                    <summary class="px-5 py-3 border-b border-border bg-slate-50 dark:bg-muted/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 cursor-pointer list-none select-none hover:bg-slate-100 dark:hover:bg-muted/40 transition-colors [&::-webkit-details-marker]:hidden">
                        <div class="flex items-center gap-4">
                            <div class="text-slate-400 group-open:rotate-180 transition-transform duration-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div class="w-11 h-11 rounded-md bg-muted text-foreground border border-border flex items-center justify-center font-bold text-sm uppercase shrink-0">
                                {{ rm.nama_bahan?.substring(0, 2) || 'RM' }}
                            </div>
                            <div>
                                <h3 class="text-foreground font-semibold text-base leading-tight">{{ rm.nama_bahan || 'Bahan Baku' }}</h3>
                                <!-- Details moved to inside -->
                            </div>
                        </div>
                        <div class="flex flex-col sm:items-end shrink-0 pl-9 sm:pl-0">
                            <span class="text-xs text-muted-foreground mb-1">Total Ketersediaan</span>
                            <div :class="Number(rm.stok_total || 0) < (rm.threshold_min || 0) ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'" class="px-3 py-1 rounded-md font-semibold border text-xs">
                                {{ Number(rm.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ rm.satuan_dasar_penggunaan || '' }}
                            </div>
                        </div>
                    </summary>

                    <!-- Accordion Content -->
                    <div class="group-open:animate-in group-open:slide-in-from-top-2 group-open:fade-in-0 duration-200">
                        <!-- Batch Details List -->
                        <div v-if="rm.stocks?.length" class="bg-card p-3">
                            <div class="flex flex-col gap-2">
                                <div v-for="(inv, index) in rm.stocks" :key="inv.id" class="px-4 py-2.5 rounded-md border border-slate-200 dark:border-border bg-slate-50/50 dark:bg-muted/20 hover:bg-slate-50 dark:hover:bg-muted/40 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                        <div class="flex items-center min-w-[120px]">
                                            <span class="text-sm font-bold text-slate-800 dark:text-foreground tracking-wide">#{{ inv.batch_no || 'NO-BATCH' }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 min-w-[140px]">
                                            <svg class="w-3.5 h-3.5 text-slate-400 dark:text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z"></path></svg>
                                            <span v-if="inv.expired_date" :class="isExpired(inv.expired_date) ? 'text-rose-600 font-semibold' : isNearExpiry(inv.expired_date) ? 'text-amber-600 font-semibold' : 'text-slate-600 dark:text-foreground'" class="text-[13px] font-medium">
                                                {{ new Date(inv.expired_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                            </span>
                                            <span v-else class="text-slate-400 dark:text-muted-foreground italic text-[13px]">Tanpa ED</span>
                                            <span v-if="index === 0 && inv.expired_date" class="ml-2 text-[9px] font-bold text-white bg-emerald-500 px-1.5 py-0.5 rounded-sm uppercase tracking-wider">FEFO</span>
                                        </div>
                                    </div>
                                    
                                    <!-- QTY & Gudang removed, replaced by Kode and Bentuk -->
                                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                                        <div v-if="rm.kode_bahan" class="flex items-center gap-1.5 bg-white dark:bg-background px-2.5 py-0.5 rounded-md border border-slate-200 dark:border-border">
                                            <span class="text-[10px] text-muted-foreground">Kode:</span>
                                            <span class="text-[10px] font-semibold text-slate-700 dark:text-foreground">{{ rm.kode_bahan }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 bg-white dark:bg-background px-2.5 py-0.5 rounded-md border border-slate-200 dark:border-border">
                                            <span class="text-[10px] text-muted-foreground">Bentuk:</span>
                                            <span class="text-[10px] font-semibold text-slate-700 dark:text-foreground">{{ rm.bentuk_sediaan || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-6 text-center bg-muted/20">
                            <span class="text-xs font-medium text-muted-foreground">Tidak ada inventaris batch aktif untuk bahan ini</span>
                        </div>
                    </div>
                </details>
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

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
                <details v-for="med in filteredMedicines" :key="med.id" class="group bg-card border border-border rounded-md shadow-sm overflow-hidden transition-colors">
                    <!-- Header Card -->
                    <summary class="px-5 py-3 border-b border-border bg-slate-50 dark:bg-muted/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 cursor-pointer list-none select-none hover:bg-slate-100 dark:hover:bg-muted/40 transition-colors [&::-webkit-details-marker]:hidden">
                        <div class="flex items-center gap-4">
                            <div class="text-slate-400 group-open:rotate-180 transition-transform duration-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div class="w-11 h-11 rounded-md bg-primary/10 text-primary border border-primary/20 flex items-center justify-center font-bold text-sm uppercase shrink-0">
                                {{ med.nama_obat?.substring(0, 2) || 'OB' }}
                            </div>
                            <div>
                                <h3 class="text-foreground font-semibold text-base leading-tight">{{ med.nama_obat || 'Obat' }}</h3>
                                <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                    <div v-if="med.kode_obat" class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                        <span class="text-xs text-muted-foreground">Kode:</span>
                                        <span class="text-xs font-medium text-foreground">{{ med.kode_obat }}</span>
                                    </div>
                                    <div v-if="med.kategori" class="flex items-center gap-1.5 bg-background px-2.5 py-0.5 rounded-md border border-border">
                                        <span class="text-xs text-muted-foreground">Kategori:</span>
                                        <span class="text-xs font-medium text-foreground">{{ med.kategori }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:items-end shrink-0 pl-9 sm:pl-0">
                            <span class="text-xs text-muted-foreground mb-1">Total Stok Global</span>
                            <div :class="Number(med.stok_total || 0) <= 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-muted text-foreground border-border'" class="px-3 py-1 rounded-md font-semibold border text-xs">
                                {{ Number(med.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ med.satuan_stok || med.satuan_terkecil || '' }}
                            </div>
                        </div>
                    </summary>

                    <!-- Accordion Content -->
                    <div class="group-open:animate-in group-open:slide-in-from-top-2 group-open:fade-in-0 duration-200">
                        <!-- Batch Details List -->
                        <div v-if="med.warehouse_inventories?.length" class="bg-card p-3">
                            <div class="flex flex-col gap-2">
                                <div v-for="(inv, index) in med.warehouse_inventories" :key="inv.id" class="px-4 py-2.5 rounded-md border border-slate-200 dark:border-border bg-slate-50/50 dark:bg-muted/20 hover:bg-slate-50 dark:hover:bg-muted/40 transition-colors flex items-center justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                        <div class="flex items-center w-32">
                                            <span class="text-sm font-bold text-slate-800 dark:text-foreground tracking-wide">#{{ inv.batch_no || 'NO-BATCH' }}</span>
                                        </div>
                                        <div class="flex items-center w-24">
                                            <span :class="inv.location_type === 'kasir' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800' : 'bg-white dark:bg-background text-slate-500 dark:text-muted-foreground border-slate-200 dark:border-border'" class="border text-[10px] font-semibold px-2 py-0.5 rounded-md uppercase tracking-wider">
                                                {{ inv.location_type === 'kasir' ? 'Kasir' : 'Gudang' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 min-w-[160px]">
                                            <svg class="w-3.5 h-3.5 text-slate-400 dark:text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z"></path></svg>
                                            <span v-if="inv.expired_date" :class="isExpired(inv.expired_date) ? 'text-rose-600 font-semibold' : isNearExpiry(inv.expired_date) ? 'text-amber-600 font-semibold' : 'text-slate-600 dark:text-foreground'" class="text-[13px] font-medium">
                                                {{ new Date(inv.expired_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                            </span>
                                            <span v-else class="text-slate-400 dark:text-muted-foreground italic text-[13px]">Tanpa ED</span>
                                            <span v-if="index === 0 && inv.expired_date" class="ml-2 text-[9px] font-bold text-white bg-emerald-500 px-1.5 py-0.5 rounded-sm uppercase tracking-wider">FEFO</span>
                                        </div>
                                    </div>
                                    
                                    <div class="inline-flex items-center gap-2 bg-white dark:bg-background border border-slate-200 dark:border-border px-3 py-1.5 rounded-md shadow-2xs shrink-0">
                                        <span class="text-slate-400 dark:text-muted-foreground text-[10px] font-semibold uppercase tracking-wider">Qty</span>
                                        <span class="text-slate-800 dark:text-foreground font-bold text-sm tabular-nums">{{ Number(inv.stock_on_hand || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ med.satuan_stok || med.satuan_terkecil || '' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-6 text-center bg-muted/20">
                            <span class="text-xs font-medium text-muted-foreground">Tidak ada inventaris batch aktif untuk obat ini</span>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>
</template>
