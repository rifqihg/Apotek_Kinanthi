<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    fakturStocks: { type: Object, default: () => ({ data: [], total: 0 }) },
    racikanProducts: { type: Object, default: () => ({ data: [], total: 0 }) },
    title: { type: String, default: 'Katalog Obat & Sediaan' },
    activeTab: { type: String, default: 'faktur' },
    filters: { type: Object, default: () => ({ search: '' }) }
});

const page = usePage();
const searchQuery = ref(props.filters.search || '');
const activeSearch = ref(props.filters.search || '');

// TanStack Vue Query: Fast reactive search with initialData from Inertia
const { data: queryData, isFetching, refetch } = useQuery({
    queryKey: computed(() => ['admin-medicines', props.activeTab, activeSearch.value]),
    queryFn: async () => {
        if (!activeSearch.value.trim()) {
            return props.activeTab === 'faktur' ? props.fakturStocks : props.racikanProducts;
        }
        const endpoint = props.activeTab === 'faktur' 
            ? 'control.admin.medicines.faktur' 
            : 'control.admin.medicines.racikan';

        const res = await axios.get(route(endpoint, { search: activeSearch.value }), {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const fetchedProps = res.data?.props;
        return props.activeTab === 'faktur' 
            ? (fetchedProps?.fakturStocks || props.fakturStocks)
            : (fetchedProps?.racikanProducts || props.racikanProducts);
    },
    initialData: () => props.activeTab === 'faktur' ? props.fakturStocks : props.racikanProducts,
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (previousData) => previousData,
});

const handleSearch = () => {
    activeSearch.value = searchQuery.value;
    refetch();
};

// Reactive data bindings (fallback to Inertia props)
const activeFakturStocks = computed(() => {
    if (props.activeTab !== 'faktur') return props.fakturStocks;
    return queryData.value || props.fakturStocks;
});

const activeRacikanProducts = computed(() => {
    if (props.activeTab !== 'racikan') return props.racikanProducts;
    return queryData.value || props.racikanProducts;
});

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
                            v-model="searchQuery" 
                            @keyup.enter="handleSearch"
                            placeholder="Cari berdasarkan nama obat, batch, atau kategori..." 
                            class="w-full h-10 pl-10 pr-4 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl text-xs sm:text-sm font-medium text-slate-800 dark:text-foreground placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all shadow-2xs"
                        />
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto">
                        <!-- Filter Button -->
                        <button 
                            type="button" 
                            @click="handleSearch"
                            :disabled="isFetching"
                            class="h-10 px-4 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white text-xs font-semibold rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75"
                        >
                            <svg v-if="!isFetching" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <svg v-else class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Filter</span>
                        </button>

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
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-muted/40 border-b border-border">
                                    <th class="px-6 py-3.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Identitas Obat</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Nomor Batch</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Exp Date</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Qty / Jumlah</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="!activeFakturStocks.data || activeFakturStocks.data.length === 0">
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center max-w-md mx-auto">
                                            <div class="w-14 h-14 bg-slate-100 dark:bg-muted rounded-xl flex items-center justify-center text-slate-400 mb-4 border border-slate-200/60 dark:border-border shadow-2xs">
                                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <p class="text-base font-bold text-slate-800 dark:text-foreground">Belum Ada Data Obat Dari Faktur</p>
                                            <p class="text-xs text-slate-500 dark:text-muted-foreground mt-1.5 leading-relaxed">
                                                Katalog obat dari faktur masih kosong. Silakan tambahkan faktur pembelian masuk dari distributor untuk mengisi data obat dan stok.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <template v-for="item in activeFakturStocks.data" :key="item.id">
                                <tr class="transition-colors group" :class="expandedRows.includes(item.id) ? 'bg-muted/30' : 'hover:bg-muted/20'">
                                    <!-- IDENTITAS -->
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-primary/10 rounded-md flex items-center justify-center text-primary font-bold text-xs border border-primary/20 flex-shrink-0">
                                                {{ item.medicine?.nama_obat ? item.medicine.nama_obat.substring(0, 2).toUpperCase() : 'OB' }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-foreground text-sm line-clamp-1" :title="item.medicine?.nama_obat">{{ item.medicine?.nama_obat || 'Obat Tidak Diketahui' }}</span>
                                                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">{{ item.medicine?.kategori || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- BATCH NO -->
                                    <td class="px-6 py-3.5">
                                        <span class="px-2.5 py-0.5 bg-amber-50 text-amber-700 rounded-md text-xs font-medium border border-amber-200 uppercase tracking-wider inline-flex items-center gap-1.5 whitespace-nowrap">
                                            {{ item.batch_no || '-' }}
                                        </span>
                                    </td>
                                    <!-- EXP DATE -->
                                    <td class="px-6 py-3.5">
                                        <span class="text-xs font-semibold whitespace-nowrap" :class="new Date(item.expired_date) < new Date() ? 'text-destructive' : 'text-foreground'">
                                            {{ item.expired_date ? new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : '-' }}
                                        </span>
                                    </td>
                                    <!-- QTY -->
                                    <td class="px-6 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <div v-for="(b, i) in getStockUnits(item)" :key="i"
                                                 class="inline-flex items-center gap-1 px-2 py-0.5 border rounded-md shadow-2xs" :class="b.cls">
                                                <span class="text-xs font-bold">{{ b.qty }}</span>
                                                <span class="text-xs font-medium uppercase opacity-80">{{ b.unit }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- HARGA & PROFIT -->
                                    <td class="px-6 py-3.5 text-right align-top">
                                        <div @click="toggleRow(item.id)" class="group inline-flex flex-col items-end cursor-pointer focus:outline-none w-full select-none justify-end">
                                            <div class="flex items-center justify-end gap-1.5 w-full">
                                                <span class="font-bold text-sm" :class="item.current_selling_price > 0 ? 'text-foreground' : 'text-muted-foreground'">
                                                    {{ item.current_selling_price > 0 ? formatCurrency(item.current_selling_price) : 'Rp 0' }}
                                                </span>
                                                <div class="p-0.5 rounded text-muted-foreground transition-colors" :class="expandedRows.includes(item.id) ? 'bg-primary/10 text-primary' : 'group-hover:bg-muted'">
                                                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="expandedRows.includes(item.id) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                                </div>
                                            </div>
                                            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider mt-0.5 mr-6 whitespace-nowrap">/ {{ item.prices?.kecil?.unit }}</span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- EXPANDED ACCORDION ROW -->
                                <tr v-if="expandedRows.includes(item.id)" class="bg-muted/10">
                                    <td colspan="5" class="p-0 border-b border-border">
                                        <div class="px-6 py-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="text-xs font-semibold text-foreground uppercase tracking-wider flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    Detail Harga Per Satuan
                                                </div>
                                            </div>
                                            
                                            <div class="overflow-hidden rounded-md border border-border bg-card shadow-2xs w-full">
                                                <table class="w-full text-left">
                                                    <thead class="bg-muted/40 border-b border-border">
                                                        <tr>
                                                            <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Satuan</th>
                                                            <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Isi Konversi</th>
                                                            <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">HPP</th>
                                                            <th class="px-4 py-2.5 text-xs font-semibold text-foreground uppercase tracking-wider whitespace-nowrap">Harga Jual</th>
                                                            <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap text-right">Margin</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-border">
                                                        <template v-for="(priceData, level) in item.prices" :key="level">
                                                            <tr v-if="priceData" class="hover:bg-muted/20 transition-colors">
                                                                <td class="px-4 py-2.5">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-xs font-semibold text-foreground uppercase">{{ priceData.unit }}</span>
                                                                        <span class="text-xs text-muted-foreground capitalize">{{ (priceData.unit || '').toLowerCase() }}</span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-2.5 text-xs font-medium text-muted-foreground whitespace-nowrap">
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
                                                                </td>
                                                                <td class="px-4 py-2.5 text-xs text-muted-foreground whitespace-nowrap">{{ formatCurrency(priceData.hpp) }}</td>
                                                                <td class="px-4 py-2.5 whitespace-nowrap">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-sm font-bold text-foreground">
                                                                            {{ priceData.selling > 0 ? formatCurrency(priceData.selling) : 'Rp 0' }}
                                                                        </span>
                                                                        <span v-if="level === 'kecil'" class="text-xs font-medium text-primary mt-0.5">(utama)</span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                                    <span v-if="priceData.selling > 0" 
                                                                          class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-md border whitespace-nowrap"
                                                                          :class="priceData.profit >= 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-destructive bg-destructive/10 border-destructive/20'">
                                                                        {{ priceData.profit > 0 ? '+' : '' }}{{ priceData.profit }}%
                                                                    </span>
                                                                    <span v-else class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium text-muted-foreground bg-muted border border-border rounded-md uppercase">
                                                                        Belum
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <div class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>Harga jual utama yang digunakan di kasir adalah per <strong class="capitalize text-foreground">{{ (item.prices?.kecil?.unit || '').toLowerCase() }}</strong>.</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                        
                        <!-- PAGINATION FAKTUR -->
                        <div v-if="activeFakturStocks.data && activeFakturStocks.data.length > 0" class="px-6 py-3.5 bg-muted/20 border-t border-border flex items-center justify-between">
                            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Menampilkan {{ activeFakturStocks.data.length }} Obat Faktur
                            </span>
                        </div>
                    </div>

                    <!-- TABLE: KATALOG RACIKAN -->
                    <div v-if="activeTab === 'racikan'" class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm animate-in fade-in duration-300">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-purple-50/30 border-b border-border">
                                    <th class="px-6 py-3.5 text-xs font-semibold text-purple-700 uppercase tracking-wider whitespace-nowrap">Identitas Racikan</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-purple-700 uppercase tracking-wider whitespace-nowrap">Tgl Buat & BUD</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-purple-700 uppercase tracking-wider text-center whitespace-nowrap">Qty Hasil</th>
                                    <th class="px-6 py-3.5 text-xs font-semibold text-purple-700 uppercase tracking-wider text-right whitespace-nowrap">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="!activeRacikanProducts.data || activeRacikanProducts.data.length === 0">
                                    <td colspan="4" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center max-w-md mx-auto">
                                            <div class="w-14 h-14 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl flex items-center justify-center text-emerald-500 mb-4 border border-emerald-100 dark:border-emerald-900 shadow-2xs">
                                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                            </div>
                                            <p class="text-base font-bold text-slate-800 dark:text-foreground">Belum Ada Data Formula / Obat Racikan</p>
                                            <p class="text-xs text-slate-500 dark:text-muted-foreground mt-1.5 leading-relaxed">
                                                Katalog produk racikan apoteker masih kosong. Silakan buat sediaan racikan untuk menambahkan katalog.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="item in activeRacikanProducts.data" :key="item.id" class="hover:bg-purple-50/30 transition-colors group">
                                    <!-- IDENTITAS -->
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-purple-50 rounded-md flex items-center justify-center text-purple-600 font-bold text-xs border border-purple-100 flex-shrink-0">
                                                RC
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-foreground text-sm line-clamp-1" :title="item.nama_produk">{{ item.nama_produk }}</span>
                                                <span class="text-xs font-medium text-purple-600 uppercase tracking-wider bg-purple-50 px-2 py-0.5 rounded-md w-fit border border-purple-100">{{ item.satuan || 'Puyer' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- DATES -->
                                    <td class="px-6 py-3.5">
                                        <div class="flex flex-col gap-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-medium text-muted-foreground w-8">Buat</span>
                                                <span class="text-xs font-semibold text-foreground">{{ new Date(item.tanggal_racik).toLocaleDateString('id-ID') }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-medium text-destructive w-8">BUD</span>
                                                <span class="text-xs font-semibold text-destructive">{{ new Date(item.bud_date).toLocaleDateString('id-ID') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- QTY -->
                                    <td class="px-6 py-3.5 text-center">
                                        <div class="inline-flex items-center justify-center min-w-[3rem] px-2.5 py-0.5 bg-muted border border-border rounded-md">
                                            <span class="text-sm font-bold text-foreground">{{ Number(item.qty_hasil) }}</span>
                                        </div>
                                    </td>
                                    <!-- HARGA & PROFIT -->
                                    <td class="px-6 py-3.5 text-right">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <!-- Harga Jual -->
                                            <span class="font-bold text-foreground text-sm whitespace-nowrap">
                                                {{ item.harga_jual > 0 ? formatCurrency(item.harga_jual) : 'Rp 0' }}
                                            </span>
                                            <!-- Profit / Status -->
                                            <div class="flex items-center justify-end gap-1.5">
                                                <span class="text-xs font-medium text-muted-foreground whitespace-nowrap">HPP: {{ formatCurrency(item.hpp_per_unit) }}</span>
                                                <span v-if="item.harga_jual > 0" class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md uppercase tracking-wider border border-emerald-200">
                                                    Profit {{ getRacikanProfit(item.hpp_per_unit, item.harga_jual) }}%
                                                </span>
                                                <span v-else class="text-xs font-medium text-destructive bg-destructive/10 px-2 py-0.5 rounded-md uppercase tracking-wider border border-destructive/20">
                                                    Belum Diset
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- PAGINATION RACIKAN -->
                        <div v-if="activeRacikanProducts.data && activeRacikanProducts.data.length > 0" class="px-6 py-3.5 bg-muted/20 border-t border-border flex items-center justify-between">
                            <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Menampilkan {{ activeRacikanProducts.data.length }} Obat Racikan
                            </span>
                        </div>
                    </div>
            </div>
    </div>
</template>
