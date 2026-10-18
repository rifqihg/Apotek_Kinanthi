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
        showProgress: false,
        only: ['logs', 'kpi', 'filters'],
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
    if (l.reference_type === 'App\\Models\\Control\\CompoundingSubmission') return 'Racik';
    if (l.reference_type === 'App\\Models\\Control\\Sale') return 'Terjual';
    if ((l.note && l.note.toLowerCase().includes('distribusi')) || (l.type && l.type.includes('Distribusi'))) return 'Distribusi';
    if (l.type === 'Masuk' || l.type === 'Penyesuaian Masuk') return 'Masuk';
    if (l.reference_type === 'App\\Models\\Control\\ReturnForm' || l.type === 'Retur') return 'Retur';
    const t = l.type ? l.type.toLowerCase() : '';
    if (t === 'keluar' || t === 'penjualan' || t === 'penyesuaian keluar') return 'Terjual';
    return l.type;
};

const getLogTypeClass = (type) => ({
    Masuk: 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800/40',
    Retur: 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800/40',
    Terjual: 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800/40',
    Distribusi: 'bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-800/40',
    Racik: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100 dark:bg-fuchsia-950/30 dark:text-fuchsia-400 dark:border-fuchsia-800/40',
}[type] || 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900/40 dark:text-slate-400 dark:border-slate-800');

// Format Referensi
const refTitles = { ReturnForm: 'Retur Pembelian', Sale: 'Penjualan (Kasir)', StockOpname: 'Stock Opname', IncomingStock: 'Faktur Masuk', CompoundingSubmission: 'Peracikan' };
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
        showProgress: false,
        only: ['audits', 'filters'],
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
    <!-- BENTO COCKPIT HEADER -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between gap-2.5">
            <!-- Baris 1: Filter Tabs (Pills) -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    <button
                        type="button"
                        @click="switchTab('mutasi')"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                        :class="activeTab === 'mutasi' 
                            ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                        <span>Mutasi Fisik Stok</span>
                        <span 
                            v-if="activeKpi.total" 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'mutasi' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ activeKpi.total }}</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('audit')"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                        :class="activeTab === 'audit' 
                            ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Jejak Aktivitas Sistem</span>
                        <span 
                            v-if="activeAudits.total" 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'audit' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ activeAudits.total }}</span>
                    </button>
                </div>

                <div class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium text-slate-500 dark:text-muted-foreground bg-slate-100/80 dark:bg-muted/60 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full" :class="activeTab === 'mutasi' ? 'bg-emerald-500' : 'bg-blue-500'"></span>
                    <span>{{ activeTab === 'mutasi' ? 'Log Ledger Fisik' : 'Audit Trail RBAC' }}</span>
                </div>
            </div>

            <!-- Baris 2: Search Input & Type/Role Selector -->
            <div class="flex items-center gap-2 w-full">
                <div class="relative flex-1">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-if="activeTab === 'mutasi'"
                        v-model="search" 
                        type="text" 
                        placeholder="Cari obat, batch, jenis mutasi..." 
                        class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                    />
                    <input 
                        v-else
                        v-model="auditSearch" 
                        type="text" 
                        placeholder="Cari aktivitas, tindakan, user pengubah..." 
                        class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                    />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                        <button 
                            v-if="activeTab === 'mutasi' ? search : auditSearch" 
                            type="button" 
                            @click="activeTab === 'mutasi' ? (search = '', applyMutationFilters()) : (auditSearch = '', applyAuditFilters())" 
                            class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <select 
                    v-if="activeTab === 'mutasi'"
                    v-model="filterType" 
                    class="h-9 pl-3 pr-8 min-w-[120px] bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all cursor-pointer shrink-0"
                >
                    <option value="all">Semua Log</option>
                    <option value="Masuk">Masuk</option>
                    <option value="Retur">Retur</option>
                    <option value="Jual">Terjual</option>
                    <option value="Distribusi">Distribusi</option>
                </select>
                <select 
                    v-else
                    v-model="roleFilter" 
                    class="h-9 pl-3 pr-8 min-w-[120px] bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all cursor-pointer shrink-0"
                >
                    <option value="">Semua Role</option>
                    <option value="admin">Admin</option>
                    <option value="apoteker">Apoteker</option>
                    <option value="kasir">Kasir</option>
                </select>
            </div>
        </div>

        <!-- SISI KANAN: 2 METRIK KARTU BERDAMPINGAN (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
            <!-- Card 1: Arus Stok Masuk -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        ARUS MASUK
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ activeKpi.in || 0 }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Kali</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Faktur & penyesuaian masuk
                </p>
            </div>

            <!-- Card 2: Arus Stok Keluar -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        ARUS KELUAR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 8v12m0 0l4-4m-4 4l-4-4M7 16V4m0 0L3 8m4-4l4 4" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                        {{ activeKpi.out || 0 }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Kali</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Penjualan kasir & racikan
                </p>
            </div>
        </div>
    </div>

    <!-- KOTAK BARU: PERIODE TANGGAL & FILTER MUTASI TERKONTROL (MEMANJANG KESAMPING) -->
    <div v-if="activeTab === 'mutasi'" class="w-full bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-2.5 sm:px-3.5 sm:py-2.5 shadow-2xs flex flex-wrap items-center justify-between gap-2.5">
        <!-- Filter Tanggal & Reset -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <span class="text-xs font-semibold text-slate-500 dark:text-muted-foreground shrink-0 hidden sm:inline">Periode:</span>
            <div class="flex items-center gap-1.5">
                <input 
                    v-model="startDate" 
                    type="date" 
                    class="h-8.5 min-w-[125px] sm:w-36 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all cursor-pointer" 
                />
                <span class="text-muted-foreground text-xs font-medium">s/d</span>
                <input 
                    v-model="endDate" 
                    type="date" 
                    class="h-8.5 min-w-[125px] sm:w-36 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all cursor-pointer" 
                />
            </div>
            <button 
                v-if="search || startDate || endDate || filterType !== 'all' || isControlled" 
                @click="clearFilters" 
                type="button" 
                class="h-8.5 px-2.5 text-xs font-medium text-destructive hover:bg-destructive/10 rounded-lg transition-colors cursor-pointer shrink-0"
            >
                Reset Filter
            </button>
        </div>

        <!-- Tombol Filter Mutasi Terkontrol -->
        <button 
            @click="isControlled = !isControlled" 
            type="button" 
            class="h-8.5 px-3 text-xs font-medium rounded-lg transition-all flex items-center gap-1.5 border cursor-pointer shrink-0"
            :class="isControlled ? 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-800/40 shadow-xs font-semibold' : 'bg-slate-50 dark:bg-background text-muted-foreground border-slate-200 dark:border-input hover:bg-slate-100 dark:hover:bg-muted'"
        >
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <span class="sm:hidden">Terkontrol</span>
            <span class="hidden sm:inline">Filter Mutasi Terkontrol</span>
        </button>
    </div>
    <div v-else class="w-full bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-2.5 sm:px-3.5 sm:py-2.5 shadow-2xs flex items-center justify-between gap-2.5 text-xs text-muted-foreground font-medium">
        <span>Total Catatan Aktivitas: <strong class="text-foreground">{{ activeAudits.total || 0 }}</strong> entri</span>
        <button v-if="auditSearch || roleFilter" @click="clearAuditFilters" type="button" class="text-xs text-destructive hover:underline cursor-pointer">
            Reset Filter
        </button>
    </div>

    <!-- ═══ TAB 1: MUTASI FISIK STOK ═══ -->
    <div v-show="activeTab === 'mutasi'" class="space-y-4">

        <!-- Table -->
        <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="min-w-[130px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Waktu</TableHead>
                            <TableHead class="min-w-[90px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Jenis</TableHead>
                            <TableHead class="min-w-[220px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Item (Obat / Bahan)</TableHead>
                            <TableHead class="min-w-[110px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Batch</TableHead>
                            <TableHead class="min-w-[130px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Referensi</TableHead>
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
                                <div class="flex flex-col min-w-0">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="text-sm font-medium text-foreground leading-tight truncate">
                                            {{ l.inventory?.medicine?.nama_obat || l.rawMaterialStock?.rawMaterial?.nama_bahan || l.item?.nama_obat || l.item?.nama_bahan || l.item?.nama_produk || '-' }}
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
                                        {{ l.inventory?.medicine ? 'Obat Jadi' : (l.rawMaterialStock ? 'Bahan Baku Racikan' : (l.item?.nama_produk ? 'Produk Racikan' : 'Item')) }}
                                    </span>
                                </div>
                            </TableCell>
                            <!-- Batch -->
                            <TableCell class="py-2.5 px-3 align-middle text-center text-sm font-medium text-foreground tracking-tight truncate">
                                {{ l.inventory?.batch_no || l.rawMaterialStock?.batch_no || l.item?.batch_no || l.item?.kode_bahan || l.rawMaterialStock?.rawMaterial?.kode_bahan || '-' }}
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
                                <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1.5">
                                    <template v-for="(b, i) in getMutationUnits(l.quantity, l.inventory)" :key="i">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                            <span class="font-semibold tabular-nums" :class="l.type === 'Masuk' || l.type === 'Penyesuaian Masuk' ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">{{ b.qty }}</span>
                                            <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ b.unit }}</span>
                                        </div>
                                    </template>
                                </div>
                                <!-- Base Unit Display for Raw Materials -->
                                <div v-else class="flex items-center justify-center gap-1.5">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                        <span class="font-semibold tabular-nums" :class="l.type === 'Masuk' || l.type === 'Penyesuaian Masuk' ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">
                                            {{ Number(l.quantity) }}
                                        </span>
                                        <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ l.rawMaterialStock?.rawMaterial?.satuan_dasar_penggunaan || l.rawMaterialStock?.rawMaterial?.satuan || l.item?.satuan_dasar_penggunaan || l.item?.satuan || '' }}</span>
                                    </div>
                                </div>
                            </TableCell>
                            <!-- Sisa Stok -->
                            <TableCell class="py-2.5 px-3 align-middle text-center tabular-nums">
                                <!-- Hierarchical Multi-unit display for Medicine -->
                                <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1.5">
                                    <template v-for="(b, i) in getMutationUnits(l.balance_after, l.inventory)" :key="i">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                            <span class="font-semibold tabular-nums text-foreground">{{ b.qty }}</span>
                                            <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ b.unit }}</span>
                                        </div>
                                    </template>
                                </div>
                                <!-- Base Unit Display -->
                                <div v-else class="flex items-center justify-center gap-1.5">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                        <span class="font-semibold text-foreground tabular-nums">{{ Number(l.balance_after) }}</span>
                                        <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ l.rawMaterialStock?.rawMaterial?.satuan_dasar_penggunaan || l.rawMaterialStock?.rawMaterial?.satuan || l.item?.satuan_dasar_penggunaan || l.item?.satuan || '' }}</span>
                                    </div>
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

        <!-- Audit Table -->
        <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Waktu</TableHead>
                            <TableHead class="py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Pengguna</TableHead>
                            <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Tindakan</TableHead>
                            <TableHead class="py-2 px-3 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">Deskripsi Aktivitas</TableHead>
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
