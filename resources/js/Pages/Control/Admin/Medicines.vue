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
            <!-- COMPACT PREMIUM HERO HEADER -->
            <div class="relative p-6 bg-card rounded-xl border border-border shadow-sm overflow-hidden group">
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-32 -mt-32 transition-transform group-hover:scale-110 duration-1000"></div>
                
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 bg-primary/10 text-primary text-xs font-semibold uppercase tracking-wider rounded-md border border-primary/20">Master Data</span>
                            <span class="px-2.5 py-0.5 bg-muted text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">Katalog Terpusat</span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-foreground tracking-tight leading-none uppercase">Katalog Obat Jadi</h1>
                            <p class="text-muted-foreground font-medium mt-1.5 max-w-xl text-sm">Manajemen sediaan dari faktur masuk dan hasil racikan apoteker.</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <Link :href="route('control.admin.medicines.faktur')" 
                              class="p-4 rounded-lg shadow-sm flex items-center gap-4 transition-all"
                              :class="activeTab === 'faktur' ? 'bg-primary/5 border-2 border-primary' : 'bg-card border border-border hover:bg-muted/50'">
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase tracking-wider leading-none" :class="activeTab === 'faktur' ? 'text-primary' : 'text-muted-foreground'">Total Faktur</p>
                                <p class="text-2xl font-semibold tracking-tight text-primary leading-none mt-1.5">{{ activeFakturStocks.total || 0 }}</p>
                            </div>
                            <div class="w-10 h-10 bg-primary/10 rounded-md flex items-center justify-center text-lg border border-primary/20 text-primary">📦</div>
                        </Link>
                        <Link :href="route('control.admin.medicines.racikan')" 
                              class="p-4 rounded-lg shadow-sm flex items-center gap-4 transition-all"
                              :class="activeTab === 'racikan' ? 'bg-purple-50/50 border-2 border-purple-500' : 'bg-card border border-border hover:bg-muted/50'">
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase tracking-wider leading-none" :class="activeTab === 'racikan' ? 'text-purple-600' : 'text-muted-foreground'">Total Racikan</p>
                                <p class="text-2xl font-semibold tracking-tight text-purple-600 leading-none mt-1.5">{{ activeRacikanProducts.total || 0 }}</p>
                            </div>
                            <div class="w-10 h-10 bg-purple-50 rounded-md flex items-center justify-center text-lg border border-purple-100 text-purple-600">🧪</div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- CONTENT AREA -->
            <div class="w-full space-y-6">
                <!-- SEARCH & FILTER BAR -->
                <div class="bg-card p-4 rounded-lg shadow-sm border border-border flex flex-col md:flex-row gap-4 justify-between items-center">
                    <div class="flex items-center gap-3 w-full">
                        <div class="flex-1 relative group">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground group-focus-within:text-foreground transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input 
                                type="text" 
                                v-model="searchQuery"
                                @keyup.enter="handleSearch" 
                                placeholder="Cari berdasarkan nama obat, batch, atau kode SKU..." 
                                class="w-full h-10 pl-10 pr-4 bg-background border border-input focus:ring-1 focus:ring-ring focus:border-input rounded-md text-sm font-medium transition-all outline-none"
                            />
                        </div>
                        <button 
                            @click="handleSearch"
                            :disabled="isFetching"
                            class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-75 text-white font-semibold rounded-md transition-all flex items-center gap-2 justify-center shadow-sm"
                        >
                            <svg v-if="isFetching" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isFetching ? 'Mencari...' : 'Cari' }}</span>
                        </button>
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
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-14 h-14 bg-muted border border-border rounded-lg flex items-center justify-center text-2xl mb-3 text-muted-foreground">📦</div>
                                            <p class="text-muted-foreground font-semibold uppercase tracking-wider text-xs">Belum Ada Obat Dari Faktur</p>
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
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-14 h-14 bg-purple-50 border border-purple-100 rounded-lg flex items-center justify-center text-2xl mb-3 text-purple-300">🧪</div>
                                            <p class="text-muted-foreground font-semibold uppercase tracking-wider text-xs">Belum Ada Produk Racikan</p>
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
