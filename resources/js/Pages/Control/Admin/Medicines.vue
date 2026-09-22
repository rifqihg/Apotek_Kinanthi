<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    fakturStocks: { type: Object, default: () => ({ data: [], total: 0 }) },
    racikanProducts: { type: Object, default: () => ({ data: [], total: 0 }) },
    title: { type: String, default: 'Katalog Obat & Sediaan' },
    activeTab: { type: String, default: 'faktur' },
    filters: { type: Object, default: () => ({ search: '' }) }
});

const search = ref(props.filters?.search || '');
const isSearching = ref(false);

// Sync if props.filters.search changes from outside
watch(() => props.filters?.search, (newSearch) => {
    if (newSearch !== undefined && newSearch !== search.value) {
        search.value = newSearch || '';
    }
});

const handleLiveSearch = useDebounceFn((query) => {
    const targetRoute = props.activeTab === 'racikan'
        ? 'control.admin.medicines.racikan'
        : 'control.admin.medicines.faktur';

    isSearching.value = true;
    router.get(route(targetRoute), {
        search: query || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        },
    });
}, 300);

watch(search, (newVal) => {
    handleLiveSearch(newVal);
});

const clearSearch = () => {
    search.value = '';
};

// Reactive data bindings (directly from Inertia props)
const activeFakturStocks = computed(() => props.fakturStocks || { data: [], total: 0 });
const activeRacikanProducts = computed(() => props.racikanProducts || { data: [], total: 0 });

const formatCurrency = (val) => Number(val) ? 'Rp ' + Number(val).toLocaleString('id-ID') : 'Rp 0';

const getRacikanProfit = (hpp, sell) => (hpp > 0 && sell > 0) ? Math.round(((sell - hpp) / hpp) * 100) : 0;

// Kemasan & Multi-unit breakdown helper (single-pass)
const getStockUnits = (item) => {
    let rem = Number(item?.qty_converted) || 0;
    const unit = item?.medicine?.satuan_stok || 'Unit';
    if (rem <= 0) return [{ qty: 0, unit, cls: 'bg-rose-50 border-rose-200 text-rose-600' }];

    const totalBig = (item.mid_to_big_ratio > 0 && item.small_to_mid_ratio > 0)
        ? item.mid_to_big_ratio * item.small_to_mid_ratio
        : (item.unit_conversion_factor || 1);

    const res = [];
    if (item.satuan_input && totalBig > 1 && rem >= totalBig) {
        res.push({ qty: Math.floor(rem / totalBig), unit: item.satuan_input, cls: 'bg-slate-100 border-slate-200 text-slate-800' });
        rem %= totalBig;
    }
    if (item.mid_unit && item.small_to_mid_ratio > 1 && rem >= item.small_to_mid_ratio) {
        res.push({ qty: Math.floor(rem / item.small_to_mid_ratio), unit: item.mid_unit, cls: 'bg-indigo-50 border-indigo-100 text-indigo-800' });
        rem %= item.small_to_mid_ratio;
    }
    if (rem > 0) {
        res.push({ qty: Number(rem.toFixed(2)), unit, cls: 'bg-emerald-50 border-emerald-100 text-emerald-800' });
    }
    return res.length ? res : [{ qty: 0, unit, cls: 'bg-rose-50 border-rose-200 text-rose-600' }];
};

// State for expanding accordion rows
const expandedRows = ref([]);
const toggleRow = (id) => {
    const idx = expandedRows.value.indexOf(id);
    idx > -1 ? expandedRows.value.splice(idx, 1) : expandedRows.value.push(id);
};

// State for editing medicine classification & threshold
const editingMedicine = ref(null);
const editForm = useForm({
    classification: 'obat_keras',
    threshold_min: 10,
    deskripsi: '',
});

const openEditModal = (medicine) => {
    editingMedicine.value = medicine;
    editForm.classification = medicine.classification || 'obat_keras';
    editForm.threshold_min = medicine.threshold_min ?? 10;
    editForm.deskripsi = medicine.deskripsi || '';
};

const closeEditModal = () => {
    editingMedicine.value = null;
    editForm.reset();
};

