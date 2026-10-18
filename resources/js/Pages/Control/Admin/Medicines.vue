<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    fakturStocks: { type: Object, default: () => ({ data: [], total: 0 }) },
    title: { type: String, default: 'Katalog Obat & Sediaan' },
    filters: { type: Object, default: () => ({ search: '' }) }
});

const search = ref(props.filters?.search || '');

const clearSearch = () => {
    search.value = '';
};

// Reactive data bindings (directly from Inertia props)
const activeFakturStocks = computed(() => props.fakturStocks || { data: [], total: 0 });

const activeFilterTab = ref('all'); // 'all' | 'menipis' | 'nearEd'

const isNearEd = (item) => {
    if (!item?.expired_date) return false;
    const exp = new Date(item.expired_date);
    const now = new Date();
    const diffDays = (exp - now) / (1000 * 60 * 60 * 24);
    return diffDays >= 0 && diffDays <= 90;
};

const isMenipis = (item) => {
    const qty = Number(item?.qty_converted) || 0;
    const threshold = Number(item?.medicine?.threshold_min) || 10;
    return qty <= threshold;
};

const stats = computed(() => {
    const list = activeFakturStocks.value?.data || [];
    const total = activeFakturStocks.value?.total ?? list.length;
    const menipis = list.filter(isMenipis).length;
    const nearEd = list.filter(isNearEd).length;
    return { total, menipis, nearEd };
});

const filteredStocks = computed(() => {
    let list = activeFakturStocks.value?.data || [];
    if (activeFilterTab.value === 'menipis') {
        list = list.filter(isMenipis);
    } else if (activeFilterTab.value === 'nearEd') {
        list = list.filter(isNearEd);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(item => 
            item.medicine?.nama_obat?.toLowerCase().includes(q) ||
            item.batch_no?.toLowerCase().includes(q) ||
            item.medicine?.kategori?.toLowerCase().includes(q) ||
            item.medicine?.classification?.toLowerCase().includes(q)
        );
    }
    return list;
});

