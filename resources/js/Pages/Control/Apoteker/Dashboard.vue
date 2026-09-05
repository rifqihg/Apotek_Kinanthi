<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardCharts from '@/Components/DashboardCharts.vue';

const props = defineProps({
    queueCount: { type: Number, default: 0 },
    activeSubmissions: { type: Array, default: () => [] },
    rawMaterialAlerts: { type: Array, default: () => [] }
});
</script>

<template>
    <Head title="Dashboard Apoteker" />

    <div class="space-y-8 animate-in fade-in duration-700">
        <!-- 4 KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            <!-- KPI: Antrean Racikan Aktif -->
            <div class="bg-slate-900 p-5 rounded-[24px] shadow-lg text-white relative overflow-hidden group border border-slate-800">
                <div class="absolute -right-3 -bottom-3 w-20 h-20 bg-emerald-500/10 rounded-full group-hover:scale-125 transition-transform duration-700"></div>
                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest block mb-4">Antrean Racikan</span>
                <div class="flex items-baseline gap-2 relative z-10">
                    <span class="text-4xl font-black tracking-tighter">{{ queueCount }}</span>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Proses</span>
                </div>
                <p class="mt-4 text-[9px] text-slate-400 font-black leading-relaxed uppercase tracking-widest relative z-10">Pengerjaan sediaan di tahap penimbangan gudang.</p>
            </div>

            <!-- KPI: Bahan Baku Kritis -->
            <div class="bg-white p-5 rounded-[24px] border border-rose-200/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] hover:shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all group">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Bahan Baku Kritis</span>
                    <div class="w-10 h-10 bg-rose-50 rounded-[16px] flex items-center justify-center border border-rose-100/50 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-rose-600 tracking-tighter">{{ rawMaterialAlerts.length }}</span>
                    <span class="text-[11px] font-bold text-rose-400 uppercase tracking-widest">Bahan</span>
                </div>
                <p class="mt-4 text-[9px] text-slate-400 font-extrabold uppercase tracking-widest leading-none">Ambang Batas Minimum Sistem</p>
            </div>

            <!-- KPI: Racikan Selesai -->
            <div class="bg-white p-5 rounded-[24px] border border-[#E8EEF6]/80 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Racikan Selesai</span>
                    <div class="w-10 h-10 bg-emerald-50 rounded-[16px] flex items-center justify-center border border-emerald-100/50">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-emerald-700 tracking-tighter">{{ activeSubmissions.filter(s => s.status === 'approved').length }}</span>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Batch</span>
                </div>
                <div class="mt-4 flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Tervalidasi Hari Ini</span>
                </div>
            </div>

            <!-- KPI: Verifikasi Admin -->
            <div class="bg-white p-5 rounded-[24px] border border-amber-200/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Verifikasi Admin</span>
                    <div class="w-10 h-10 bg-amber-50 rounded-[16px] flex items-center justify-center border border-amber-100/50">
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-amber-600 tracking-tighter">{{ activeSubmissions.filter(s => s.status === 'pending_final_verification').length }}</span>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Draf</span>
                </div>
                <p class="mt-4 text-[9px] text-slate-400 font-extrabold uppercase tracking-widest leading-none">Menunggu Approval Finansial</p>
            </div>
        </div>

        <!-- Dashboard Charts -->
        <DashboardCharts />

        <!-- Tabel: Monitor Antrean Compounding -->
        <div class="bg-white rounded-[1.5rem] border border-[#E8EEF6]/80 overflow-hidden shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div class="px-8 py-5 border-b border-[#E8EEF6] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-50/50">
                <div>
                    <h3 class="font-black text-slate-900 tracking-tight text-sm uppercase">Monitor Antrean Compounding</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase tracking-tighter font-bold">Daftar batch racikan yang sedang diproses farmasi.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left font-medium">
                    <thead>
                        <tr class="bg-white text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] border-b border-[#E8EEF6]">
                            <th class="px-8 py-5">Nama Racikan</th>
                            <th class="px-8 py-5">Resep / Formula</th>
                            <th class="px-8 py-5">Komposisi Bahan</th>
                            <th class="px-8 py-5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in activeSubmissions" :key="item.id" class="hover:bg-emerald-50/20 transition-all group">
                            <td class="px-8 py-5">
                                <span class="font-bold text-slate-800 text-sm block group-hover:text-emerald-700 transition-colors uppercase tracking-tighter leading-tight">{{ item.nama_racikan }}</span>
                                <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest mt-1 block">ID-{{ item.id }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-[10px] font-black px-3 py-1 bg-emerald-50 text-emerald-700 rounded-[16px] border border-emerald-100 uppercase tracking-widest">
                                    {{ item.formula?.nama_resep || 'Non-Formula' }}
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="detail in (item.details || []).slice(0, 3)"
                                        :key="detail.id"
                                        class="text-[9px] font-black text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md border border-[#E8EEF6] uppercase"
                                    >
                                        {{ detail.medicine?.nama_obat || detail.raw_material?.nama_bahan || '-' }}
                                    </span>
                                    <span v-if="(item.details || []).length > 3" class="text-[9px] font-black text-slate-400 uppercase">
                                        +{{ item.details.length - 3 }} Lainnya
                                    </span>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span
                                    class="px-3 py-1.5 rounded-[16px] text-[9px] font-black uppercase tracking-widest border shadow-[0_8px_24px_rgba(15,23,42,0.04)]"
                                    :class="{
                                        'bg-amber-50 text-amber-600 border-amber-200': item.status === 'pending_final_verification',
                                        'bg-emerald-50 text-emerald-600 border-emerald-200': item.status === 'active',
                                        'bg-rose-50 text-rose-600 border-rose-200': item.status === 'rejected',
                                        'bg-slate-50 text-slate-600 border-[#E8EEF6]': !['pending_final_verification','active','rejected'].includes(item.status)
                                    }"
                                >
                                    {{ item.status === 'pending_final_verification' ? 'Menunggu Harga' :
                                       item.status === 'active' ? 'Aktif' :
                                       item.status === 'rejected' ? 'Ditolak' : item.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="activeSubmissions.length === 0">
                            <td colspan="4" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 border border-emerald-100/50">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    </div>
                                    <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Tidak Ada Antrean Racikan Aktif</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Peringatan Bahan Baku Kritis -->
        <div v-if="rawMaterialAlerts.length > 0" class="bg-white rounded-[1.5rem] border border-rose-200/60 overflow-hidden shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div class="px-8 py-5 border-b border-rose-100 flex items-center gap-4 bg-rose-50/30">
                <div class="w-10 h-10 bg-white rounded-[16px] flex items-center justify-center border border-rose-100 shadow-[0_8px_24px_rgba(15,23,42,0.04)] text-rose-500 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 tracking-tight text-sm uppercase">Peringatan Stok Bahan Baku</h3>
                    <p class="text-[11px] text-rose-600 mt-0.5 uppercase tracking-tighter font-bold">Bahan baku di bawah ambang batas aman operasional.</p>
                </div>
            </div>
            <div class="divide-y divide-rose-100/50">
                <div v-for="material in rawMaterialAlerts" :key="material.id" class="px-8 py-4 flex items-center justify-between hover:bg-rose-50/30 transition-all group">
                    <div>
                        <span class="font-bold text-sm text-slate-800 uppercase tracking-tighter group-hover:text-rose-600 transition-colors">{{ material.nama_bahan }}</span>
                        <span class="block text-[10px] text-slate-400 mt-1 font-black uppercase tracking-widest">Sediaan: {{ material.bentuk_sediaan || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-black text-rose-600 text-lg tracking-tighter">{{ material.stok_total }}</span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Unit</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