const submitEditMedicine = () => {
    if (!editingMedicine.value) return;
    editForm.put(route('control.admin.medicines.update', editingMedicine.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
};
</script>

<template>
    <Head :title="title" />

    <div class="space-y-6 animate-in fade-in duration-700">
            <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
                <!-- HERO BANNER CARD (lg:col-span-8: diperlebar dan dipertinggi proporsional) -->
                <div 
                    class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300"
                >
                    <!-- Left Text Column -->
                    <div class="relative z-10 max-w-sm sm:max-w-md">
                        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                            {{ activeTab === 'faktur' ? 'Katalog Obat dari Faktur' : 'Katalog Formula & Obat Racikan' }}
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                            {{ activeTab === 'faktur' 
                                ? 'Manajemen dan pemantauan menyeluruh data sediaan obat dari faktur pembelian masuk secara terpusat.' 
                                : 'Manajemen master formula peracikan, takaran bahan baku aktif, dan standarisasi sediaan racikan apotek.' 
                            }}
                        </p>
                    </div>

                    <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                    <div class="relative z-10 flex items-center justify-center shrink-0">
                        <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                            <!-- SVG FAKTUR (Botol Obat Etiket Presisi + Kapsul Interlocking) -->
                            <svg v-if="activeTab === 'faktur'" class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="10" y="4" width="16" height="4.5" rx="2" stroke="#2563EB" stroke-width="2" fill="#EFF6FF" />
                                <rect x="14" y="8.5" width="8" height="3" stroke="#2563EB" stroke-width="1.6" fill="#FFFFFF" />
                                <rect x="4" y="11.5" width="28" height="40" rx="5" stroke="#2563EB" stroke-width="2.2" fill="#FFFFFF" />
                                <rect x="8" y="20" width="20" height="24" rx="3" stroke="#2563EB" stroke-width="1.5" fill="#EFF6FF" />
                                <line x1="12" y1="26" x2="24" y2="26" stroke="#2563EB" stroke-width="1.8" stroke-linecap="round" />
                                <line x1="12" y1="32" x2="22" y2="32" stroke="#2563EB" stroke-opacity="0.5" stroke-width="1.5" stroke-linecap="round" />
                                <line x1="12" y1="37" x2="19" y2="37" stroke="#2563EB" stroke-opacity="0.5" stroke-width="1.5" stroke-linecap="round" />
                                <g transform="translate(37, 33) rotate(28)">
                                    <rect x="-7.5" y="-18" width="15" height="36" rx="7.5" stroke="#2563EB" stroke-width="2.2" fill="#FFFFFF" />
                                    <path d="M-7.5 -10.5 A7.5 7.5 0 0 1 7.5 -10.5 V0 H-7.5 Z" fill="#BFDBFE" />
                                    <line x1="-7.5" y1="0" x2="7.5" y2="0" stroke="#2563EB" stroke-width="1.8" />
                                </g>
                            </svg>

                            <!-- SVG RACIKAN (Lab Flask Erlenmeyer + Kapsul Interlocking) -->
                            <svg v-else class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="12" y="4" width="14" height="4.5" rx="1.8" stroke="#059669" stroke-width="2" fill="#ECFDF5" />
                                <path d="M15 8.5v11L5 46.5a4 4 0 0 0 3.5 5.5h23a4 4 0 0 0 3.5-5.5L25 19.5V8.5" stroke="#059669" stroke-width="2.2" stroke-linejoin="round" fill="#FFFFFF" />
                                <path d="M8 39c3-1.5 6 1.5 10 0s6-.5 10 1l2 6.5a3 3 0 0 1-2.5 4H10a3 3 0 0 1-2.8-4l.8-7.5z" fill="#A7F3D0" fill-opacity="0.9" />
                                <line x1="16" y1="26" x2="20" y2="26" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                                <line x1="14" y1="31" x2="20" y2="31" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                                <g transform="translate(37, 33) rotate(28)">
                                    <rect x="-7.5" y="-18" width="15" height="36" rx="7.5" stroke="#059669" stroke-width="2.2" fill="#FFFFFF" />
                                    <path d="M-7.5 -10.5 A7.5 7.5 0 0 1 7.5 -10.5 V0 H-7.5 Z" fill="#10B981" />
                                    <line x1="-7.5" y1="0" x2="7.5" y2="0" stroke="#059669" stroke-width="1.8" />
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 2 STAT CARDS (lg:col-span-4: diperkecil lebarnya, tinggi mengikuti hero banner) -->
                <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                    <!-- Card 1: Total Data Faktur -->
                    <Link 
                        :href="route('control.admin.medicines.faktur')" 
                        class="bg-white dark:bg-card border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all group cursor-pointer h-full"
                        :class="activeTab === 'faktur' 
                            ? 'border-blue-400/90 ring-2 ring-blue-500/10' 
                            : 'border-slate-200/80 dark:border-border hover:border-slate-300 dark:hover:border-slate-700'"
                    >
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground group-hover:text-blue-600 transition-colors truncate">
                                TOTAL DATA FAKTUR
                            </span>
                            <div class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-950/50 border border-blue-100 dark:border-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="my-0.5">
                            <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                                {{ activeFakturStocks.total || 0 }}
                            </span>
                        </div>
                        <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground leading-tight truncate">
                            Item sediaan faktur masuk
                        </p>
                    </Link>

                    <!-- Card 2: Total Data Racikan -->
                    <Link 
                        :href="route('control.admin.medicines.racikan')" 
                        class="bg-white dark:bg-card border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all group cursor-pointer h-full"
                        :class="activeTab === 'racikan' 
                            ? 'border-emerald-400/90 ring-2 ring-emerald-500/10' 
                            : 'border-slate-200/80 dark:border-border hover:border-slate-300 dark:hover:border-slate-700'"
                    >
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground group-hover:text-emerald-600 transition-colors truncate">
                                TOTAL DATA RACIKAN
                            </span>
                            <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 2v7.31L4.41 19.59A2 2 0 0 0 6 22h12a2 2 0 0 0 1.59-2.41L14 9.31V2"></path>
                                    <line x1="8.5" y1="2" x2="15.5" y2="2"></line>
                                    <path d="M7 16h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="my-0.5">
                            <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                                {{ activeRacikanProducts.total || 0 }}
                            </span>
                        </div>
                        <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground leading-tight truncate">
                            Item racikan aktif apoteker
                        </p>
                    </Link>
                </div>
            </div>

            <!-- CONTENT AREA -->
            <div class="w-full space-y-4">
                <!-- SEARCH & ACTION BAR -->
                <div class="flex flex-col sm:flex-row items-center gap-3 w-full">
                    <div class="relative flex-1 w-full">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Cari berdasarkan nama obat, batch, atau kategori..." 
                            class="w-full h-10 pl-10 pr-10 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl text-xs sm:text-sm font-medium text-slate-800 dark:text-foreground placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all shadow-2xs"
                        />
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                            <svg v-if="isSearching" class="animate-spin h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <button 
                                v-else-if="search" 
                                type="button" 
                                @click="clearSearch" 
                                class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-full hover:bg-slate-100 dark:hover:bg-muted transition-colors cursor-pointer"
                                title="Hapus pencarian"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto">
                        <!-- + Tambah Button -->
                        <Link 
                            v-if="activeTab === 'faktur'"
                            :href="route('control.admin.incoming-stocks')"
                            class="h-10 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Faktur</span>
                        </Link>

                        <Link 
                            v-else
                            :href="route('control.admin.compounded-products')"
                            class="h-10 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Racikan</span>
                        </Link>
                    </div>
                </div>

                    <!-- TABLE: KATALOG FAKTUR -->
                    <div v-if="activeTab === 'faktur'" class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm animate-in fade-in duration-300">
                        <Table>
                            <TableHeader>
                                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Identitas Obat</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-center">Nomor Batch</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-center">Exp Date</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Qty / Jumlah</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Harga Jual</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="!activeFakturStocks.data || activeFakturStocks.data.length === 0">
                                    <TableCell colspan="5" class="px-6 py-12 text-center text-muted-foreground text-sm">
                                        Belum ada data obat dari faktur pembelian.
                                    </TableCell>
                                </TableRow>
                                <template v-for="item in activeFakturStocks.data" :key="item.id">
                                <TableRow class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors" :class="expandedRows.includes(item.id) ? 'bg-muted/20' : ''">
                                    <!-- IDENTITAS -->
                                    <TableCell class="py-2 px-3 align-middle">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-medium text-foreground text-sm leading-tight line-clamp-1" :title="item.medicine?.nama_obat">{{ item.medicine?.nama_obat || 'Obat Tidak Diketahui' }}</span>
                                                <span v-if="item.medicine?.classification" class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider shrink-0" :class="{
                                                    'bg-red-100 text-red-800 border border-red-300': item.medicine.classification === 'narkotika',
                                                    'bg-purple-100 text-purple-800 border border-purple-300': item.medicine.classification === 'psikotropika',
                                                    'bg-blue-100 text-blue-800 border border-blue-300': item.medicine.classification === 'prekursor',
                                                    'bg-rose-50 text-rose-700 border border-rose-200': item.medicine.classification === 'obat_keras',
                                                    'bg-amber-50 text-amber-700 border border-amber-200': item.medicine.classification === 'bebas_terbatas',
                                                    'bg-emerald-50 text-emerald-700 border border-emerald-200': item.medicine.classification === 'bebas'
                                                }">
                                                    {{ item.medicine.classification.replace('_', ' ') }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-muted-foreground leading-tight mt-1">
                                                <span>{{ item.medicine?.kategori || '-' }}</span>
                                                <button v-if="item.medicine" @click="openEditModal(item.medicine)" class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-0.5 font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                    Ubah Golongan
                                                </button>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <!-- BATCH NO -->
                                    <TableCell class="py-2 px-3 text-center align-middle">
                                        <span class="font-mono text-sm font-normal text-foreground tracking-tight whitespace-nowrap">{{ item.batch_no || '-' }}</span>
                                    </TableCell>
                                    <!-- EXP DATE -->
                                    <TableCell class="py-2 px-3 text-center align-middle">
                                        <span 
                                            v-if="new Date(item.expired_date) < new Date()"
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20 whitespace-nowrap"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                            {{ item.expired_date ? new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : '-' }}
                                        </span>
                                        <span v-else class="text-xs font-mono text-muted-foreground tabular-nums whitespace-nowrap">
                                            {{ item.expired_date ? new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : '-' }}
                                        </span>
                                    </TableCell>
                                    <!-- QTY -->
                                    <TableCell class="py-2 px-3 text-center align-middle">
                                        <div class="flex flex-wrap items-center justify-center gap-1.5">
                                            <template v-for="(b, i) in getStockUnits(item)" :key="i">
                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                                    <span class="font-semibold tabular-nums text-foreground">{{ b.qty }}</span>
                                                    <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ b.unit }}</span>
                                                </div>
                                            </template>
                                            <div v-if="!getStockUnits(item)?.length" class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-destructive/30 bg-destructive/10 text-xs font-medium text-destructive">
                                                <span class="font-semibold tabular-nums">0</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <!-- HARGA & PROFIT -->
                                    <TableCell class="py-2 px-3 text-right align-middle">
                                        <div @click="toggleRow(item.id)" class="group inline-flex items-center justify-end gap-1.5 cursor-pointer select-none">
                                            <span class="text-sm font-medium tabular-nums text-foreground">
                                                {{ item.current_selling_price > 0 ? formatCurrency(item.current_selling_price) : 'Rp 0' }}
                                            </span>
                                            <span class="text-xs text-muted-foreground">/{{ item.prices?.kecil?.unit }}</span>
                                            <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200" :class="expandedRows.includes(item.id) ? 'rotate-180 text-primary' : 'group-hover:text-foreground'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <!-- EXPANDED ACCORDION ROW -->
                                <TableRow v-if="expandedRows.includes(item.id)" class="bg-muted/10">
                                    <TableCell colspan="5" class="p-0 border-b border-border">
                                        <div class="px-5 py-3">
                                            <div class="text-xs font-semibold text-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Detail Harga Per Satuan
                                            </div>
                                            
                                            <div class="overflow-hidden rounded-md border border-border bg-card w-full">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow class="h-8 bg-muted/40 hover:bg-muted/40 border-b border-border">
                                                            <TableHead class="py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Satuan</TableHead>
                                                            <TableHead class="py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Isi Konversi</TableHead>
                                                            <TableHead class="py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-right">HPP</TableHead>
                                                            <TableHead class="py-1.5 px-3 text-xs font-semibold text-foreground uppercase tracking-wider whitespace-nowrap text-right">Harga Jual</TableHead>
                                                            <TableHead class="py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-right">Margin</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        <template v-for="(priceData, level) in item.prices" :key="level">
                                                            <TableRow v-if="priceData" class="h-8 hover:bg-muted/20 transition-colors border-b border-border/30 last:border-0">
                                                                <TableCell class="py-1.5 px-3 text-xs align-middle">
                                                                    <span class="font-medium text-foreground uppercase">{{ priceData.unit }}</span>
                                                                </TableCell>
                                                                <TableCell class="py-1.5 px-3 text-xs text-muted-foreground whitespace-nowrap align-middle">
                                                                    <template v-if="level === 'besar' && item.mid_unit">
                                                                        1 {{ priceData.unit }} = {{ item.mid_to_big_ratio }} {{ item.mid_unit }}
                                                                    </template>
                                                                    <template v-else-if="level === 'besar' && !item.mid_unit">
                                                                        1 {{ priceData.unit }} = {{ item.unit_conversion_factor }} {{ item.medicine?.satuan_stok }}
                                                                    </template>
                                                                    <template v-else-if="level === 'menengah'">
                                                                        1 {{ priceData.unit }} = {{ item.small_to_mid_ratio }} {{ item.medicine?.satuan_stok }}
                                                                    </template>
                                                                    <template v-else>
                                                                        -
                                                                    </template>
                                                                </TableCell>
                                                                <TableCell class="py-1.5 px-3 text-xs text-muted-foreground text-right whitespace-nowrap tabular-nums align-middle">{{ formatCurrency(priceData.hpp) }}</TableCell>
                                                                <TableCell class="py-1.5 px-3 text-right whitespace-nowrap align-middle">
                                                                    <span class="text-sm font-medium text-foreground tabular-nums">
                                                                        {{ priceData.selling > 0 ? formatCurrency(priceData.selling) : 'Rp 0' }}
                                                                    </span>
                                                                    <span v-if="level === 'kecil'" class="text-[10px] text-primary ml-1">(utama)</span>
                                                                </TableCell>
                                                                <TableCell class="py-1.5 px-3 text-right whitespace-nowrap align-middle">
                                                                    <span v-if="priceData.selling > 0" 
                                                                          class="text-xs font-medium tabular-nums"
                                                                          :class="priceData.profit >= 0 ? 'text-emerald-600' : 'text-destructive'">
                                                                        {{ priceData.profit > 0 ? '+' : '' }}{{ priceData.profit }}%
                                                                    </span>
                                                                    <span v-else class="text-xs text-muted-foreground">
                                                                        Belum
                                                                    </span>
                                                                </TableCell>
                                                            </TableRow>
                                                        </template>
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        
                        <!-- PAGINATION FAKTUR -->
                        <div v-if="activeFakturStocks.data && activeFakturStocks.data.length > 0" class="px-4 py-2.5 bg-muted/20 border-t border-border flex items-center justify-between">
                            <span class="text-xs font-medium text-muted-foreground">
                                Menampilkan {{ activeFakturStocks.data.length }} Obat Faktur
                            </span>
                            <div v-if="activeFakturStocks.links && activeFakturStocks.links.length > 3" class="flex items-center gap-1">
                                <button v-for="(link, i) in activeFakturStocks.links" :key="i"
                                    type="button"
                                    @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
                                    v-html="link.label"
                                    :class="[
                                        'px-2.5 py-1 text-xs font-medium rounded-md transition-colors',
                                        link.active ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:bg-muted',
                                        !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- TABLE: KATALOG RACIKAN -->
                    <div v-if="activeTab === 'racikan'" class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm animate-in fade-in duration-300">
                        <Table>
                            <TableHeader>
                                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Identitas Racikan</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Tgl Buat & BUD</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Qty Hasil</TableHead>
                                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Harga Jual</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="!activeRacikanProducts.data || activeRacikanProducts.data.length === 0">
                                    <TableCell colspan="4" class="px-6 py-12 text-center text-muted-foreground text-sm">
                                        Belum ada data racikan terdaftar.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="item in activeRacikanProducts.data" :key="item.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                                    <!-- IDENTITAS -->
                                    <TableCell class="py-2 px-3 align-middle">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-foreground text-sm leading-tight line-clamp-1" :title="item.nama_produk">{{ item.nama_produk }}</span>
                                            <span class="text-xs text-muted-foreground leading-tight mt-0.5">{{ item.satuan || 'Puyer' }}</span>
                                        </div>
                                    </TableCell>
                                    <!-- DATES -->
                                    <TableCell class="py-2 px-3 align-middle">
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="text-muted-foreground">Buat: <span class="font-mono text-foreground tabular-nums">{{ new Date(item.tanggal_racik).toLocaleDateString('id-ID') }}</span></span>
                                            <span>·</span>
                                            <span class="text-destructive font-medium">BUD: <span class="font-mono tabular-nums">{{ new Date(item.bud_date).toLocaleDateString('id-ID') }}</span></span>
                                        </div>
                                    </TableCell>
                                    <!-- QTY -->
                                    <TableCell class="py-2 px-3 text-center align-middle">
                                        <span class="text-sm font-semibold text-foreground tabular-nums">{{ Number(item.qty_hasil) }}</span>
                                        <span class="text-xs text-muted-foreground ml-1">{{ item.satuan || 'Unit' }}</span>
                                    </TableCell>
                                    <!-- HARGA & PROFIT -->
                                    <TableCell class="py-2 px-3 text-right align-middle">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="text-sm font-medium text-foreground tabular-nums">
                                                {{ item.harga_jual > 0 ? formatCurrency(item.harga_jual) : 'Rp 0' }}
                                            </span>
                                            <span v-if="item.harga_jual > 0" class="text-xs font-medium text-emerald-600 tabular-nums">
                                                (+{{ getRacikanProfit(item.hpp_per_unit, item.harga_jual) }}%)
                                            </span>
                                            <span v-else class="text-xs text-muted-foreground">
                                                (Belum set)
                                            </span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- PAGINATION RACIKAN -->
                        <div v-if="activeRacikanProducts.data && activeRacikanProducts.data.length > 0" class="px-6 py-3.5 bg-muted/20 border-t border-border flex items-center justify-between">
                            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Menampilkan {{ activeRacikanProducts.data.length }} Obat Racikan
                            </span>
                            <div v-if="activeRacikanProducts.links && activeRacikanProducts.links.length > 3" class="flex items-center gap-1">
                                <button v-for="(link, i) in activeRacikanProducts.links" :key="i"
                                    type="button"
                                    @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
                                    v-html="link.label"
                                    :class="[
                                        'px-2.5 py-1 text-xs font-medium rounded-md transition-colors',
                                        link.active ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:bg-muted',
                                        !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
            </div>

            <!-- MODAL EDIT PENGATURAN GOLONGAN OBAT -->
            <div v-if="editingMedicine" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 animate-in fade-in duration-200">
                <div class="bg-card border border-border rounded-xl p-6 shadow-xl max-w-md w-full space-y-4">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="font-bold text-sm text-foreground">Pengaturan Master Obat</h3>
                        <button @click="closeEditModal" class="text-muted-foreground hover:text-foreground text-lg leading-none">&times;</button>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Obat</p>
                        <p class="font-semibold text-sm text-foreground mt-0.5">{{ editingMedicine.nama_obat }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Klasifikasi Golongan Obat (BPOM / SIPNAP)</label>
                        <select v-model="editForm.classification" class="w-full h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary">
                            <option value="obat_keras">Obat Keras (Lingkaran Merah K)</option>
                            <option value="narkotika">Narkotika (Palang Merah - Wajib SIPNAP)</option>
                            <option value="psikotropika">Psikotropika (Lingkaran Biru K - Wajib SIPNAP)</option>
                            <option value="prekursor">Prekursor Farmasi</option>
                            <option value="bebas_terbatas">Obat Bebas Terbatas (Lingkaran Biru)</option>
                            <option value="bebas">Obat Bebas (Lingkaran Hijau)</option>
                        </select>
                        <p class="text-[11px] text-muted-foreground mt-1">Obat bertipe Narkotika/Psikotropika mewajibkan input No. SIP Dokter pada resep dan tercatat di mutasi SIPNAP.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Batas Minimum Stok (Threshold Alert)</label>
                        <input type="number" v-model="editForm.threshold_min" min="0" class="w-full h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Deskripsi / Catatan Khusus</label>
                        <textarea v-model="editForm.deskripsi" rows="2" class="w-full rounded-md border border-input bg-background p-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Catatan penyimpanan, suhu, atau regulasi..."></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="closeEditModal" class="px-3.5 py-1.5 rounded-md border border-border text-xs font-medium hover:bg-muted text-foreground transition-colors">
                            Batal
                        </button>
                        <button type="button" @click="submitEditMedicine" :disabled="editForm.processing" class="px-4 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs disabled:opacity-50 transition-colors">
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </div>
            </div>
    </div>
</template>
