<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    audits: { type: Object, default: () => ({ data: [], links: [] }) },
    kpi: { type: Object, default: () => ({ in: 0, out: 0, total: 0 }) },
    filters: { type: Object, default: () => ({}) }
});

// Tab state: 'mutasi' or 'audit' (0ms instant transition)
const activeTab = ref(
    props.filters?.tab === 'audit' || (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'audit') 
        ? 'audit' 
        : 'mutasi'
);

const switchTab = (tab) => {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
};

// ─── TAB 1: MUTASI STOK STATE & LIVE FILTER ──────────────────
const search = ref(props.filters?.search || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const filterType = ref(props.filters?.type || 'all');
const isControlled = ref(props.filters?.is_controlled === '1' || props.filters?.is_controlled === true);
const filterDropdownOpen = ref(false);
const isFilteringLogs = ref(false);

const applyMutationFilters = () => {
    isFilteringLogs.value = true;
    router.get(route('control.admin.stock-logs'), {
        tab: 'mutasi',
        search: search.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        type: filterType.value !== 'all' ? filterType.value : undefined,
        is_controlled: isControlled.value ? true : undefined,
        mutations_page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { isFilteringLogs.value = false; },
    });
};

const debouncedMutationSearch = useDebounceFn(() => {
    applyMutationFilters();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        debouncedMutationSearch();
    }
});

watch([startDate, endDate, filterType, isControlled], () => {
    applyMutationFilters();
});

const activeLogs = computed(() => props.logs || { data: [], links: [] });
const activeKpi = computed(() => props.kpi || { in: 0, out: 0, total: 0 });

const clearFilters = () => {
    search.value = '';
    startDate.value = '';
    endDate.value = '';
    filterType.value = 'all';
    isControlled.value = false;
    applyMutationFilters();
};

const displayType = (l) => {
    if ((l.note && l.note.toLowerCase().includes('distribusi')) || (l.type && l.type.includes('Distribusi'))) return 'Distribusi';
    if (l.type === 'Masuk' || l.type === 'Penyesuaian Masuk') return 'Masuk';
    if (l.reference_type === 'App\\Models\\Control\\ReturnForm' || l.type === 'Retur') return 'Retur';
    if (l.type === 'Keluar' || l.type === 'Penjualan' || l.type === 'Penyesuaian Keluar') return 'Terjual';
    return l.type;
};

const getLogTypeClass = (type) => ({
    Masuk: 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800/40',
    Retur: 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800/40',
    Terjual: 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800/40',
    Distribusi: 'bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-800/40',
}[type] || 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/40 dark:text-slate-400 dark:border-slate-800');

// Format Referensi
const refTitles = { ReturnForm: 'Retur Pembelian', Sale: 'Penjualan (Kasir)', StockOpname: 'Stock Opname', IncomingStock: 'Faktur Masuk' };
const formatReference = (l) => {
    if (l.reference_type) {
        const type = l.reference_type.split('\\').pop();
        return { title: refTitles[type] || type, id: 'ID: ' + l.reference_id };
    }

    if (l.note) {
        let noteText = l.note;
        if (noteText.includes('Terima Distribusi dari Gudang') || noteText.includes('Distribusi ke Kasir')) {
            noteText = 'Distribusi ke Kasir';
        } else if (noteText.includes('Return Distribusi dari Kasir') || noteText.includes('Return dari Kasir')) {
            noteText = 'Distribusi ke Gudang';
        }
        return { title: 'Aktivitas Sistem', id: noteText };
    }

    if (l.type === 'Masuk' && l.inventory?.incoming_stock) {
        return {
            title: 'Faktur Masuk',
            id: l.inventory.incoming_stock.faktur_no || '-'
        };
    }

    return { title: '-', id: '' };
};

// Kemasan Breakdown
const getMutationUnits = (qty, inventory) => {
    let rem = Number(qty) || 0;
    const incoming = inventory?.incoming_stock || inventory?.incomingStock;
    const med = inventory?.medicine;
    const uSmall = med?.satuan_stok || med?.base_unit || 'Unit';
    const uMid = incoming?.mid_unit || med?.satuan_menengah;
    const uBig = incoming?.satuan_input || med?.satuan_besar;

    if (rem <= 0) return [{ qty: 0, unit: uSmall }];

    const rSekunder = Math.max(1, Number(incoming?.small_to_mid_ratio || med?.rasio_konversi) || 1);
    const rTersier = Math.max(1, Number(incoming?.mid_to_big_ratio) || 1);
    const totalSmallInBig = (rTersier > 1 && rSekunder > 1)
        ? rTersier * rSekunder
        : Math.max(1, Number(incoming?.unit_conversion_factor || med?.rasio_konversi) || 1);

    const res = [];
    if (uBig && totalSmallInBig > 1 && rem >= totalSmallInBig) {
        res.push({ qty: Math.floor(rem / totalSmallInBig), unit: uBig });
        rem %= totalSmallInBig;
    }
    if (uMid && rSekunder > 1 && rem >= rSekunder) {
        res.push({ qty: Math.floor(rem / rSekunder), unit: uMid });
        rem %= rSekunder;
    }
    if (rem > 0) {
        res.push({ qty: Number(rem.toFixed(2)), unit: uSmall });
    }
    return res.length ? res : [{ qty: 0, unit: uSmall }];
};

// ─── TAB 2: AUDIT TRAIL STATE & LIVE FILTER ──────────────────
const auditSearch = ref(props.filters?.audit_search || '');
const roleFilter = ref(props.filters?.role || '');
const isFilteringAudits = ref(false);

const applyAuditFilters = () => {
    isFilteringAudits.value = true;
    router.get(route('control.admin.stock-logs'), {
        tab: 'audit',
        audit_search: auditSearch.value || undefined,
        role: roleFilter.value || undefined,
        audits_page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { isFilteringAudits.value = false; },
    });
};

const debouncedAuditSearch = useDebounceFn(() => {
    applyAuditFilters();
}, 300);

watch(auditSearch, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        debouncedAuditSearch();
    }
});

