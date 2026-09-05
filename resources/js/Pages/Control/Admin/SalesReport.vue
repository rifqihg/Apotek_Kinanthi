<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

const props = defineProps({
    medicines: Object,
    filters: Object,
    summary: Object
});

const form = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || ''
});

// Watch for date changes and auto-submit
watch(form, (newVal) => {
    if (newVal.start_date && newVal.end_date) {
        if (newVal.start_date <= newVal.end_date) {
            router.get(route('control.admin.reports.sales'), {
                start_date: newVal.start_date,
                end_date: newVal.end_date
            }, {
                preserveState: true,
                preserveScroll: true,
                replace: true
            });
        }
    }
}, { deep: true });

const exportToExcel = () => {
    const url = new URL(route('control.admin.reports.sales.export'));
    if (form.value.start_date) url.searchParams.append('start_date', form.value.start_date);
    if (form.value.end_date) url.searchParams.append('end_date', form.value.end_date);
    window.location.href = url.toString();
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};
</script>

<template>
<Head title="Laporan Penjualan (Rekap Produk)" />

<ControlLayout>
<div class="space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Laporan Penjualan</h2>
            <p class="text-slate-500 text-sm mt-1">Rekapitulasi penjualan berdasarkan produk obat.</p>
        </div>

        <div class="flex items-center gap-3">
            <button @click="exportToExcel" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-xl transition-all shadow-sm hover:shadow-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Excel
            </button>
        </div>
    </div>

    <!-- Filters & Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Date Filters -->
        <div class="md:col-span-4 bg-white border border-[#E8EEF6] p-4 rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Filter Periode</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Mulai</label>
                    <input type="date" v-model="form.start_date" class="w-full border-slate-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Sampai</label>
                    <input type="date" v-model="form.end_date" class="w-full border-slate-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                </div>
            </div>
        </div>

        <div class="md:col-span-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white border border-[#E8EEF6] p-4 rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex flex-col justify-center">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Item Terjual</p>
                <p class="text-2xl font-black text-blue-600">{{ summary.total_items }} <span class="text-sm font-bold text-slate-400">PCS</span></p>
            </div>
            <div class="bg-white border border-[#E8EEF6] p-4 rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex flex-col justify-center">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Penjualan</p>
                <p class="text-xl font-black text-slate-800">{{ formatCurrency(summary.total_amount) }}</p>
            </div>
            <div class="bg-white border border-[#E8EEF6] p-4 rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex flex-col justify-center">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Laba</p>
                <p class="text-xl font-black text-green-600">{{ formatCurrency(summary.total_laba) }}</p>
            </div>
        </div>
    </div>

    <!-- Table Data -->
    <div class="bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-blue-900 text-white text-[10px] uppercase font-bold border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-4 py-4 text-center border-r border-blue-800/50">No</th>
                        <th class="px-4 py-4 border-r border-blue-800/50">Nama Produk</th>
                        <th class="px-4 py-4 text-center border-r border-blue-800/50">Satuan</th>
                        <th class="px-4 py-4 text-right border-r border-blue-800/50">Modal</th>
                        <th class="px-4 py-4 text-right border-r border-blue-800/50">Harga Jual</th>
                        <th class="px-4 py-4 text-center border-r border-blue-800/50 bg-blue-800/30">Qty Awal</th>
                        <th class="px-4 py-4 text-center border-r border-blue-800/50 bg-blue-800/50">Terjual</th>
                        <th class="px-4 py-4 text-center border-r border-blue-800/50 bg-blue-800/30">Sisa Stok</th>
                        <th class="px-4 py-4 text-right border-r border-blue-800/50">Total Penjualan</th>
                        <th class="px-4 py-4 text-right border-r border-blue-800/50">Total Modal</th>
                        <th class="px-4 py-4 text-right border-r border-blue-800/50">Laba</th>
                        <th class="px-4 py-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!medicines.data.length">
                        <td colspan="12" class="px-6 py-12 text-center">
                            <p class="text-slate-500 font-medium">Tidak ada data obat.</p>
                        </td>
                    </tr>
                    <template v-for="(med, index) in medicines.data" :key="med.id">
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-center text-slate-500 font-medium border-r border-slate-50">{{ (medicines.current_page - 1) * medicines.per_page + index + 1 }}</td>
                            <td class="px-4 py-3 text-slate-800 font-bold border-r border-slate-50">{{ med.nama_produk }}</td>
                            <td class="px-4 py-3 text-center text-slate-600 border-r border-slate-50">{{ med.satuan }}</td>
                            <td class="px-4 py-3 text-right text-slate-600 border-r border-slate-50">{{ formatCurrency(med.modal) }}</td>
                            <td class="px-4 py-3 text-right text-slate-600 border-r border-slate-50">{{ formatCurrency(med.harga_jual) }}</td>
                            
                            <td class="px-4 py-3 text-center text-slate-700 bg-slate-50/50 border-r border-slate-100">{{ med.qty_awal }}</td>
                            <td class="px-4 py-3 text-center font-bold text-blue-600 bg-blue-50/30 border-r border-blue-50">{{ med.terjual }}</td>
                            <td class="px-4 py-3 text-center text-slate-700 bg-slate-50/50 border-r border-slate-100">{{ med.sisa_stok }}</td>
                            
                            <td class="px-4 py-3 text-right font-bold text-slate-800 border-r border-slate-50">{{ formatCurrency(med.total_penjualan) }}</td>
                            <td class="px-4 py-3 text-right text-slate-600 border-r border-slate-50">{{ formatCurrency(med.total_modal) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-green-600 border-r border-slate-50">{{ formatCurrency(med.laba) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="med.status === 'Tersedia'" class="inline-flex items-center justify-center px-2 py-1 bg-green-100 text-green-700 rounded-md text-[10px] font-bold uppercase tracking-wider">
                                    Tersedia
                                </span>
                                <span v-else class="inline-flex items-center justify-center px-2 py-1 bg-red-100 text-red-700 rounded-md text-[10px] font-bold uppercase tracking-wider">
                                    Pesan
                                </span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            
            <!-- PAGINATION -->
            <div v-if="medicines.data && medicines.data.length > 0" class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    Halaman {{ medicines.current_page }} dari {{ medicines.last_page }}
                </span>
                
                <div class="flex items-center gap-1">
                    <Link v-for="(link, i) in medicines.links" :key="i"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-lg transition-colors',
                            link.active ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-200',
                            !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>
</div>
</ControlLayout>
</template>
