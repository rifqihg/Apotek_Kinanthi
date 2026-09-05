<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({ logs: Object });

const actionClass = (a) => ({
    'Adjustment': 'text-amber-600 bg-amber-50 border-amber-100',
    'Pemusnahan': 'text-rose-600 bg-rose-50 border-rose-100',
    'Restock': 'text-emerald-600 bg-emerald-50 border-emerald-100',
    'Initial': 'text-blue-600 bg-blue-50 border-blue-100'
}[a] || 'text-slate-600 bg-slate-50 border-[#E8EEF6]');
</script>

<template>
    <Head title="Audit Stok" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Log Audit & Mutasi Inventaris</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Transparansi penuh pergerakan stok non-transaksional</p>
            </div>
        </div>

        <!-- Table View -->
        <div class="bg-white border border-[#E8EEF6] rounded-[1.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-[0.15em] border-b border-[#E8EEF6]">
                        <tr>
                            <th class="px-8 py-5">Timestamp</th>
                            <th class="px-8 py-5">Entitas / Substrat</th>
                            <th class="px-8 py-5">Aksi Audit</th>
                            <th class="px-8 py-5 text-right">Delta (Perubahan)</th>
                            <th class="px-8 py-5 text-right">Saldo Akhir</th>
                            <th class="px-8 py-5 text-center">PIC / Verifikator</th>
                            <th class="px-8 py-5">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="7" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 border border-[#E8EEF6]">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2"></path></svg>
                                    </div>
                                    <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Belum Ada Log Audit</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/70 transition-all group">
                            <td class="px-8 py-5 whitespace-nowrap text-[10px] font-bold text-slate-400 uppercase tracking-tighter">
                                {{ new Date(log.created_at).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}
                            </td>
                            <td class="px-8 py-5">
                                <span class="font-black text-slate-900 text-sm uppercase tracking-tighter block leading-tight">{{ log.raw_material?.nama_bahan || log.medicine?.nama_obat }}</span>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">{{ log.raw_material_id ? 'Materia / Bahan Baku' : 'Sediaan Jadi / Obat' }}</p>
                            </td>
                            <td class="px-8 py-5">
                                <span :class="actionClass(log.action)" class="px-2.5 py-1 rounded-[16px] text-[9px] font-black border uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right font-black text-sm tabular-nums" :class="log.change_amount > 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ log.change_amount > 0 ? '+' : '' }}{{ Number(log.change_amount).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-8 py-5 text-right font-black text-slate-900 text-sm tabular-nums">
                                {{ Number(log.qty_after).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-7 h-7 bg-slate-100 rounded-full flex items-center justify-center text-[10px] font-black text-slate-500 border border-[#E8EEF6] uppercase">{{ log.user?.name?.charAt(0) }}</div>
                                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-tighter">{{ log.user?.name }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <div class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-[16px] text-[9px] font-black uppercase tracking-tight block w-fit truncate max-w-[150px]" :title="log.reason">
                                    {{ log.reason || '-' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="logs.links?.length > 3" class="flex justify-center gap-1.5 p-6 border-t border-[#E8EEF6]">
                <template v-for="l in logs.links" :key="l.label">
                    <button 
                        v-if="l.url" 
                        @click="router.get(l.url)" 
                        :class="l.active ? 'bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-emerald-500 hover:text-emerald-500'" 
                        class="w-8 h-8 flex items-center justify-center rounded-[16px] text-[10px] font-black transition-all border uppercase" 
                        v-html="l.label"
                    ></button>
                </template>
            </div>
        </div>
    </div>
</template>
