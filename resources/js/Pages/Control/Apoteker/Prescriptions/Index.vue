<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

defineProps({ prescriptions: Object });

const statusColor = (s) => ({
    baru: 'bg-slate-50 text-slate-700 border-[#E8EEF6]',
    diproses: 'bg-blue-50 text-blue-700 border-blue-200',
    siap_ambil: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    selesai: 'bg-teal-50 text-teal-700 border-teal-200',
    batal: 'bg-rose-50 text-rose-700 border-rose-200',
}[s] || 'bg-slate-50 text-slate-600 border-[#E8EEF6]');

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';
</script>

<template>
    <Head title="Daftar Resep Aktif" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Daftar Resep Aktif</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Kelola dan proses resep pasien</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.create')" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[52px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Tambah Resep Baru
            </Link>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">No. Resep</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pasien</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Dokter</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Tgl Resep</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Jml Item</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8EEF6]">
                    <tr v-if="!prescriptions?.data?.length">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <div class="w-16 h-16 bg-slate-50 rounded-[16px] flex items-center justify-center text-slate-300 border border-[#E8EEF6]">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Belum ada resep aktif.</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="rx in (prescriptions?.data || [])" :key="rx.id" class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-6 py-4 font-mono font-bold text-slate-700 text-sm">{{ rx.prescription_number }}</td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ rx.patient?.name }}</td>
                        <td class="px-6 py-4 text-slate-600 font-medium text-sm">{{ rx.doctor_name }}</td>
                        <td class="px-6 py-4 text-center text-slate-500 text-sm font-medium">{{ formatDate(rx.prescription_date) }}</td>
                        <td class="px-6 py-4 text-center font-mono font-black text-slate-700 text-sm">{{ rx.prescription_items?.length || 0 }}</td>
                        <td class="px-6 py-4 text-center">
                            <span :class="statusColor(rx.status)" class="text-[9px] px-3 py-1.5 rounded-[12px] font-bold uppercase tracking-widest border block w-fit mx-auto whitespace-nowrap">
                                {{ rx.status ? rx.status.replace(/_/g, ' ') : 'unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <Link :href="route('control.apoteker.prescriptions.show', rx.id)" class="inline-flex items-center justify-center text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-600 hover:text-white h-[52px] px-6 rounded-[16px] text-[10px] font-bold uppercase tracking-widest transition-all active:scale-95">
                                Detail
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Halaman {{ prescriptions?.current_page || 1 }}</span>
                <div v-if="prescriptions?.links?.length > 3" class="flex items-center gap-1.5">
                    <template v-for="l in (prescriptions?.links || [])" :key="l.label">
                        <Link 
                            v-if="l.url" 
                            :href="l.url" 
                            :class="l.active ? 'bg-blue-600 text-white border-blue-600 shadow-[0_8px_24px_rgba(15,23,42,0.04)]' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-blue-500 hover:text-blue-600'" 
                            class="w-8 h-8 flex items-center justify-center rounded-[16px] text-xs font-bold transition-all border uppercase" 
                            v-html="l.label"
                        ></Link>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
