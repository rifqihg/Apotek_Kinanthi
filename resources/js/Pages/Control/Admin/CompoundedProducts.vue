<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ products: Object });
const expandedId = ref(null);
function toggle(id) { expandedId.value = expandedId.value === id ? null : id; }

function budClass(bud) {
    const d = new Date(bud), now = new Date();
    const diff = (d - now) / (1000*60*60*24);
    if (diff < 0) return 'bg-gray-100 text-gray-500 border-gray-200';
    if (diff < 30) return 'bg-red-50 text-red-600 border-red-200';
    if (diff < 90) return 'bg-amber-50 text-amber-600 border-amber-200';
    return 'bg-teal-50 text-teal-600 border-teal-200';
}
function budLabel(bud) {
    const d = new Date(bud), now = new Date();
    const diff = (d - now) / (1000*60*60*24);
    if (diff < 0) return 'Expired';
    if (diff < 30) return '< 1 Bulan';
    if (diff < 90) return '< 3 Bulan';
    return 'Aman';
}
</script>

<template>
<Head title="Master Obat Racikan" />
<ControlLayout title="Master Data Racikan">
<div class="p-4 lg:p-6 space-y-6 max-w-7xl mx-auto">
    <div class="relative p-10 bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] overflow-hidden group">
        <div class="absolute top-0 right-0 w-80 h-80 bg-teal-50 rounded-full -mr-40 -mt-40 transition-transform group-hover:scale-110 duration-1000"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-emerald-50 rounded-full -ml-20 -mb-20 opacity-50"></div>
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <span class="px-4 py-1.5 bg-teal-100 text-teal-700 text-[10px] font-black uppercase tracking-widest rounded-full shadow-[0_8px_24px_rgba(15,23,42,0.04)]">Master Data</span>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight leading-none">Data Obat Racikan</h1>
                <p class="text-slate-500 font-medium">Produk hasil racikan Apoteker — di-tracking berdasarkan <span class="font-semibold text-slate-700 underline decoration-teal-300 decoration-2 underline-offset-4">Beyond Use Date (BUD)</span></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-white/50 backdrop-blur-md border border-white p-6 rounded-[2.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-6">
                    <div class="text-right">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Racikan</p>
                        <p class="text-3xl font-black text-teal-600 leading-none mt-1">{{ products.total }}</p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-teal-500 to-teal-600 rounded-[24px] flex items-center justify-center text-2xl shadow-xl shadow-teal-500/20 text-white">🧪</div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-[#E8EEF6]">
                        <th class="text-left px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama Produk</th>
                        <th class="text-center px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Batch</th>
                        <th class="text-center px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Tgl Racik</th>
                        <th class="text-center px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">BUD Date</th>
                        <th class="text-right px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Qty</th>
                        <th class="text-center px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status BUD</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <template v-for="p in products.data" :key="p.id">
                        <tr class="hover:bg-slate-50/50 transition-all group cursor-pointer" @click="toggle(p.id)">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-[16px] bg-slate-50 border border-[#E8EEF6] flex items-center justify-center transition-all group-hover:bg-teal-50 group-hover:border-teal-100">
                                        <svg class="w-4 h-4 text-slate-400 transition-transform group-hover:text-teal-600" :class="expandedId===p.id?'rotate-90 text-teal-600':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                    <p class="font-black text-slate-900">{{ p.nama_produk }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="px-3 py-1.5 bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest rounded-[16px] border border-[#E8EEF6] font-mono">{{ p.batch_no }}</span>
                            </td>
                            <td class="px-6 py-5 text-center text-xs font-bold text-slate-500">{{ new Date(p.tanggal_racik).toLocaleDateString('id-ID') }}</td>
                            <td class="px-6 py-5 text-center font-black text-slate-900">{{ new Date(p.bud_date).toLocaleDateString('id-ID') }}</td>
                            <td class="px-6 py-5 text-right font-black text-lg text-slate-900">{{ Number(p.qty_hasil).toLocaleString('id-ID') }} {{ p.satuan }}</td>
                            <td class="px-8 py-5 text-center">
                                <span :class="budClass(p.bud_date)" class="text-[9px] px-3 py-1.5 rounded-[16px] font-black uppercase tracking-widest border shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                                    {{ budLabel(p.bud_date) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="expandedId===p.id" class="bg-slate-50/30">
                            <td colspan="6" class="px-10 py-8">
                                <div class="bg-white p-8 rounded-[2.5rem] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] relative overflow-hidden grid grid-cols-2 md:grid-cols-4 gap-8">
                                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-teal-50 rounded-full transition-transform"></div>
                                    <div class="relative"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Apoteker</p><p class="font-black text-slate-900">{{ p.submission?.user?.name || '-' }}</p></div>
                                    <div class="relative"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Racikan Asal</p><p class="font-black text-slate-900">{{ p.submission?.nama_racikan || '-' }}</p></div>
                                    <div class="relative"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Harga Jual</p><p class="font-black text-teal-600 text-lg">Rp {{ Number(p.harga_jual).toLocaleString('id-ID') }}</p></div>
                                    <div class="relative"><p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Dibuat Oleh</p><p class="font-black text-slate-900">{{ p.creator?.name || '-' }}</p></div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div v-if="!products.data?.length" class="p-20 flex flex-col items-center justify-center text-center space-y-8">
            <div class="relative">
                <div class="absolute inset-0 bg-teal-500 blur-3xl opacity-10 scale-150 rounded-full"></div>
                <div class="relative w-32 h-32 bg-gradient-to-br from-teal-50 to-white rounded-[24px] shadow-2xl border border-teal-100 flex items-center justify-center text-6xl transform transition-transform hover:scale-110 duration-500">🧪</div>
            </div>
            <div class="space-y-3">
                <h4 class="text-2xl font-black text-slate-900 tracking-tight">Belum Ada Produk Racikan</h4>
                <p class="text-slate-400 font-medium max-w-sm mx-auto leading-relaxed">Produk racikan akan muncul di sini setelah proses peracikan diselesaikan oleh Apoteker dan dikonfirmasi oleh sistem.</p>
            </div>
        </div>
    </div>
</div>
</ControlLayout>
</template>
