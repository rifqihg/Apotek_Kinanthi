<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useQuery } from '@tanstack/vue-query';

defineOptions({ layout: ControlLayout });
import axios from 'axios';

const props = defineProps({ 
    logs: Object,
    kpi: Object,
    filters: Object
});

const page = usePage();
const search = ref(props.filters?.search || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const filterType = ref(props.filters?.type || 'all');
const filterDropdownOpen = ref(false);

// TanStack Vue Query: Smooth reactive log & KPI data
const { data: queryData, isFetching } = useQuery({
    queryKey: computed(() => ['admin-stock-logs', search.value, startDate.value, endDate.value, filterType.value]),
    queryFn: async () => {
        if (!search.value && !startDate.value && !endDate.value && filterType.value === 'all') {
            return { logs: props.logs, kpi: props.kpi };
        }
        const res = await axios.get(route('control.admin.stock-logs'), {
            params: {
                search: search.value || undefined,
                start_date: startDate.value || undefined,
                end_date: endDate.value || undefined,
                type: filterType.value !== 'all' ? filterType.value : undefined,
            },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        return {
            logs: res.data?.props?.logs || props.logs,
            kpi: res.data?.props?.kpi || props.kpi
        };
    },
    initialData: () => ({ logs: props.logs, kpi: props.kpi }),
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (previousData) => previousData,
});

const activeLogs = computed(() => queryData.value?.logs || props.logs);
const activeKpi = computed(() => queryData.value?.kpi || props.kpi);

const displayType = (l) => {
    if (l.type === 'Masuk' || l.type === 'Penyesuaian Masuk') return 'Masuk';
    if (l.reference_type === 'App\\Models\\Control\\ReturnForm' || l.type === 'Retur') return 'Retur';
    if (l.type === 'Keluar' || l.type === 'Penjualan' || l.type === 'Penyesuaian Keluar') return 'Terjual';
    return l.type;
};

const getLogTypeClass = (type) => ({
    Masuk: 'bg-emerald-50 text-emerald-600 border-emerald-100',
    Retur: 'bg-amber-50 text-amber-600 border-amber-100',
    Terjual: 'bg-blue-50 text-blue-600 border-blue-100',
}[type] || 'bg-slate-50 text-slate-600 border-slate-200');

// Format Referensi
const refTitles = { ReturnForm: 'Retur Pembelian', Sale: 'Penjualan (Kasir)', StockOpname: 'Stock Opname', IncomingStock: 'Faktur Masuk' };
const formatReference = (l) => {
    if (l.reference_type) {
        const type = l.reference_type.split('\\').pop();
        return { title: refTitles[type] || type, id: 'ID: ' + l.reference_id };
    }

    if (l.type === 'Masuk' && l.inventory?.incoming_stock) {
        return {
            title: 'Faktur Masuk',
            id: l.inventory.incoming_stock.faktur_no || '-'
        };
    }
    
    return { title: '-', id: '' };
};

// ==========================================
// KEMASAN / MULTI-UNIT HELPERS (Hierarchical Breakdown)
// ==========================================
const getUnitsBreakdown = (qty, incomingStock) => {
    if (!qty || qty <= 0) return { l1: 0, l2: 0, l3: 0 };
    let rem = Number(qty);
    let l1 = 0, l2 = 0, l3 = 0;

    let totalSmallInBig = incomingStock?.unit_conversion_factor || 1;
    if (incomingStock?.mid_to_big_ratio > 0 && incomingStock?.small_to_mid_ratio > 0) {
        totalSmallInBig = incomingStock.mid_to_big_ratio * incomingStock.small_to_mid_ratio;
    }

    if (incomingStock?.satuan_input && totalSmallInBig > 1 && rem >= totalSmallInBig) {
        l1 = Math.floor(rem / totalSmallInBig);
        rem %= totalSmallInBig;
    }
    if (incomingStock?.mid_unit && incomingStock?.small_to_mid_ratio > 1 && rem >= incomingStock?.small_to_mid_ratio) {
        l2 = Math.floor(rem / incomingStock.small_to_mid_ratio);
        rem %= incomingStock.small_to_mid_ratio;
    }
    if (rem > 0) {
        l3 = Number(rem.toFixed(2));
    }
    return { l1, l2, l3 };
};

const getLevel1Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l1;
const getLevel2Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l2;
const getLevel3Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l3;

const clearFilters = () => {
    search.value = '';
    startDate.value = '';
    endDate.value = '';
    filterType.value = 'all';
};
</script>

<template>
<Head title="Log Pergerakan Stok" />
<div class="space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-foreground tracking-tight">Log Pergerakan Stok</h1>
            <p class="text-muted-foreground text-sm mt-1">Lacak seluruh aktivitas stok secara transparan berdasarkan riwayat mutasi.</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div v-if="activeKpi" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-card border border-border p-5 rounded-lg shadow-sm flex items-center gap-4">
            <div class="w-10 h-10 bg-emerald-500/10 rounded-md flex items-center justify-center text-lg text-emerald-600 shrink-0 border border-emerald-500/20">📥</div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Trx Stok Masuk</p>
                <p class="text-2xl font-bold text-foreground leading-none mt-1">{{ activeKpi.in || 0 }}</p>
            </div>
        </div>
        <div class="bg-card border border-border p-5 rounded-lg shadow-sm flex items-center gap-4">
            <div class="w-10 h-10 bg-amber-500/10 rounded-md flex items-center justify-center text-lg text-amber-600 shrink-0 border border-amber-500/20">📤</div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Trx Stok Keluar</p>
                <p class="text-2xl font-bold text-foreground leading-none mt-1">{{ activeKpi.out || 0 }}</p>
            </div>
        </div>
        <div class="bg-card border border-border p-5 rounded-lg shadow-sm flex items-center gap-4">
            <div class="w-10 h-10 bg-primary/10 rounded-md flex items-center justify-center text-lg text-primary shrink-0 border border-primary/20">📊</div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Mutasi</p>
                <p class="text-2xl font-bold text-foreground leading-none mt-1">{{ activeKpi.total || 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-card border border-border p-3 rounded-lg shadow-sm flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[200px] relative">
            <input v-model="search" type="text" placeholder="Cari nama obat atau bahan baku..." 
                   class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" />
            <svg v-if="!isFetching" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <svg v-else class="animate-spin w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <div class="relative">
            <button @click="filterDropdownOpen = !filterDropdownOpen" @blur="setTimeout(() => filterDropdownOpen = false, 200)" class="h-9 min-w-[130px] bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground hover:bg-accent cursor-pointer outline-none transition-colors flex items-center justify-between gap-2 shadow-sm">
                <div class="flex items-center gap-1.5">
                    <span v-if="filterType === 'all'" class="text-foreground">Semua Log</span>
                    <span v-else-if="filterType === 'Masuk'" class="text-emerald-600 font-semibold">Masuk</span>
                    <span v-else-if="filterType === 'Retur'" class="text-amber-600 font-semibold">Retur</span>
                    <span v-else-if="filterType === 'Jual'" class="text-primary font-semibold">Terjual</span>
                </div>
                <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200" :class="{'rotate-180': filterDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-show="filterDropdownOpen" class="absolute left-0 top-full mt-1.5 w-full min-w-[140px] bg-card border border-border rounded-md shadow-lg overflow-hidden z-20 origin-top-left transition-all">
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
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input v-model="startDate" type="date" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
            <span class="text-muted-foreground text-xs">-</span>
            <input v-model="endDate" type="date" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
        </div>
        <button v-if="search || startDate || endDate || filterType !== 'all'" @click="clearFilters" class="h-9 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 rounded-md transition-colors">
            Reset
        </button>
    </div>

    <!-- Table -->
    <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 text-muted-foreground text-xs uppercase font-semibold border-b border-border">
                    <tr>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Jenis</th>
                        <th class="px-5 py-3">Item (Obat / Bahan)</th>
                        <th class="px-5 py-3">Batch</th>
                        <th class="px-5 py-3">Referensi</th>
                        <th class="px-5 py-3 text-center">Qty Mutasi</th>
                        <th class="px-5 py-3 text-center">Sisa Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!activeLogs.data || !activeLogs.data.length">
                        <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                <span class="text-xs font-medium">Belum terdapat aktivitas mutasi stok pada periode yang dipilih.</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="l in (activeLogs.data || [])" :key="l.id" class="hover:bg-muted/40 transition-colors">
                        <!-- Waktu -->
                        <td class="px-5 py-3 text-muted-foreground text-xs">
                            <div class="font-semibold text-foreground">{{ new Date(l.created_at).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}</div>
                            <div class="text-xs text-muted-foreground mt-0.5">{{ new Date(l.created_at).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) }}</div>
                        </td>
                        
                        <!-- Jenis -->
                        <td class="px-5 py-3">
                            <span :class="getLogTypeClass(displayType(l))" class="px-2 py-0.5 rounded-md text-xs font-semibold border uppercase tracking-wider inline-flex items-center whitespace-nowrap">
                                {{ displayType(l) }}
                            </span>
                        </td>
                        
                        <!-- Item -->
                        <td class="px-5 py-3">
                            <div class="flex flex-col">
                                <span class="text-foreground font-semibold text-sm whitespace-nowrap">
                                    {{ l.inventory?.medicine?.nama_obat || l.rawMaterialStock?.rawMaterial?.nama_bahan || 'Unknown Item' }}
                                </span>
                                <span v-if="l.inventory?.medicine" class="text-xs text-muted-foreground mt-0.5">Kategori: {{ l.inventory.medicine.kategori }}</span>
                            </div>
                        </td>
                        
                        <!-- Batch -->
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 bg-muted text-muted-foreground rounded-md text-xs font-medium uppercase whitespace-nowrap border border-border">
                                {{ l.inventory?.batch_no || l.rawMaterialStock?.batch_no || '-' }}
                            </span>
                        </td>
                        
                        <!-- Referensi -->
                        <td class="px-5 py-3">
                            <div class="text-xs">
                                <div class="font-medium text-foreground">{{ formatReference(l).title }}</div>
                                <div class="text-muted-foreground text-xs">{{ formatReference(l).id }}</div>
                            </div>
                        </td>
                        
                        <!-- QTY Mutasi -->
                        <td class="px-5 py-3 text-center">
                            <!-- Multi-Unit Display (Medicines only) -->
                            <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1">
                                <!-- Level 1 -->
                                <template v-if="getLevel1Qty(l.quantity, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.satuan_input">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-muted border border-border rounded-md">
                                        <span class="text-xs font-bold text-foreground">{{ getLevel1Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-muted-foreground uppercase">{{ l.inventory.incoming_stock.satuan_input }}</span>
                                    </div>
                                </template>
                                <!-- Level 2 -->
                                <template v-if="getLevel2Qty(l.quantity, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.mid_unit">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-500/10 border border-indigo-500/20 rounded-md">
                                        <span class="text-xs font-bold text-indigo-600">{{ getLevel2Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-indigo-500 uppercase">{{ l.inventory.incoming_stock.mid_unit }}</span>
                                    </div>
                                </template>
                                <!-- Level 3 -->
                                <template v-if="getLevel3Qty(l.quantity, l.inventory.incoming_stock) > 0">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-sky-500/10 border border-sky-500/20 rounded-md">
                                        <span class="text-xs font-bold text-sky-600">{{ getLevel3Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-sky-500 uppercase">{{ l.inventory.medicine.satuan_stok }}</span>
                                    </div>
                                </template>
                                <template v-if="!getLevel1Qty(l.quantity, l.inventory.incoming_stock) && !getLevel2Qty(l.quantity, l.inventory.incoming_stock) && !getLevel3Qty(l.quantity, l.inventory.incoming_stock)">
                                    <span class="text-xs font-bold text-muted-foreground">0</span>
                                </template>
                            </div>
                            <!-- Base Unit Display (Raw Materials / Others) -->
                            <div v-else class="inline-flex items-center justify-center gap-1">
                                <span class="text-sm font-bold text-foreground">{{ Number(l.quantity) }}</span>
                                <span class="text-xs text-muted-foreground uppercase">{{ l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                            </div>
                        </td>
                        
                        <!-- Sisa Stok -->
                        <td class="px-5 py-3 text-center bg-muted/20">
                            <!-- Multi-Unit Display (Medicines only) -->
                            <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1">
                                <!-- Level 1 -->
                                <template v-if="getLevel1Qty(l.balance_after, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.satuan_input">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-muted border border-border rounded-md">
                                        <span class="text-xs font-bold text-foreground">{{ getLevel1Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-muted-foreground uppercase">{{ l.inventory.incoming_stock.satuan_input }}</span>
                                    </div>
                                </template>
                                <!-- Level 2 -->
                                <template v-if="getLevel2Qty(l.balance_after, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.mid_unit">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-500/10 border border-indigo-500/20 rounded-md">
                                        <span class="text-xs font-bold text-indigo-600">{{ getLevel2Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-indigo-500 uppercase">{{ l.inventory.incoming_stock.mid_unit }}</span>
                                    </div>
                                </template>
                                <!-- Level 3 -->
                                <template v-if="getLevel3Qty(l.balance_after, l.inventory.incoming_stock) > 0">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-sky-500/10 border border-sky-500/20 rounded-md">
                                        <span class="text-xs font-bold text-sky-600">{{ getLevel3Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-xs text-sky-500 uppercase">{{ l.inventory.medicine.satuan_stok }}</span>
                                    </div>
                                </template>
                                <template v-if="!getLevel1Qty(l.balance_after, l.inventory.incoming_stock) && !getLevel2Qty(l.balance_after, l.inventory.incoming_stock) && !getLevel3Qty(l.balance_after, l.inventory.incoming_stock)">
                                    <span class="text-xs font-bold text-muted-foreground">0</span>
                                </template>
                            </div>
                            <!-- Base Unit Display -->
                            <div v-else class="inline-flex items-center justify-center gap-1">
                                <span class="text-sm font-bold text-foreground">{{ Number(l.balance_after) }}</span>
                                <span class="text-xs text-muted-foreground uppercase">{{ l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="activeLogs.links && activeLogs.links.length > 3" class="px-5 py-3 border-t border-border flex items-center justify-center gap-1 bg-muted/30">
            <template v-for="(l, index) in activeLogs.links" :key="index">
                <Link v-if="l.url" :href="l.url" :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border border-input text-foreground hover:bg-accent font-medium'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs transition-colors" v-html="l.label" />
                <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium" v-html="l.label"></span>
            </template>
        </div>
    </div>
</div>
</template>
