<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

const props = defineProps({
    fakturStocks: { type: Object, default: () => ({ data: [], total: 0 }) },
    racikanProducts: { type: Object, default: () => ({ data: [], total: 0 }) },
    title: { type: String, default: 'Katalog Obat & Sediaan' },
    activeTab: { type: String, default: 'faktur' },
    filters: { type: Object, default: () => ({ search: '' }) }
});

const searchQuery = ref(props.filters.search || '');

const handleSearch = () => {
    router.get(
        route(props.activeTab === 'faktur' ? 'control.admin.medicines.faktur' : 'control.admin.medicines.racikan'),
        { search: searchQuery.value },
        { preserveState: true, preserveScroll: true }
    );
};

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

    <ControlLayout>
        <template #header-title>{{ title }}</template>

        <div class="space-y-6 animate-in fade-in duration-700">
            <!-- COMPACT PREMIUM HERO HEADER -->
            <div class="relative p-6 bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden group">
                <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50/50 rounded-full -mr-32 -mt-32 transition-transform group-hover:scale-110 duration-1000"></div>
                
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-50 text-blue-700 text-[9px] font-bold uppercase tracking-widest rounded-full border border-blue-100">Master Data</span>
                            <span class="px-3 py-1 bg-slate-50 text-slate-500 text-[9px] font-bold uppercase tracking-widest rounded-full border border-[#E8EEF6]">Katalog Terpusat</span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-none uppercase">Katalog Obat Jadi</h1>
                            <p class="text-slate-500 font-medium mt-1.5 max-w-xl text-xs">Manajemen sediaan dari faktur masuk dan hasil racikan apoteker.</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <Link :href="route('control.admin.medicines.faktur')" 
                              class="p-4 rounded-[16px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4 transition-all"
                              :class="activeTab === 'faktur' ? 'bg-blue-50/50 border-2 border-blue-500 shadow-blue-100' : 'bg-white border border-[#E8EEF6] hover:bg-slate-50'">
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-widest leading-none" :class="activeTab === 'faktur' ? 'text-blue-600' : 'text-slate-400'">Total Faktur</p>
                                <p class="text-2xl font-black text-blue-600 leading-none mt-1.5">{{ fakturStocks.total || 0 }}</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center text-lg border border-blue-100 text-blue-600">📦</div>
                        </Link>
                        <Link :href="route('control.admin.medicines.racikan')" 
                              class="p-4 rounded-[16px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4 transition-all"
                              :class="activeTab === 'racikan' ? 'bg-purple-50/50 border-2 border-purple-500 shadow-purple-100' : 'bg-white border border-[#E8EEF6] hover:bg-slate-50'">
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-widest leading-none" :class="activeTab === 'racikan' ? 'text-purple-600' : 'text-slate-400'">Total Racikan</p>
                                <p class="text-2xl font-black text-purple-600 leading-none mt-1.5">{{ racikanProducts.total || 0 }}</p>
                            </div>
                            <div class="w-10 h-10 bg-purple-50 rounded-[12px] flex items-center justify-center text-lg border border-purple-100 text-purple-600">🧪</div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- CONTENT AREA -->
            <div class="w-full space-y-6">
                <!-- SEARCH & FILTER BAR -->
                <div class="bg-white p-4 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] flex flex-col md:flex-row gap-4 justify-between items-center">
                    <div class="flex items-center gap-3 w-full">
                        <div class="flex-1 relative group">
                            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input 
                                type="text" 
                                v-model="searchQuery"
                                @keyup.enter="handleSearch" 
                                placeholder="Cari berdasarkan nama obat, batch, atau kode SKU..." 
                                class="w-full h-[48px] pl-11 pr-4 bg-slate-50 border-[#E8EEF6] focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200/50 rounded-[16px] text-sm font-medium transition-all outline-none shadow-[0_8px_24px_rgba(15,23,42,0.04)]"
                            />
                        </div>
                        <button 
                            @click="handleSearch"
                            class="h-[48px] px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-[16px] transition-colors flex items-center justify-center shadow-[0_8px_24px_rgba(37,99,235,0.2)]"
                        >
                            Cari
                        </button>
                    </div>
                </div>

                    <!-- TABLE: KATALOG FAKTUR -->
                    <div v-if="activeTab === 'faktur'" class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] animate-in fade-in slide-in-from-right-4 duration-500">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Identitas Obat</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Nomor Batch</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Exp Date</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center whitespace-nowrap">Qty / Jumlah</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right whitespace-nowrap">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E8EEF6]">
                                <tr v-if="!fakturStocks.data || fakturStocks.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-16 h-16 bg-slate-50 border border-[#E8EEF6] rounded-[16px] flex items-center justify-center text-2xl mb-4 text-slate-300 grayscale">📦</div>
                                            <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Belum Ada Obat Dari Faktur</p>
                                        </div>
                                    </td>
                                </tr>
                                <template v-for="item in fakturStocks.data" :key="item.id">
                                <tr class="transition-colors group" :class="expandedRows.includes(item.id) ? 'bg-slate-50/50' : 'hover:bg-slate-50/30'">
                                    <!-- IDENTITAS -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center text-blue-600 font-bold text-xs border border-blue-100 group-hover:scale-105 transition-transform flex-shrink-0">
                                                {{ item.medicine?.nama_obat ? item.medicine.nama_obat.substring(0, 2).toUpperCase() : 'OB' }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-900 text-sm mb-1 line-clamp-1" :title="item.medicine?.nama_obat">{{ item.medicine?.nama_obat || 'Obat Tidak Diketahui' }}</span>
                                                <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">{{ item.medicine?.kategori || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- BATCH NO -->
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 bg-amber-50 text-amber-700 rounded-md text-[10px] font-mono font-bold border border-amber-200 uppercase tracking-wider inline-flex items-center gap-1.5 whitespace-nowrap">
                                            {{ item.batch_no || '-' }}
                                        </span>
                                    </td>
                                    <!-- EXP DATE -->
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-bold whitespace-nowrap" :class="new Date(item.expired_date) < new Date() ? 'text-rose-600' : 'text-slate-700'">
                                            {{ item.expired_date ? new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : '-' }}
                                        </span>
                                    </td>
                                    <!-- QTY -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <div v-for="(b, i) in getStockUnits(item)" :key="i"
                                                 class="inline-flex items-center gap-1 px-2 py-1 border rounded-[8px] shadow-sm" :class="b.cls">
                                                <span class="text-xs font-black font-mono">{{ b.qty }}</span>
                                                <span class="text-[9px] font-bold uppercase opacity-80">{{ b.unit }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- HARGA & PROFIT -->
                                    <td class="px-6 py-4 text-right align-top">
                                        <div @click="toggleRow(item.id)" class="group inline-flex flex-col items-end cursor-pointer focus:outline-none w-full select-none justify-end">
                                            <div class="flex items-center justify-end gap-1.5 w-full">
                                                <span class="font-bold text-[14px]" :class="item.current_selling_price > 0 ? 'text-slate-800' : 'text-slate-400'">
                                                    {{ item.current_selling_price > 0 ? formatCurrency(item.current_selling_price) : 'Rp 0' }}
                                                </span>
                                                <div class="p-0.5 rounded text-slate-400 transition-colors" :class="expandedRows.includes(item.id) ? 'bg-blue-50 text-blue-500' : 'group-hover:bg-slate-100'">
                                                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="expandedRows.includes(item.id) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mt-0.5 mr-6 whitespace-nowrap">/ {{ item.prices?.kecil?.unit }}</span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- EXPANDED ACCORDION ROW -->
                                <tr v-if="expandedRows.includes(item.id)" class="bg-[#F8FAFC]">
                                    <td colspan="5" class="p-0 border-b border-[#E8EEF6]">
                                        <div class="px-8 py-5">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="text-[10px] font-bold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    Detail Harga Per Satuan
                                                </div>
                                            </div>
                                            
                                            <div class="overflow-hidden rounded-xl border border-[#E8EEF6] bg-white shadow-sm w-full">
                                                <table class="w-full text-left">
                                                    <thead class="bg-[#F5F9FF] border-b border-[#E8EEF6]">
                                                        <tr>
                                                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Satuan</th>
                                                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Isi Konversi</th>
                                                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">HPP</th>
                                                            <th class="px-4 py-3 text-[10px] font-bold text-slate-800 uppercase tracking-widest whitespace-nowrap">Harga Jual</th>
                                                            <th class="px-4 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap text-right">Margin</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-[#E8EEF6]">
                                                        <template v-for="(priceData, level) in item.prices" :key="level">
                                                            <tr v-if="priceData" class="hover:bg-slate-50/50 transition-colors">
                                                                <td class="px-4 py-3.5">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-[12px] font-bold text-slate-800 uppercase">{{ priceData.unit }}</span>
                                                                        <span class="text-[10px] text-slate-400 capitalize">{{ (priceData.unit || '').toLowerCase() }}</span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3.5 text-[11px] font-medium text-slate-500 whitespace-nowrap">
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
                                                                <td class="px-4 py-3.5 text-[12px] text-slate-500 font-medium whitespace-nowrap">{{ formatCurrency(priceData.hpp) }}</td>
                                                                <td class="px-4 py-3.5 whitespace-nowrap">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-[13px] font-black text-slate-900">
                                                                            {{ priceData.selling > 0 ? formatCurrency(priceData.selling) : 'Rp 0' }}
                                                                        </span>
                                                                        <span v-if="level === 'kecil'" class="text-[9px] font-bold text-blue-500 mt-0.5">(utama)</span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                                                    <span v-if="priceData.selling > 0" 
                                                                          class="inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-bold rounded-md border whitespace-nowrap"
                                                                          :class="priceData.profit >= 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-100' : 'text-rose-700 bg-rose-50 border-rose-200'">
                                                                        {{ priceData.profit > 0 ? '+' : '' }}{{ priceData.profit }}%
                                                                    </span>
                                                                    <span v-else class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-bold text-slate-400 bg-slate-50 border border-slate-200 rounded-md uppercase">
                                                                        Belum
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <div class="mt-4 flex items-center gap-1.5 text-[11px] text-slate-500">
                                                <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>Harga jual utama yang digunakan di kasir adalah per <strong class="capitalize">{{ (item.prices?.kecil?.unit || '').toLowerCase() }}</strong>.</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                        
                        <!-- PAGINATION FAKTUR -->
                        <div v-if="fakturStocks.data && fakturStocks.data.length > 0" class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                Menampilkan {{ fakturStocks.data.length }} Obat Faktur
                            </span>
                        </div>
                    </div>

                    <!-- TABLE: KATALOG RACIKAN -->
                    <div v-if="activeTab === 'racikan'" class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] animate-in fade-in slide-in-from-right-4 duration-500">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-purple-50/30 border-b border-[#E8EEF6]">
                                    <th class="px-6 py-4 text-[10px] font-bold text-purple-600 uppercase tracking-widest whitespace-nowrap">Identitas Racikan</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-purple-600 uppercase tracking-widest whitespace-nowrap">Tgl Buat & BUD</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-purple-600 uppercase tracking-widest text-center whitespace-nowrap">Qty Hasil</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-purple-600 uppercase tracking-widest text-right whitespace-nowrap">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E8EEF6]">
                                <tr v-if="!racikanProducts.data || racikanProducts.data.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="w-16 h-16 bg-purple-50 border border-purple-100 rounded-[16px] flex items-center justify-center text-2xl mb-4 text-purple-300 grayscale">🧪</div>
                                            <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Belum Ada Produk Racikan</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="item in racikanProducts.data" :key="item.id" class="hover:bg-purple-50/30 transition-colors group">
                                    <!-- IDENTITAS -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-purple-50 rounded-[12px] flex items-center justify-center text-purple-600 font-bold text-xs border border-purple-100 group-hover:scale-105 transition-transform flex-shrink-0">
                                                RC
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-900 text-sm mb-1 line-clamp-1" :title="item.nama_produk">{{ item.nama_produk }}</span>
                                                <span class="text-[10px] font-mono font-bold text-purple-500 uppercase tracking-widest bg-purple-50 px-2 py-0.5 rounded w-fit">{{ item.satuan || 'Puyer' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- DATES -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-medium text-slate-400 w-8">Buat</span>
                                                <span class="text-xs font-bold text-slate-700">{{ new Date(item.tanggal_racik).toLocaleDateString('id-ID') }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-medium text-rose-400 w-8">BUD</span>
                                                <span class="text-xs font-bold text-rose-600">{{ new Date(item.bud_date).toLocaleDateString('id-ID') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- QTY -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="inline-flex items-center justify-center min-w-[3rem] px-3 py-1 bg-slate-100 border border-slate-200 rounded-[8px]">
                                            <span class="text-sm font-black text-slate-800 font-mono">{{ Number(item.qty_hasil) }}</span>
                                        </div>
                                    </td>
                                    <!-- HARGA & PROFIT -->
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex flex-col items-end gap-1">
                                            <!-- Harga Jual -->
                                            <span class="font-black text-slate-900 text-sm whitespace-nowrap">
                                                {{ item.harga_jual > 0 ? formatCurrency(item.harga_jual) : 'Rp 0' }}
                                            </span>
                                            <!-- Profit / Status -->
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-[10px] font-medium text-slate-400 whitespace-nowrap">HPP: {{ formatCurrency(item.hpp_per_unit) }}</span>
                                                <span v-if="item.harga_jual > 0" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded uppercase tracking-widest border border-emerald-100">
                                                    Profit {{ getRacikanProfit(item.hpp_per_unit, item.harga_jual) }}%
                                                </span>
                                                <span v-else class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded uppercase tracking-widest border border-rose-100">
                                                    Belum Diset
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- PAGINATION RACIKAN -->
                        <div v-if="racikanProducts.data && racikanProducts.data.length > 0" class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                Menampilkan {{ racikanProducts.data.length }} Obat Racikan
                            </span>
                        </div>
                    </div>
                </div>
            </div>
    </ControlLayout>
</template>