const formatCurrency = (val) => Number(val) ? 'Rp ' + Number(val).toLocaleString('id-ID') : 'Rp 0';

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
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Tombol Desktop -->
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="activeFilterTab = 'all'"
                            class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                            :class="activeFilterTab === 'all' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Semua Sediaan</span>
                            <span 
                                v-if="stats.total" 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeFilterTab === 'all' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                            >{{ stats.total }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'menipis'"
                            class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                            :class="activeFilterTab === 'menipis' 
                                ? 'bg-amber-500 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <svg class="w-3.5 h-3.5" :class="activeFilterTab === 'menipis' ? 'text-white' : 'text-amber-500'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Stok Menipis</span>
                            <span 
                                v-if="stats.menipis" 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeFilterTab === 'menipis' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'"
                            >{{ stats.menipis }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'nearEd'"
                            class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                            :class="activeFilterTab === 'nearEd' 
                                ? 'bg-rose-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <svg class="w-3.5 h-3.5" :class="activeFilterTab === 'nearEd' ? 'text-white' : 'text-rose-500'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Dekat ED</span>
                            <span 
                                v-if="stats.nearEd" 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeFilterTab === 'nearEd' ? 'bg-white/20 text-white' : 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300'"
                            >{{ stats.nearEd }}</span>
                        </button>
                    </div>

                    <!-- Action Button Desktop -->
                    <Link 
                        :href="route('control.admin.incoming-stocks')"
                        class="hidden sm:inline-flex h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center justify-center gap-1.5 shrink-0 whitespace-nowrap"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Input Faktur</span>
                    </Link>
                </div>

                <!-- Baris 2: Search Input & Mobile Action Button -->
                <div class="flex items-center gap-2 w-full">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Cari obat, batch, kategori..." 
                            class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all"
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button 
                                v-if="search" 
                                type="button" 
                                @click="clearSearch" 
                                class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                                title="Hapus pencarian"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <Link 
                        :href="route('control.admin.incoming-stocks')"
                        class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs flex items-center justify-center gap-1.5 shrink-0 whitespace-nowrap"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Input</span>
                    </Link>
                </div>
            </div>

            <!-- SISI KANAN: 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
                <!-- Card 1: Total Data Faktur -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            TOTAL DATA FAKTUR
                        </span>
                        <div class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="my-1 truncate">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                            {{ activeFakturStocks.total || 0 }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                        Item sediaan faktur masuk
                    </p>
                </div>

                <!-- Card 2: Link ke Katalog Racikan -->
                <Link 
                    :href="route('control.admin.compounded-products')" 
                    class="bg-white dark:bg-card border border-slate-200/80 dark:border-border hover:border-emerald-300 dark:hover:border-emerald-800/60 rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all group cursor-pointer h-full"
                >
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground group-hover:text-emerald-600 transition-colors truncate">
                            PRODUK RACIKAN
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 2v7.31L4.41 19.59A2 2 0 0 0 6 22h12a2 2 0 0 0 1.59-2.41L14 9.31V2"></path>
                                <line x1="8.5" y1="2" x2="15.5" y2="2"></line>
                                <path d="M7 16h10"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="my-1 flex items-center gap-1.5">
                        <span class="text-base sm:text-lg font-bold text-emerald-600 dark:text-emerald-400 group-hover:underline">Buka Racikan</span>
                        <span class="text-lg font-bold text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                        Item racikan aktif apoteker
                    </p>
                </Link>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="w-full space-y-4">
            <!-- TABLE: KATALOG FAKTUR -->
            <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm animate-in fade-in duration-300">
                <Table class="w-full min-w-[960px]">
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="w-12 min-w-12 text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">NO</TableHead>
                            <TableHead class="min-w-[260px] text-left py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Identitas Obat</TableHead>
                            <TableHead class="min-w-[130px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Nomor Batch</TableHead>
                            <TableHead class="min-w-[130px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Exp Date</TableHead>
                            <TableHead class="min-w-[230px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Qty / Jumlah</TableHead>
                            <TableHead class="min-w-[160px] text-right py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Harga Jual</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="!filteredStocks || filteredStocks.length === 0">
                            <TableCell colspan="6" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">
                                {{ activeFilterTab !== 'all' ? 'Tidak ada data sediaan obat yang sesuai dengan filter.' : 'Belum ada data obat dari faktur pembelian.' }}
                            </TableCell>
                        </TableRow>
                        <template v-for="(item, index) in filteredStocks" :key="item.id">
                                <TableRow class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors" :class="expandedRows.includes(item.id) ? 'bg-muted/20' : ''">
                                    <!-- NO -->
                                    <TableCell class="py-2 px-3 text-center align-middle text-xs font-medium text-muted-foreground tabular-nums">
                                        {{ ((activeFakturStocks.current_page || 1) - 1) * (activeFakturStocks.per_page || 15) + index + 1 }}
                                    </TableCell>
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
                                        <span class="text-sm font-medium text-foreground tracking-tight whitespace-nowrap">{{ item.batch_no || '-' }}</span>
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
                                        <span v-else class="text-sm font-medium text-foreground tabular-nums whitespace-nowrap">
                                            {{ item.expired_date ? new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : '-' }}
                                        </span>
                                    </TableCell>
                                    <!-- QTY -->
                                    <TableCell class="py-2 px-3 text-center align-middle">
                                        <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
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
                                    <TableCell colspan="6" class="p-0 border-b border-border">
                                        <div class="w-full bg-slate-50/70 dark:bg-muted/30 p-4 border-y border-slate-200/60 dark:border-border/60">
                                            <div class="w-full bg-card rounded-lg border border-slate-200/80 dark:border-border/80 shadow-2xs overflow-hidden">
                                                <div class="px-4 py-2.5 border-b border-slate-200/60 dark:border-border/60 bg-muted/30 flex items-center justify-between">
                                                    <div class="text-xs font-semibold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                                                        <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        <span>Detail Harga Per Satuan</span>
                                                    </div>
                                                    <span class="text-[11px] text-muted-foreground font-medium">Struktur Multi-Satuan</span>
                                                </div>
                                                
                                                <div class="w-full overflow-x-auto">
                                                    <Table class="w-full min-w-[640px]">
                                                        <TableHeader>
                                                            <TableRow class="h-8 bg-muted/15 hover:bg-muted/15 border-b border-border/60">
                                                                <TableHead class="min-w-[90px] py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-left">Satuan</TableHead>
                                                                <TableHead class="min-w-[140px] py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-center">Isi Konversi</TableHead>
                                                                <TableHead class="min-w-[160px] py-1.5 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-right tabular-nums">HPP</TableHead>
                                                                <TableHead class="min-w-[150px] py-1.5 px-3 text-xs font-semibold text-foreground uppercase tracking-wider whitespace-nowrap text-right tabular-nums">Harga Jual</TableHead>
                                                                <TableHead class="min-w-[100px] py-1.5 px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap text-right">Margin</TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            <template v-for="(priceData, level) in item.prices" :key="level">
                                                                <TableRow v-if="priceData" class="hover:bg-muted/20 transition-colors border-b border-border/30 last:border-0">
                                                                    <TableCell class="py-2 px-3 text-xs align-middle">
                                                                        <span class="font-semibold text-foreground uppercase">{{ priceData.unit }}</span>
                                                                    </TableCell>
                                                                    <TableCell class="py-2 px-3 text-xs text-muted-foreground whitespace-nowrap align-middle text-center">
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
                                                                    <TableCell class="py-2 px-3 text-right whitespace-nowrap tabular-nums align-middle">
                                                                        <span class="text-sm font-semibold text-foreground block">
                                                                            {{ formatCurrency(priceData.hpp) }}
                                                                        </span>
                                                                        <span v-if="priceData.harga_beli !== undefined && priceData.harga_beli !== null" class="text-[11px] text-muted-foreground block mt-0.5">
                                                                            Pre-PPN: {{ formatCurrency(priceData.harga_beli) }}
                                                                        </span>
                                                                    </TableCell>
                                                                    <TableCell class="py-2 px-3 text-right whitespace-nowrap align-middle">
                                                                        <span class="text-sm font-medium text-foreground tabular-nums">
                                                                            {{ priceData.selling > 0 ? formatCurrency(priceData.selling) : 'Rp 0' }}
                                                                        </span>
                                                                        <span v-if="level === 'kecil'" class="text-[10px] text-primary ml-1">(utama)</span>
                                                                    </TableCell>
                                                                    <TableCell class="py-2 px-3 text-right whitespace-nowrap align-middle">
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
                                <template v-for="(link, i) in activeFakturStocks.links" :key="i">
                                    <Link 
                                        v-if="link.url"
                                        :href="link.url"
                                        preserve-state
                                        preserve-scroll
                                        v-html="link.label"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-medium rounded-md transition-colors',
                                            link.active ? 'bg-primary text-primary-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:bg-muted',
                                        ]"
                                    />
                                    <span 
                                        v-else
                                        v-html="link.label"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-medium rounded-md opacity-50 cursor-not-allowed',
                                            'text-muted-foreground'
                                        ]"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- MODAL EDIT PENGATURAN GOLONGAN OBAT -->
            <Teleport to="body">
                <div v-if="editingMedicine" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 animate-in fade-in duration-200">
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
            </Teleport>
    </div>
</template>
