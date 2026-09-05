<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import DashboardCharts from '@/Components/DashboardCharts.vue';

const filterDropdownOpen = ref(false);

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_obat: 0,
            low_stock_count: 0,
            low_stock_percentage: 0,
            pending_admin_count: 0,
            pending_compounding: 0,
            today_sales_count: 0,
            today_sales_total: 0,
            active_prescriptions_count: 0,
            pending_pricing_count: 0
        })
    },
    tableStocks: {
        type: Object,
        default: () => ({ data: [], links: [] })
    },
    currentFilter: {
        type: String,
        default: 'abjad'
    },
    title: { type: String, default: 'Dashboard Strategis' }
});

const applyFilter = (filterName) => {
    router.get(
        route('control.admin.dashboard'),
        { filter: filterName },
        { preserveState: true, preserveScroll: true }
    );
};

// Helpers for dynamic stock display based on qty_converted (Source of Truth)
const getLevel1Qty = (item) => {
    if (!item.qty_converted || item.qty_converted <= 0) return 0;
    
    // Default factor if mid unit doesn't exist
    let totalSmallInBig = item.unit_conversion_factor || 1;
    
    // If mid unit exists, total small in big = small_to_mid * mid_to_big
    if (item.mid_to_big_ratio > 0 && item.small_to_mid_ratio > 0) {
        totalSmallInBig = item.mid_to_big_ratio * item.small_to_mid_ratio;
    }
    
    return Math.floor(item.qty_converted / totalSmallInBig);
};

const getLevel2Qty = (item) => {
    if (!item.qty_converted || item.qty_converted <= 0 || !item.small_to_mid_ratio) return 0;
    
    return Math.floor(item.qty_converted / item.small_to_mid_ratio);
};
</script>

