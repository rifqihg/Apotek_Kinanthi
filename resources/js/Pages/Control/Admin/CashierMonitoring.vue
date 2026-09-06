<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

const props = defineProps({
    filters: Object,
    kpis: Object,
    transactions: Array,
});

const form = ref({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const expandedRows = ref({});

const toggleRow = (id) => { expandedRows.value[id] = !expandedRows.value[id]; };

const filterData = () => {
    router.get(route('control.admin.reports.cashier-monitoring'), form.value, { preserveState: true });
};

watch(() => [form.value.start_date, form.value.end_date], filterData);

const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
</script>

<template>
    <Head title="Monitoring Transaksi" />

    <ControlLayout>
        <template #header-title>Monitoring Transaksi</template>
        <template #header-subtitle>Pantau riwayat transaksi, pergerakan stok, dan keuntungan (laba)</template>

        <!-- Filter -->
        <div class="mb-6 bg-white rounded-[24px] border border-[#E8EEF6] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 pl-1">Dari Tanggal</label>
                    <input type="date" v-model="form.start_date" class="w-full h-[52px] rounded-[16px] border-[#E8EEF6] focus:border-blue-500 focus:ring focus:ring-blue-200/50 transition-all font-medium text-slate-700">
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 pl-1">Sampai Tanggal</label>
                    <input type="date" v-model="form.end_date" class="w-full h-[52px] rounded-[16px] border-[#E8EEF6] focus:border-blue-500 focus:ring focus:ring-blue-200/50 transition-all font-medium text-slate-700">
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Transaksi</p>
                <p class="text-3xl font-black text-slate-800">{{ kpis.total_transactions }}</p>
            </div>
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Penjualan</p>
                <p class="text-3xl font-black text-blue-600">{{ formatRupiah(kpis.total_sales) }}</p>
            </div>
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Diskon</p>
                <p class="text-3xl font-black text-rose-600">{{ formatRupiah(kpis.total_discount) }}</p>
            </div>
            <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Laba</p>
                <p class="text-3xl font-black text-emerald-600">{{ formatRupiah(kpis.total_laba) }}</p>
            </div>
        </div>

        <!-- Tabel Riwayat Transaksi -->
        <div class="bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] mb-8 overflow-hidden">
            <div class="p-6 border-b border-[#E8EEF6] flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800">Laporan Transaksi & Pergerakan Stok</h2>
                
                <a :href="route('control.admin.reports.sales.export', { start_date: form.start_date, end_date: form.end_date })" 
                   class="inline-flex items-center justify-center h-10 px-4 rounded-[12px] bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white font-bold text-sm transition-colors border border-emerald-100 hover:border-emerald-500 gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Excel
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                            <th class="w-12 px-4 py-4 text-center"></th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Waktu</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">No Transaksi</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Modal (HPP)</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Subtotal</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Diskon</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Penjualan</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Laba</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EEF6]">
                        <template v-for="data in transactions" :key="data.id">
                            <!-- Main Row -->
                            <tr @click="toggleRow(data.id)" class="hover:bg-slate-50/50 transition-colors cursor-pointer group">
                                <td class="px-4 py-4 text-center">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 group-hover:bg-blue-100 flex items-center justify-center text-slate-400 group-hover:text-blue-600 transition-colors mx-auto">
                                        <svg :class="{'rotate-180': expandedRows[data.id]}" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-600">{{ formatDate(data.created_at) }}</td>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-900">{{ data.sale_number }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">Kasir: {{ data.kasir_name }}</p>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-slate-500">{{ formatRupiah(data.modal) }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-600">{{ formatRupiah(data.subtotal) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <span v-if="data.discount_amount > 0" class="font-bold text-rose-600 text-sm">
                                        {{ formatRupiah(data.discount_amount) }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400">Rp0</span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-blue-600">{{ formatRupiah(data.total_amount) }}</td>
                                <td class="px-6 py-4 text-right font-black text-emerald-600">{{ formatRupiah(data.laba) }}</td>
                            </tr>
                            
                            <!-- Expanded Details Row -->
                            <tr v-show="expandedRows[data.id]" class="bg-slate-50/50">
                                <td colspan="8" class="p-0 border-t-0">
                                    <div class="px-16 py-6 bg-gradient-to-r from-slate-50 to-white shadow-inner">
                                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Rincian Obat & Stok Keluar</h3>
                                        
                                        <div class="bg-white border border-[#E8EEF6] rounded-[16px] overflow-hidden">
                                            <table class="w-full text-left text-sm">
                                                <thead class="bg-slate-50 border-b border-[#E8EEF6]">
                                                    <tr>
                                                        <th class="px-4 py-3 font-bold text-slate-600">Item Terjual</th>
                                                        <th class="px-4 py-3 font-bold text-slate-600 text-center">Kategori</th>
                                                        <th class="px-4 py-3 font-bold text-slate-600 text-center">Qty Keluar</th>
                                                        <th class="px-4 py-3 font-bold text-slate-600 text-right">Modal/HPP Total</th>
                                                        <th class="px-4 py-3 font-bold text-slate-600 text-right">Harga Jual Total</th>
                                                        <th class="px-4 py-3 font-bold text-slate-600 text-center">Sisa Stok Real-time</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-[#E8EEF6]">
                                                    <tr v-for="item in data.items" :key="item.id" class="hover:bg-slate-50">
                                                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.item_name }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-widest rounded-full" 
                                                                :class="item.item_type === 'racikan' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                                                {{ item.item_type === 'racikan' ? 'Racikan' : 'Obat Jadi' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center font-bold text-slate-700">
                                                            {{ item.quantity }} <span class="text-xs text-slate-400 font-medium ml-1">{{ item.unit }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-medium text-slate-500">{{ formatRupiah(item.modal) }}</td>
                                                        <td class="px-4 py-3 text-right font-bold text-slate-700">{{ formatRupiah(item.subtotal) }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            <div v-if="item.item_type === 'obat_jadi'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[8px]" :class="item.sisa_stok <= 10 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-700'">
                                                                <span class="font-black">{{ item.sisa_stok }}</span>
                                                                <span class="text-[10px] font-bold uppercase tracking-widest">{{ item.unit }}</span>
                                                            </div>
                                                            <span v-else class="text-xs text-slate-400">-</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="transactions.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400 font-medium text-sm">Belum ada aktivitas penjualan dalam rentang tanggal yang dipilih.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </ControlLayout>
</template>