watch(roleFilter, () => {
    applyAuditFilters();
});

const activeAudits = computed(() => props.audits || { data: [], links: [] });

const clearAuditFilters = () => {
    auditSearch.value = '';
    roleFilter.value = '';
    applyAuditFilters();
};

const getActionClass = (a) => {
    if (!a) return 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/40 dark:text-slate-400 dark:border-slate-800';
    const action = a.toUpperCase();
    if (action.includes('CREATE')) return 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800/40';
    if (action.includes('UPDATE')) return 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800/40';
    if (action.includes('DELETE')) return 'bg-red-50 text-red-600 border-red-100 dark:bg-red-950/30 dark:text-red-400 dark:border-red-800/40';
    if (action.includes('REJECT')) return 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800/40';
    return 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/40 dark:text-slate-400 dark:border-slate-800';
};

const formatAuditDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return dateStr;
    }
};
</script>

<template>
<Head :title="activeTab === 'mutasi' ? 'Riwayat Mutasi Stok' : 'Jejak Audit Aktivitas'" />
<div class="space-y-6">
    <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- HERO BANNER CARD (lg:col-span-8) -->
        <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
            <!-- Left Text Column -->
            <div class="relative z-10 max-w-sm sm:max-w-md">
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                    Riwayat Mutasi & Jejak Audit
                </h1>
                <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                    Pemantauan transparan pergerakan fisik stok obat serta rekam jejak aktivitas operasional pengguna.
                </p>
            </div>

            <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
            <div class="relative z-10 flex items-center justify-center shrink-0">
                <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Stock Ledger Sheet (behind left) -->
                        <rect x="4" y="4" width="31" height="44" rx="4" stroke="#0891B2" stroke-width="2.2" fill="#FFFFFF" />
                        <rect x="8" y="9" width="15" height="3.5" rx="1.2" fill="#06B6D4" />
                        <line x1="8" y1="17" x2="28" y2="17" stroke="#0891B2" stroke-opacity="0.25" stroke-width="1.4" />
                        <line x1="8" y1="23" x2="28" y2="23" stroke="#0891B2" stroke-opacity="0.25" stroke-width="1.4" />
                        <!-- Dynamic Trend Line Chart -->
                        <path d="M8 39l6-7 7 3 9-11" stroke="#0891B2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                        <circle cx="14" cy="32" r="1.6" fill="#06B6D4" />
                        <circle cx="21" cy="35" r="1.6" fill="#06B6D4" />
                        <circle cx="30" cy="24" r="2" fill="#0891B2" />
                        <!-- In/Out Mutation Badge (in front right) -->
                        <g transform="translate(23, 16)">
                            <circle cx="14.5" cy="14.5" r="13.5" stroke="#0891B2" stroke-width="2.2" fill="#FFFFFF" />
                            <circle cx="14.5" cy="14.5" r="10.5" fill="#ECFEFF" />
                            <path d="M10.5 19V10M7 13.5l3.5-3.5 3.5 3.5" stroke="#0891B2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M18.5 10v9m-3.5-3.5l3.5 3.5 3.5-3.5" stroke="#0891B2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 2 STAT CARDS (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-3">
            <!-- Card 1: Arus Stok Masuk -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        ARUS MASUK
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ activeKpi.in || 0 }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                    Faktur & penyesuaian masuk
                </p>
            </div>

            <!-- Card 2: Arus Stok Keluar -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        ARUS KELUAR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted border border-slate-200 dark:border-border flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 8v12m0 0l4-4m-4 4l-4-4M7 16V4m0 0L3 8m4-4l4 4" />
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                        {{ activeKpi.out || 0 }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                    Penjualan kasir & racikan
                </p>
            </div>
        </div>
    </div>

    <!-- ═══ TAB SWITCHER (SEGMENTED CONTROL) ═══ -->
    <div class="flex items-center justify-between gap-4 border-b border-border/70 pb-3">
        <div class="inline-flex p-1 bg-slate-100 dark:bg-muted/60 border border-slate-200/80 dark:border-border rounded-xl">
            <button
                type="button"
                @click="switchTab('mutasi')"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-200 cursor-pointer"
                :class="activeTab === 'mutasi'
                    ? 'bg-white dark:bg-card text-slate-900 dark:text-foreground shadow-2xs border border-slate-200/50 dark:border-border/50'
                    : 'text-slate-500 dark:text-muted-foreground hover:text-slate-900 dark:hover:text-foreground'"
            >
                <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
                Mutasi Fisik Stok
                <span v-if="activeKpi.total" class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold border border-emerald-100 dark:border-emerald-800/40">
                    {{ activeKpi.total }}
                </span>
            </button>
            <button
                type="button"
                @click="switchTab('audit')"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-200 cursor-pointer"
                :class="activeTab === 'audit'
                    ? 'bg-white dark:bg-card text-slate-900 dark:text-foreground shadow-2xs border border-slate-200/50 dark:border-border/50'
                    : 'text-slate-500 dark:text-muted-foreground hover:text-slate-900 dark:hover:text-foreground'"
            >
                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Jejak Aktivitas Sistem
                <span v-if="activeAudits.total" class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold border border-blue-100 dark:border-blue-800/40">
                    {{ activeAudits.total }}
                </span>
            </button>
        </div>
    </div>

    <!-- ═══ TAB 1: MUTASI FISIK STOK ═══ -->
    <div v-show="activeTab === 'mutasi'" class="space-y-4">
        <!-- Filters -->
        <div class="bg-card border border-border p-3 rounded-lg shadow-sm flex flex-col md:flex-row gap-3 md:items-center justify-between">
            <div class="flex flex-1 flex-row gap-2 items-center w-full md:w-auto">
                <div class="flex-1 min-w-[150px] relative">
                    <input v-model="search" type="text" placeholder="Cari obat..." 
                           class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-8 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" />
                    <svg v-if="!isFilteringLogs" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <svg v-else class="animate-spin w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <button 
                        v-if="search" 
                        @click="search = ''; applyMutationFilters()" 
                        type="button" 
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer p-0.5 rounded-full hover:bg-muted transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="relative shrink-0">
                    <button @click="filterDropdownOpen = !filterDropdownOpen" @blur="setTimeout(() => filterDropdownOpen = false, 200)" class="h-9 w-auto bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground hover:bg-accent cursor-pointer outline-none transition-colors flex items-center justify-between gap-2 shadow-sm">
                        <div class="flex items-center gap-1.5">
                            <span v-if="filterType === 'all'" class="text-foreground">Semua Log</span>
                            <span v-else-if="filterType === 'Masuk'" class="text-emerald-600 font-semibold">Masuk</span>
                            <span v-else-if="filterType === 'Retur'" class="text-amber-600 font-semibold">Retur</span>
                            <span v-else-if="filterType === 'Jual'" class="text-primary font-semibold">Terjual</span>
                            <span v-else-if="filterType === 'Distribusi'" class="text-indigo-600 font-semibold">Distribusi</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200" :class="{'rotate-180': filterDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="filterDropdownOpen" class="absolute right-0 sm:left-0 sm:right-auto top-full mt-1.5 w-full min-w-[140px] bg-card border border-border rounded-md shadow-lg overflow-hidden z-20 origin-top-right sm:origin-top-left transition-all">
                        <div class="p-1 space-y-0.5">
                            <button @click="filterType = 'all'" class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md flex items-center justify-between group transition-colors" :class="filterType === 'all' ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                                Semua Log
                                <div v-if="filterType === 'all'" class="w-1.5 h-1.5 rounded-full bg-foreground"></div>
                            </button>
                            <div class="h-px bg-border my-1"></div>
                            <button @click="filterType = 'Masuk'" class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md flex items-center justify-between group transition-colors" :class="filterType === 'Masuk' ? 'bg-emerald-500/10 text-emerald-600' : 'text-muted-foreground hover:bg-muted hover:text-emerald-600'">
                                Masuk
                                <div v-if="filterType === 'Masuk'" class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                            </button>
                            <button @click="filterType = 'Retur'" class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md flex items-center justify-between group transition-colors" :class="filterType === 'Retur' ? 'bg-amber-500/10 text-amber-600' : 'text-muted-foreground hover:bg-muted hover:text-amber-600'">
                                Retur
                                <div v-if="filterType === 'Retur'" class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                            </button>
                            <button @click="filterType = 'Jual'" class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md flex items-center justify-between group transition-colors" :class="filterType === 'Jual' ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-primary'">
                                Terjual
                                <div v-if="filterType === 'Jual'" class="w-1.5 h-1.5 rounded-full bg-primary"></div>
                            </button>
                            <button @click="filterType = 'Distribusi'" class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md flex items-center justify-between group transition-colors" :class="filterType === 'Distribusi' ? 'bg-indigo-50 text-indigo-600' : 'text-muted-foreground hover:bg-muted hover:text-indigo-600'">
                                Distribusi
                                <div v-if="filterType === 'Distribusi'" class="w-1.5 h-1.5 rounded-full bg-indigo-500"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scrollable container for dates and extra filters on mobile -->
            <div class="flex flex-row items-center gap-2 overflow-x-auto pb-1 md:pb-0 w-full md:w-auto shrink-0 hide-scrollbar" style="-ms-overflow-style: none; scrollbar-width: none;">
                <input v-model="startDate" type="date" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0" />
                <span class="text-muted-foreground text-xs shrink-0">-</span>
                <input v-model="endDate" type="date" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring shrink-0" />
                
                <button v-if="search || startDate || endDate || filterType !== 'all' || isControlled" @click="clearFilters" class="h-9 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 rounded-md transition-colors cursor-pointer shrink-0">
                    Reset
                </button>
                
                <div class="h-6 w-px bg-border hidden md:block mx-1"></div>
                
                <button 
                    @click="isControlled = !isControlled" 
                    type="button" 
                    class="h-9 px-3 text-xs font-medium rounded-md transition-all flex items-center gap-1.5 border cursor-pointer shrink-0 ml-auto md:ml-0"
                    :class="isControlled ? 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-800/40' : 'bg-background text-muted-foreground border-input hover:bg-accent hover:text-foreground'"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Filter Mutasi Terkontrol
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <Table class="w-full min-w-[960px]">
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="min-w-[140px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Waktu</TableHead>
                            <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Jenis</TableHead>
                            <TableHead class="min-w-[220px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Item (Obat / Bahan)</TableHead>
                            <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Batch</TableHead>
                            <TableHead class="min-w-[140px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Referensi</TableHead>
                            <TableHead class="min-w-[110px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Qty Mutasi</TableHead>
                            <TableHead class="min-w-[110px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Sisa Stok</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="!activeLogs.data || !activeLogs.data.length">
                            <TableCell colspan="7" class="py-8 px-3 text-center text-muted-foreground">
                                <div class="flex flex-col items-center gap-1.5">
                                    <svg class="w-7 h-7 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    <span class="text-xs font-medium">Belum terdapat aktivitas mutasi stok pada periode yang dipilih.</span>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="l in (activeLogs.data || [])" :key="l.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                            <!-- Waktu -->
                            <TableCell class="py-2.5 px-3 align-middle text-foreground text-sm font-medium tabular-nums whitespace-nowrap">
                                {{ new Date(l.created_at).toLocaleString('id-ID', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}
                            </TableCell>
                            <!-- Jenis -->
                            <TableCell class="py-2.5 px-3 align-middle text-center">
                                <span :class="getLogTypeClass(displayType(l))" class="px-2.5 py-0.5 rounded-md text-xs font-medium border inline-block">
                                    {{ displayType(l) }}
                                </span>
                            </TableCell>
                            <!-- Item -->
                            <TableCell class="py-2.5 px-3 align-middle text-left">
                                <div class="flex flex-col min-w-0 text-left">
                                    <div class="flex items-center justify-start gap-2 truncate">
                                        <span class="text-sm font-medium text-foreground leading-tight truncate">
                                            {{ l.inventory?.medicine?.nama_obat || l.raw_material_stock?.raw_material?.nama_bahan || l.rawMaterialStock?.rawMaterial?.nama_bahan || l.item?.nama_obat || l.item?.nama_bahan || '-' }}
                                        </span>
                                        <span v-if="l.inventory?.medicine?.classification === 'narkotika' || l.inventory?.medicine?.classification === 'psikotropika'" 
                                            class="inline-flex items-center justify-center w-4 h-4 rounded-full shrink-0"
                                            :class="l.inventory?.medicine?.classification === 'narkotika' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'"
                                            :title="'Golongan ' + l.inventory?.medicine?.classification.toUpperCase()">
                                            <svg v-if="l.inventory?.medicine?.classification === 'narkotika'" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                            <span v-else class="text-[9px] font-bold">K</span>
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground leading-tight mt-0.5">
                                        {{ l.inventory?.medicine ? 'Obat Jadi' : ((l.rawMaterialStock || l.raw_material_stock) ? 'Bahan Baku Racikan' : 'Item') }}
                                    </span>
                                </div>
                            </TableCell>
                            <!-- Batch -->
                            <TableCell class="py-2.5 px-3 align-middle text-center text-sm font-medium text-foreground tracking-tight truncate">
                                {{ l.inventory?.batch_no || l.raw_material_stock?.batch_no || l.rawMaterialStock?.batch_no || '-' }}
                            </TableCell>
                            <!-- Referensi -->
                            <TableCell class="py-2.5 px-3 align-middle">
                                <div class="flex flex-col">
                                    <span class="text-xs font-medium text-foreground leading-tight">{{ formatReference(l).title }}</span>
                                    <span class="text-[11px] text-muted-foreground leading-tight mt-0.5">{{ formatReference(l).id }}</span>
                                </div>
                            </TableCell>
                            <!-- Qty Mutasi -->
                            <TableCell class="py-2.5 px-3 align-middle text-center tabular-nums">
                                <!-- Hierarchical Multi-unit display for Medicine -->
                                <div v-if="l.inventory?.medicine" class="flex flex-nowrap items-center justify-center gap-1.5">
                                    <template v-for="(b, i) in getMutationUnits(l.quantity, l.inventory)" :key="i">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                            <span class="font-semibold tabular-nums" :class="l.type === 'Masuk' || l.type === 'Penyesuaian Masuk' ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">{{ b.qty }}</span>
                                            <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ b.unit }}</span>
                                        </div>
                                    </template>
                                </div>
                                <!-- Base Unit Display for Raw Materials -->
                                <div v-else class="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                                    <span class="text-sm font-semibold tabular-nums" :class="l.type === 'Masuk' || l.type === 'Penyesuaian Masuk' ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">
                                        {{ Number(l.quantity) }}
                                    </span>
                                    <span class="uppercase text-[11px] tracking-wide text-muted-foreground">{{ l.rawMaterialStock?.rawMaterial?.satuan_dasar_penggunaan || l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                                </div>
                            </TableCell>
                            <!-- Sisa Stok -->
                            <TableCell class="py-2.5 px-3 align-middle text-center tabular-nums">
                                <!-- Hierarchical Multi-unit display for Medicine -->
                                <div v-if="l.inventory?.medicine" class="flex flex-nowrap items-center justify-center gap-1.5">
                                    <template v-for="(b, i) in getMutationUnits(l.balance_after, l.inventory)" :key="i">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                            <span class="font-semibold tabular-nums text-foreground">{{ b.qty }}</span>
                                            <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ b.unit }}</span>
                                        </div>
                                    </template>
                                </div>
                                <!-- Base Unit Display -->
                                <div v-else class="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                                    <span class="text-sm font-semibold text-foreground tabular-nums">{{ Number(l.balance_after) }}</span>
                                    <span class="uppercase text-[11px] tracking-wide text-muted-foreground">{{ l.rawMaterialStock?.rawMaterial?.satuan_dasar_penggunaan || l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="activeLogs.links && activeLogs.links.length > 3" class="px-5 py-3 border-t border-border flex items-center justify-center gap-1 bg-muted/30">
                <template v-for="(l, index) in activeLogs.links" :key="index">
                    <Link v-if="l.url" :href="l.url" preserve-scroll preserve-state :class="l.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border border-input text-foreground hover:bg-accent font-medium'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs transition-colors" v-html="l.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- ═══ TAB 2: JEJAK AKTIVITAS SISTEM (AUDIT TRAIL) ═══ -->
    <div v-show="activeTab === 'audit'" class="space-y-4">
        <!-- Audit Filters -->
        <div class="bg-card border border-border p-3 rounded-lg shadow-sm flex flex-col md:flex-row gap-4 md:items-center justify-between">
            <div class="flex flex-col sm:flex-row flex-1 w-full md:w-auto items-stretch sm:items-center gap-3">
                <div class="flex-1 min-w-[220px] max-w-md relative">
                    <input v-model="auditSearch" type="text" placeholder="Cari aktivitas, tindakan, atau user..." 
                           class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-8 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" />
                    <svg v-if="!isFilteringAudits" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <svg v-else class="animate-spin w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <button 
                        v-if="auditSearch" 
                        @click="auditSearch = ''; applyAuditFilters()" 
                        type="button" 
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer p-0.5 rounded-full hover:bg-muted transition-colors"
                        title="Hapus pencarian"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <select v-model="roleFilter" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer">
                    <option value="">Semua Role</option>
                    <option value="admin">Admin</option>
                    <option value="apoteker">Apoteker</option>
                    <option value="kasir">Kasir</option>
                </select>

                <button v-if="auditSearch || roleFilter" @click="clearAuditFilters" class="h-9 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 rounded-md transition-colors cursor-pointer">
                    Reset
                </button>
            </div>

            <div class="text-xs text-muted-foreground font-medium">
                Total Aktivitas: <span class="font-bold text-foreground">{{ activeAudits.total || 0 }}</span> entri
            </div>
        </div>

        <!-- Audit Table -->
        <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Waktu</TableHead>
                            <TableHead class="py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Pengguna</TableHead>
                            <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Tindakan</TableHead>
                            <TableHead class="min-w-[350px] py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Deskripsi Aktivitas</TableHead>
                            <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Model Target</TableHead>
                            <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Record ID</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="!activeAudits.data || !activeAudits.data.length">
                            <TableCell colspan="6" class="py-8 px-3 text-center text-muted-foreground">
                                <div class="flex flex-col items-center gap-1.5">
                                    <svg class="w-7 h-7 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span class="text-xs font-medium">Belum terdapat catatan audit trail yang cocok.</span>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="a in (activeAudits.data || [])" :key="a.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                            <!-- Waktu -->
                            <TableCell class="py-2.5 px-3 align-middle text-foreground text-sm font-medium tabular-nums whitespace-nowrap">
                                {{ formatAuditDate(a.performed_at) }}
                            </TableCell>
                            <!-- Pengguna -->
                            <TableCell class="py-2 px-3 align-middle whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-foreground leading-tight">{{ a.user?.name || 'Sistem Otomatis' }}</span>
                                    <span class="text-xs text-muted-foreground uppercase tracking-wider leading-tight mt-0.5">{{ a.user?.role || 'SYSTEM' }}</span>
                                </div>
                            </TableCell>
                            <!-- Aksi -->
                            <TableCell class="py-2 px-3 align-middle text-center whitespace-nowrap">
                                <span :class="getActionClass(a.action)" class="px-2 py-0.5 rounded text-xs font-medium border inline-block tracking-wider">
                                    {{ a.action }}
                                </span>
                            </TableCell>
                            <!-- Deskripsi -->
                            <TableCell class="py-2 px-3 align-middle text-foreground font-normal max-w-md">
                                <span class="text-xs text-foreground line-clamp-2 block">{{ a.description }}</span>
                            </TableCell>
                            <!-- Model Target -->
                            <TableCell class="py-2 px-3 align-middle text-center text-xs text-muted-foreground font-mono">
                                {{ a.auditable_type ? a.auditable_type.split('\\').pop() : '-' }}
                            </TableCell>
                            <!-- Record ID -->
                            <TableCell class="py-2 px-3 align-middle text-center text-xs font-mono text-muted-foreground">
                                {{ a.auditable_id || '-' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Audit Pagination -->
            <div v-if="activeAudits.links && activeAudits.links.length > 3" class="px-5 py-3 border-t border-border flex items-center justify-center gap-1 bg-muted/30">
                <template v-for="(l, index) in activeAudits.links" :key="index">
                    <Link v-if="l.url" :href="l.url" preserve-scroll preserve-state :class="l.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border border-input text-foreground hover:bg-accent font-medium'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs transition-colors" v-html="l.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>
</div>
</template>