<template>
    <Head title="Dashboard Admin" />

    <ControlLayout>
        <template #header-title>Dashboard Admin</template>

        <div class="space-y-8 animate-in fade-in duration-700">
            <!-- Ringkasan Hari Ini -->
        <div>
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4 ml-1">Ringkasan Hari Ini</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Penjualan Kasir -->
                <div class="bg-indigo-600 p-5 rounded-[24px] shadow-lg text-white relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-indigo-500/30 rounded-full"></div>
                    <span class="text-[10px] font-black text-indigo-300 uppercase tracking-widest block mb-4">Transaksi Kasir</span>
                    <div class="flex items-baseline gap-2 relative z-10">
                        <span class="text-3xl font-black tracking-tighter">{{ stats.today_sales_count || 0 }}</span>
                        <span class="text-[11px] font-bold text-indigo-200 uppercase">Trx</span>
                    </div>
                </div>

                <!-- Total Omset -->
                <div class="bg-emerald-600 p-5 rounded-[24px] shadow-lg text-white relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/30 rounded-full"></div>
                    <span class="text-[10px] font-black text-emerald-300 uppercase tracking-widest block mb-4">Pendapatan</span>
                    <div class="flex items-baseline gap-2 relative z-10">
                        <span class="text-2xl font-black tracking-tighter">Rp {{ new Intl.NumberFormat('id-ID').format(stats.today_sales_total || 0) }}</span>
                    </div>
                </div>

                <!-- Resep Aktif -->
                <div class="bg-amber-500 p-5 rounded-[24px] shadow-lg text-white relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-400/30 rounded-full"></div>
                    <span class="text-[10px] font-black text-amber-200 uppercase tracking-widest block mb-4">Resep Aktif (Diproses/Siap)</span>
                    <div class="flex items-baseline gap-2 relative z-10">
                        <span class="text-3xl font-black tracking-tighter">{{ stats.active_prescriptions_count || 0 }}</span>
                        <span class="text-[11px] font-bold text-amber-100 uppercase">Resep</span>
                    </div>
                </div>

                <!-- Menunggu Harga -->
                <div class="bg-rose-500 p-5 rounded-[24px] shadow-lg text-white relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-rose-400/30 rounded-full"></div>
                    <span class="text-[10px] font-black text-rose-200 uppercase tracking-widest block mb-4">Menunggu Harga</span>
                    <div class="flex items-baseline gap-2 relative z-10">
                        <span class="text-3xl font-black tracking-tighter">{{ stats.pending_pricing_count || 0 }}</span>
                        <span class="text-[11px] font-bold text-rose-100 uppercase">Racikan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 KPI Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

            <!-- Metric 1: Total SKU -->
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6]/80 shadow-[0_8px_24px_rgba(15,23,42,0.04)] hover:shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all group">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Katalog Inventaris</span>
                    <div class="w-10 h-10 bg-emerald-50 rounded-[16px] flex items-center justify-center border border-emerald-100/50 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900 tracking-tighter">{{ stats.total_obat }}</span>
                    <span class="text-[11px] font-bold text-slate-400">ITEM</span>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-[9px] font-black text-emerald-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        DATABASE TERINTEGRASI
                    </div>
                    <svg class="w-16 h-4 text-emerald-400" viewBox="0 0 50 15" fill="none">
                        <path d="M0,15 Q10,5 20,10 T35,5 T50,0" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <!-- Metric 2: Low Stock Alert -->
            <div class="bg-white p-6 rounded-[24px] border border-rose-200/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-rose-500/70">Alert Stok Kritis</span>
                    <div class="w-10 h-10 bg-rose-50 rounded-[16px] flex items-center justify-center border border-rose-100/50">
                        <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-rose-600 tracking-tighter">{{ stats.low_stock_count }}</span>
                    <span class="text-[11px] font-bold text-rose-400 uppercase">SKU</span>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex justify-between text-[9px] font-black uppercase text-slate-400">
                        <span>Defisit Level</span>
                        <span class="text-rose-600">{{ stats.low_stock_percentage }}%</span>
                    </div>
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-rose-500 rounded-full transition-all duration-1000" :style="{ width: stats.low_stock_percentage + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Metric 3: FEFO Alert -->
            <div class="bg-slate-900 p-6 rounded-[24px] shadow-xl text-white relative overflow-hidden group border border-slate-800">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-rose-500/10 rounded-full group-hover:scale-120 transition-transform duration-500"></div>
                <span class="text-[10px] font-black text-rose-400 uppercase tracking-widest block mb-4 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    ALERT FEFO
                </span>
                
                <div v-if="stats.fefo_alert_count > 0" class="flex flex-col">
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-black tracking-tighter text-rose-50">{{ stats.fefo_alert_count }}</span>
                        <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">Batch</span>
                    </div>
                    <div class="mt-4 flex flex-col">
                        <p class="text-[9px] text-slate-400 font-black leading-relaxed uppercase tracking-widest">Prioritas berdasarkan EXP terdekat</p>
                        <p class="text-xs text-rose-300 font-bold mt-1">EXP Terdekat: {{ stats.fefo_nearest_date }}</p>
                    </div>
                </div>

                <div v-else class="flex flex-col h-full justify-center pb-2">
                    <div class="flex items-baseline gap-2 mb-2">
                        <span class="text-3xl font-black tracking-tighter text-emerald-400">0</span>
                        <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">Batch Prioritas</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-bold leading-relaxed">Semua batch aktif saat ini berada dalam kondisi aman.</p>
                </div>
            </div>

            <!-- Metric 4: Lab Queue -->
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6]/80 shadow-[0_8px_24px_rgba(15,23,42,0.04)] relative overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Antrean Racikan Lab</span>
                    <div class="w-10 h-10 bg-amber-50 rounded-[16px] flex items-center justify-center border border-amber-100/50">
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900 tracking-tighter">{{ stats.pending_compounding }}</span>
                    <span class="text-[11px] font-bold text-slate-400 uppercase">Batch</span>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                        <span class="text-[9px] font-black text-slate-400 uppercase">Status Operasional</span>
                    </div>
                    <svg class="w-16 h-4 text-amber-300" viewBox="0 0 50 15" fill="none">
                        <path d="M0,5 L10,12 L20,3 L30,10 L40,2 L50,8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Dashboard Charts -->
        <DashboardCharts />

        <!-- Inventory Monitoring List -->
        <div class="bg-white rounded-[1.5rem] border border-[#E8EEF6]/80 overflow-hidden shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div class="px-8 py-5 border-b border-[#E8EEF6] flex flex-col md:flex-row items-start md:items-center justify-between bg-slate-50/50 gap-4">
                <div>
                    <h3 class="font-black text-slate-900 tracking-tight text-sm uppercase">Monitoring Persediaan Obat</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase tracking-tighter font-bold">Pantau stok terkini dan batas kedaluwarsa secara real-time.</p>
                </div>
                <div class="flex items-center gap-4 relative">
                    <div class="relative group">
                        <button @click="filterDropdownOpen = !filterDropdownOpen" @blur="setTimeout(() => filterDropdownOpen = false, 200)" class="bg-white border border-[#E8EEF6] text-slate-700 font-bold h-[40px] w-[140px] px-3 rounded-[12px] text-[10px] uppercase tracking-widest hover:bg-slate-50 focus:ring-2 focus:ring-emerald-500/20 transition-all flex items-center justify-between shadow-sm">
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                                <span v-if="currentFilter === 'qty'" class="text-blue-600">Stok Kritis</span>
                                <span v-else-if="currentFilter === 'exp'" class="text-rose-600">EXP Terdekat</span>
                                <span v-else>Pilih Filter</span>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 transition-transform duration-300" :class="{'rotate-180': filterDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div v-show="filterDropdownOpen" class="absolute right-0 top-full mt-2 w-[140px] bg-white border border-slate-200 rounded-[16px] shadow-xl overflow-hidden z-20 origin-top-right transition-all">
                            <div class="p-1 space-y-1">
                                <button v-if="currentFilter" @click="applyFilter('')" class="w-full text-left px-3 py-2 text-[10px] font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-700 rounded-[10px] uppercase tracking-widest flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    Hapus Filter
                                </button>
                                <div v-if="currentFilter" class="h-px bg-slate-100 my-1"></div>
                                <button @click="applyFilter('qty')" class="w-full text-left px-3 py-2 text-[10px] font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="currentFilter === 'qty' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-blue-600'">
                                    Stok Kritis
                                    <div v-if="currentFilter === 'qty'" class="w-2 h-2 rounded-full bg-blue-500"></div>
                                </button>
                                <button @click="applyFilter('exp')" class="w-full text-left px-3 py-2 text-[10px] font-bold rounded-[10px] uppercase tracking-widest flex items-center justify-between group transition-colors" :class="currentFilter === 'exp' ? 'bg-rose-50 text-rose-700' : 'text-slate-600 hover:bg-slate-50 hover:text-rose-600'">
                                    EXP Terdekat
                                    <div v-if="currentFilter === 'exp'" class="w-2 h-2 rounded-full bg-rose-500"></div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <Link :href="route('control.admin.incoming-stocks')" class="text-[10px] inline-flex items-center justify-center font-black text-emerald-600 hover:text-emerald-700 uppercase tracking-widest transition-colors h-[40px] px-6 rounded-[12px] border border-emerald-200 hover:bg-emerald-50 bg-white shadow-sm">Lihat Semua</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left font-medium">
                    <thead>
                        <tr class="bg-white text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] border-b border-[#E8EEF6]">
                            <th class="px-8 py-5">Nama Obat</th>
                            <th class="px-8 py-5 text-center">Nomor Batch</th>
                            <th class="px-8 py-5 text-center">Stok Fisik</th>
                            <th class="px-8 py-5 text-center">Batas Aman</th>
                            <th class="px-8 py-5 text-center">Tgl Kedaluwarsa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in tableStocks.data" :key="item.id" class="hover:bg-emerald-50/20 transition-all group">
                            <td class="px-8 py-5">
                                <span class="font-bold text-slate-800 text-sm block group-hover:text-emerald-700 transition-colors uppercase tracking-tighter leading-tight">{{ item.nama_obat }}</span>
                                <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest mt-1 block">{{ item.kategori }}</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="text-[10px] font-black px-3 py-1.5 bg-slate-100 rounded-[8px] text-slate-600 uppercase border border-slate-200 tracking-widest">
                                    {{ item.nomor_batch || 'NO-BATCH' }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="flex items-center justify-center gap-1 flex-wrap">
                                    <!-- LEVEL 1 -->
                                    <template v-if="getLevel1Qty(item) > 0 && item.satuan_input">
                                        <div class="inline-flex items-center gap-1 px-2 py-1 bg-slate-100 border border-slate-200 rounded-[8px] shadow-sm">
                                            <span class="text-xs font-black text-slate-800 font-mono">{{ getLevel1Qty(item) }}</span>
                                            <span class="text-[9px] font-bold text-slate-500 uppercase">{{ item.satuan_input }}</span>
                                        </div>
                                    </template>

                                    <!-- LEVEL 2 -->
                                    <template v-if="item.mid_to_big_ratio > 0 && item.mid_unit && getLevel2Qty(item) > 0">
                                        <div class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 border border-indigo-100 rounded-[8px] shadow-sm">
                                            <span class="text-xs font-black text-indigo-800 font-mono">{{ getLevel2Qty(item) }}</span>
                                            <span class="text-[9px] font-bold text-indigo-500 uppercase">{{ item.mid_unit }}</span>
                                        </div>
                                    </template>

                                    <!-- LEVEL 3 (Base Unit) -->
                                    <template v-if="item.qty_converted > 0 && item.satuan_stok">
                                        <div class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-50 border border-emerald-100 rounded-[8px] shadow-sm">
                                            <span class="text-xs font-black text-emerald-800 font-mono">{{ item.qty_converted }}</span>
                                            <span class="text-[9px] font-bold text-emerald-600 uppercase">{{ item.satuan_stok }}</span>
                                        </div>
                                    </template>
                                    
                                    <!-- ZERO STOCK -->
                                    <template v-if="!item.qty_converted || item.qty_converted <= 0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-rose-600 text-lg tracking-tighter">0.00</span>
                                            <span class="text-[10px] text-rose-400 font-bold uppercase tracking-widest">{{ item.satuan_stok }}</span>
                                        </div>
                                    </template>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-xs font-black text-slate-500 uppercase tracking-widest">{{ item.batas_aman }} Unit</span>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex justify-center">
                                    <span class="text-[10px] font-black px-3 py-1.5 rounded-[8px] uppercase tracking-widest border"
                                        :class="item.is_expiring_soon ? 'bg-rose-50 text-rose-600 border-rose-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'">
                                        {{ item.expired_date }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!tableStocks.data || tableStocks.data.length === 0">
                            <td colspan="5" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 border border-emerald-100/50">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Seluruh logistik dalam kondisi aman (Optimal)</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <div v-if="tableStocks.links && tableStocks.links.length > 3" class="px-8 py-4 border-t border-slate-100 bg-white flex flex-wrap justify-center gap-1">
                    <template v-for="(link, k) in tableStocks.links" :key="k">
                        <Link 
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-[8px] border transition-all"
                            :class="link.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-md' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50 hover:text-emerald-600'"
                            v-html="link.label"
                            preserve-scroll
                        ></Link>
                        <span v-else class="px-3 py-1.5 text-[11px] font-bold rounded-[8px] border bg-slate-50 text-slate-400 border-slate-200" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>
        </div>
    </ControlLayout>
</template>
