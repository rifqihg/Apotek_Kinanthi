<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

const formatDateTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';

const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '';

const props = defineProps({
    opname: Object,
});

const adjustStock = () => {
    if (confirm('Anda yakin ingin memfinalisasi opname ini dan menyesuaikan stok aktual di sistem? Tindakan ini tidak dapat dibatalkan.')) {
        router.post(route('control.admin.stock-opname.adjust', props.opname.id));
    }
};
</script>

<template>
    <Head title="Detail Stock Opname" />

    <ControlLayout>
        <template #header-title>
            <div class="flex items-center gap-3">
                <Link :href="route('control.admin.stock-opname.index')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <span>Detail Stock Opname</span>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header Info -->
            <div class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-black text-slate-800">
                            Opname {{ formatDate(opname.opname_date) }}
                        </h2>
                        <span v-if="opname.status === 'completed'" class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">Selesai / Disesuaikan</span>
                        <span v-else class="bg-amber-50 text-amber-600 border border-amber-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">Draft</span>
                    </div>
                    <div class="text-sm text-slate-500">
                        <p><strong>Dibuat Oleh:</strong> {{ opname.creator?.name }}</p>
                        <p v-if="opname.catatan"><strong>Keterangan:</strong> {{ opname.catatan }}</p>
                        <p v-if="opname.status === 'completed' && opname.finalizer"><strong>Difinalisasi Oleh:</strong> {{ opname.finalizer.name }} ({{ formatDateTime(opname.finalized_at) }})</p>
                    </div>
                </div>
                
                <div v-if="opname.status === 'draft'">
                    <button @click="adjustStock" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-[16px] transition-colors shadow-[0_8px_24px_rgba(15,23,42,0.04)] w-full md:w-auto">
                        Finalisasi & Sesuaikan Stok
                    </button>
                    <p class="text-xs text-slate-400 mt-2 text-center">Tindakan ini akan mengubah qty di sistem.</p>
                </div>
            </div>

            <!-- Detail Items Table -->
            <div class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Nama Item</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-center">Jenis</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Qty Sistem</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Qty Fisik</th>
                            <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in opname.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-700">
                                <template v-if="item.medicine_id">{{ item.medicine?.nama_obat }}</template>
                                <template v-else-if="item.raw_material_id">{{ item.raw_material?.nama_bahan }}</template>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="item.medicine_id" class="uppercase font-bold text-[10px] bg-sky-50 text-sky-600 px-2 py-1 rounded-[16px] tracking-wider">Obat Jadi</span>
                                <span v-else-if="item.raw_material_id" class="uppercase font-bold text-[10px] bg-amber-50 text-amber-600 px-2 py-1 rounded-[16px] tracking-wider">Bahan Baku</span>
                            </td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-600">
                                {{ Number(item.system_stock).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-6 py-4 text-right font-black text-slate-800">
                                {{ Number(item.physical_stock).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-6 py-4 text-right font-black">
                                <span v-if="item.difference < 0" class="text-red-600 bg-red-50 px-2 py-1 rounded-[16px]">{{ Number(item.difference).toLocaleString('id-ID') }}</span>
                                <span v-else-if="item.difference > 0" class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded-[16px]">+{{ Number(item.difference).toLocaleString('id-ID') }}</span>
                                <span v-else class="text-slate-400">0</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </ControlLayout>
</template>
