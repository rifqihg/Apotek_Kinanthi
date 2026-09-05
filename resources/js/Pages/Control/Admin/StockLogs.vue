<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({ 
    logs: Object,
    kpi: Object,
    filters: Object
});

const search = ref(props.filters?.search || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const filterType = ref(props.filters?.type || 'all');
const filterDropdownOpen = ref(false);

const displayType = (l) => {
    if (l.type === 'Masuk' || l.type === 'Penyesuaian Masuk') return 'Masuk';
    if (l.reference_type === 'App\\Models\\Control\\ReturnForm' || l.type === 'Retur') return 'Retur';
    if (l.type === 'Keluar' || l.type === 'Penjualan' || l.type === 'Penyesuaian Keluar') return 'Terjual';
    return l.type;
};

const getLogTypeClass = (type) => {
    if (type === 'Masuk') return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    if (type === 'Retur') return 'bg-amber-50 text-amber-600 border-amber-100';
    if (type === 'Terjual') return 'bg-blue-50 text-blue-600 border-blue-100';
    return 'bg-slate-50 text-slate-600 border-slate-200';
};

// Format Referensi
const formatReference = (l) => {
    if (l.reference_type) {
        const type = l.reference_type.split('\\').pop();
        let title = type;
        if (type === 'ReturnForm') title = 'Retur Pembelian';
        else if (type === 'Sale') title = 'Penjualan (Kasir)';
        else if (type === 'StockOpname') title = 'Stock Opname';
        else if (type === 'IncomingStock') title = 'Faktur Masuk';
        
        return {
            title: title,
            id: 'ID: ' + l.reference_id
        };
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

    // Level 1: Satuan Terbesar (e.g. Dus)
    if (incomingStock?.satuan_input && totalSmallInBig > 1) {
        l1 = Math.floor(rem / totalSmallInBig);
        rem = rem % totalSmallInBig;
    }

    // Level 2: Satuan Menengah (e.g. Strip jika ada)
    if (incomingStock?.mid_unit && incomingStock?.small_to_mid_ratio > 1) {
        l2 = Math.floor(rem / incomingStock.small_to_mid_ratio);
        rem = rem % incomingStock.small_to_mid_ratio;
    }

    // Level 3: Satuan Terkecil (e.g. Tablet / Strip)
    l3 = Number(Number(rem).toFixed(2));

    return { l1, l2, l3 };
};

const getLevel1Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l1;
const getLevel2Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l2;
const getLevel3Qty = (qty, incomingStock) => getUnitsBreakdown(qty, incomingStock).l3;
const applyFilters = () => {
    router.get(route('control.admin.stock-logs'), {
        search: search.value,
        start_date: startDate.value,
        end_date: endDate.value,
        type: filterType.value !== 'all' ? filterType.value : undefined
    }, { preserveState: true, preserveScroll: true });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

watch(search, () => debouncedSearch());
watch([startDate, endDate, filterType], () => applyFilters());

const clearFilters = () => {
    search.value = '';
    startDate.value = '';
    endDate.value = '';
    filterType.value = 'all';
    applyFilters();
};
</script>

<template>
<Head title="Log Pergerakan Stok" />
<ControlLayout title="Log Pergerakan Stok">
<div class="p-4 lg:p-6 space-y-6 max-w-[1400px] mx-auto">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Log Pergerakan Stok</h1>
            <p class="text-slate-500 text-sm mt-1">Lacak seluruh aktivitas stok secara transparan berdasarkan riwayat mutasi.</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div v-if="kpi" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-[#E8EEF6] p-5 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full opacity-50 pointer-events-none"></div>
            <div class="w-12 h-12 bg-emerald-50 rounded-[16px] flex items-center justify-center text-xl text-emerald-600 shrink-0 border border-emerald-100 z-10">📥</div>
            <div class="z-10">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Trx Stok Masuk</p>
                <p class="text-2xl font-black text-slate-800 leading-none mt-1">{{ kpi.in || 0 }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#E8EEF6] p-5 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-50 rounded-full opacity-50 pointer-events-none"></div>
            <div class="w-12 h-12 bg-amber-50 rounded-[16px] flex items-center justify-center text-xl text-amber-600 shrink-0 border border-amber-100 z-10">📤</div>
            <div class="z-10">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Trx Stok Keluar</p>
                <p class="text-2xl font-black text-slate-800 leading-none mt-1">{{ kpi.out || 0 }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#E8EEF6] p-5 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full opacity-50 pointer-events-none"></div>
            <div class="w-12 h-12 bg-blue-50 rounded-[16px] flex items-center justify-center text-xl text-blue-600 shrink-0 border border-blue-100 z-10">📊</div>
            <div class="z-10">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Mutasi</p>
                <p class="text-2xl font-black text-slate-800 leading-none mt-1">{{ kpi.total || 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-[#E8EEF6] p-4 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex flex-wrap gap-4 items-center">
        <div class="flex-1 min-w-[200px] relative">
            <input v-model="search" type="text" placeholder="Cari nama obat atau bahan baku..." 
                   class="w-full h-[52px] bg-[#F5F9FF] border-none rounded-[16px] pl-12 pr-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20 transition-all placeholder:text-slate-400" />
            <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <div class="relative">
            <button @click="filterDropdownOpen = !filterDropdownOpen" @blur="setTimeout(() => filterDropdownOpen = false, 200)" class="h-[52px] min-w-[140px] bg-[#F5F9FF] border-none rounded-[16px] px-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/20 cursor-pointer outline-none transition-all flex items-center justify-between gap-3 hover:bg-slate-50 shadow-sm">
                <div class="flex items-center gap-2">
                    <span v-if="filterType === 'all'" class="text-slate-700">Semua Log</span>
                    <span v-else-if="filterType === 'Masuk'" class="text-emerald-600">Masuk</span>
                    <span v-else-if="filterType === 'Retur'" class="text-amber-600">Retur</span>
                    <span v-else-if="filterType === 'Jual'" class="text-blue-600">Terjual</span>
                </div>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-300" :class="{'rotate-180': filterDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-show="filterDropdownOpen" class="absolute left-0 top-full mt-2 w-full min-w-[140px] bg-white border border-slate-200 rounded-[16px] shadow-xl overflow-hidden z-20 origin-top-left transition-all">
                <div class="p-1.5 space-y-0.5">
                    <button @click="filterType = 'all'" class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="filterType === 'all' ? 'bg-slate-50 text-slate-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'">
                        Semua Log
                        <div v-if="filterType === 'all'" class="w-2 h-2 rounded-full bg-slate-500"></div>
                    </button>
                    <div class="h-px bg-slate-100 my-1"></div>
                    <button @click="filterType = 'Masuk'" class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="filterType === 'Masuk' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-500 hover:bg-slate-50 hover:text-emerald-600'">
                        Masuk
                        <div v-if="filterType === 'Masuk'" class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    </button>
                    <button @click="filterType = 'Retur'" class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="filterType === 'Retur' ? 'bg-amber-50 text-amber-700' : 'text-slate-500 hover:bg-slate-50 hover:text-amber-600'">
                        Retur
                        <div v-if="filterType === 'Retur'" class="w-2 h-2 rounded-full bg-amber-500"></div>
                    </button>
                    <button @click="filterType = 'Jual'" class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="filterType === 'Jual' ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'">
                        Terjual
                        <div v-if="filterType === 'Jual'" class="w-2 h-2 rounded-full bg-blue-500"></div>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input v-model="startDate" type="date" class="h-[52px] bg-[#F5F9FF] border-none rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20" />
            <span class="text-slate-400">-</span>
            <input v-model="endDate" type="date" class="h-[52px] bg-[#F5F9FF] border-none rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20" />
        </div>
        <button v-if="search || startDate || endDate || filterType !== 'all'" @click="clearFilters" class="h-[52px] px-4 text-sm font-bold text-rose-500 hover:bg-rose-50 rounded-[16px] transition-colors">
            Reset
        </button>
    </div>

    <!-- Table -->
    <div class="bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 text-slate-500 text-xs uppercase font-bold border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-6 py-4">Waktu</th>
                        <th class="px-6 py-4">Jenis</th>
                        <th class="px-6 py-4">Item (Obat / Bahan)</th>
                        <th class="px-6 py-4">Batch</th>
                        <th class="px-6 py-4">Referensi</th>
                        <th class="px-6 py-4 text-center">Qty Mutasi</th>
                        <th class="px-6 py-4 text-center">Sisa Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!logs.data.length">
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                <span>Belum terdapat aktivitas mutasi stok pada periode yang dipilih.</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50/50 transition">
                        <!-- Waktu -->
                        <td class="px-6 py-4 text-slate-500 text-xs">
                            <div class="font-bold text-slate-700">{{ new Date(l.created_at).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}</div>
                            <div class="text-[10px] mt-0.5">{{ new Date(l.created_at).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) }}</div>
                        </td>
                        
                        <!-- Jenis -->
                        <td class="px-6 py-4">
                            <span :class="getLogTypeClass(displayType(l))" class="px-2.5 py-1 rounded-[6px] text-[10px] font-bold border uppercase tracking-wider inline-flex items-center whitespace-nowrap">
                                {{ displayType(l) }}
                            </span>
                        </td>
                        
                        <!-- Item -->
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-slate-900 font-bold whitespace-nowrap">
                                    {{ l.inventory?.medicine?.nama_obat || l.rawMaterialStock?.rawMaterial?.nama_bahan || 'Unknown Item' }}
                                </span>
                                <span v-if="l.inventory?.medicine" class="text-[10px] text-slate-400 font-medium mt-0.5">Kategori: {{ l.inventory.medicine.kategori }}</span>
                            </div>
                        </td>
                        
                        <!-- Batch -->
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-md text-[10px] font-mono font-bold uppercase whitespace-nowrap">
                                {{ l.inventory?.batch_no || l.rawMaterialStock?.batch_no || '-' }}
                            </span>
                        </td>
                        
                        <!-- Referensi -->
                        <td class="px-6 py-4">
                            <div class="text-xs">
                                <div class="font-bold text-slate-700">{{ formatReference(l).title }}</div>
                                <div class="text-slate-400 text-[10px] font-mono">{{ formatReference(l).id }}</div>
                            </div>
                        </td>
                        
                        <!-- QTY Mutasi -->
                        <td class="px-6 py-4 text-center">
                            <!-- Multi-Unit Display (Medicines only) -->
                            <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1">
                                <!-- Level 1 -->
                                <template v-if="getLevel1Qty(l.quantity, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.satuan_input">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded-[6px]">
                                        <span class="text-xs font-black text-slate-800 font-mono">{{ getLevel1Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-slate-500 uppercase">{{ l.inventory.incoming_stock.satuan_input }}</span>
                                    </div>
                                </template>
                                <!-- Level 2 -->
                                <template v-if="getLevel2Qty(l.quantity, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.mid_unit">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 border border-indigo-100 rounded-[6px]">
                                        <span class="text-xs font-black text-indigo-800 font-mono">{{ getLevel2Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-indigo-500 uppercase">{{ l.inventory.incoming_stock.mid_unit }}</span>
                                    </div>
                                </template>
                                <!-- Level 3 -->
                                <template v-if="getLevel3Qty(l.quantity, l.inventory.incoming_stock) > 0">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-sky-50 border border-sky-100 rounded-[6px]">
                                        <span class="text-xs font-black text-sky-800 font-mono">{{ getLevel3Qty(l.quantity, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-sky-600 uppercase">{{ l.inventory.medicine.satuan_stok }}</span>
                                    </div>
                                </template>
                                <template v-if="!getLevel1Qty(l.quantity, l.inventory.incoming_stock) && !getLevel2Qty(l.quantity, l.inventory.incoming_stock) && !getLevel3Qty(l.quantity, l.inventory.incoming_stock)">
                                    <span class="text-xs font-black text-slate-400">0</span>
                                </template>
                            </div>
                            <!-- Base Unit Display (Raw Materials / Others) -->
                            <div v-else class="inline-flex items-center justify-center gap-1">
                                <span class="text-sm font-black text-slate-800">{{ Number(l.quantity) }}</span>
                                <span class="text-[10px] font-bold text-slate-500 uppercase">{{ l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                            </div>
                        </td>
                        
                        <!-- Sisa Stok -->
                        <td class="px-6 py-4 text-center bg-slate-50/30">
                            <!-- Multi-Unit Display (Medicines only) -->
                            <div v-if="l.inventory?.medicine" class="flex flex-wrap items-center justify-center gap-1">
                                <!-- Level 1 -->
                                <template v-if="getLevel1Qty(l.balance_after, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.satuan_input">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded-[6px]">
                                        <span class="text-xs font-black text-slate-800 font-mono">{{ getLevel1Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-slate-500 uppercase">{{ l.inventory.incoming_stock.satuan_input }}</span>
                                    </div>
                                </template>
                                <!-- Level 2 -->
                                <template v-if="getLevel2Qty(l.balance_after, l.inventory.incoming_stock) > 0 && l.inventory.incoming_stock?.mid_unit">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 border border-indigo-100 rounded-[6px]">
                                        <span class="text-xs font-black text-indigo-800 font-mono">{{ getLevel2Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-indigo-500 uppercase">{{ l.inventory.incoming_stock.mid_unit }}</span>
                                    </div>
                                </template>
                                <!-- Level 3 -->
                                <template v-if="getLevel3Qty(l.balance_after, l.inventory.incoming_stock) > 0">
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-sky-50 border border-sky-100 rounded-[6px]">
                                        <span class="text-xs font-black text-sky-800 font-mono">{{ getLevel3Qty(l.balance_after, l.inventory.incoming_stock) }}</span>
                                        <span class="text-[9px] font-bold text-sky-600 uppercase">{{ l.inventory.medicine.satuan_stok }}</span>
                                    </div>
                                </template>
                                <template v-if="!getLevel1Qty(l.balance_after, l.inventory.incoming_stock) && !getLevel2Qty(l.balance_after, l.inventory.incoming_stock) && !getLevel3Qty(l.balance_after, l.inventory.incoming_stock)">
                                    <span class="text-xs font-black text-slate-400">0</span>
                                </template>
                            </div>
                            <!-- Base Unit Display -->
                            <div v-else class="inline-flex items-center justify-center gap-1">
                                <span class="text-sm font-black text-slate-800">{{ Number(l.balance_after) }}</span>
                                <span class="text-[10px] font-bold text-slate-500 uppercase">{{ l.rawMaterialStock?.rawMaterial?.satuan || '' }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.links && logs.links.length > 3" class="px-6 py-4 border-t border-[#E8EEF6] flex items-center justify-center gap-1 bg-slate-50/30">
            <template v-for="(l, index) in logs.links" :key="index">
                <Link v-if="l.url" :href="l.url" :class="l.active ? 'bg-blue-600 text-white font-bold shadow-[0_4px_12px_rgba(37,99,235,0.2)]' : 'bg-white border border-[#E8EEF6] text-slate-600 hover:bg-slate-50 font-medium'" class="px-3 py-1.5 min-w-[32px] text-center rounded-[8px] text-xs transition-all" v-html="l.label" />
                <span v-else class="px-3 py-1.5 min-w-[32px] text-center text-slate-400 text-xs font-medium" v-html="l.label"></span>
            </template>
        </div>
    </div>
</div>
</ControlLayout>
</template>
