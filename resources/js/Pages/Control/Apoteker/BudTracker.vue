<script setup>
import { Head } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

defineProps({ submissions: Object });

const getBudStatus = (expiryDate) => {
    if (!expiryDate) return { label: 'Unknown', color: 'bg-slate-500', text: 'text-slate-700', bg: 'bg-slate-50', border: 'border-[#E8EEF6]', progress: 0 };
    const today = new Date();
    const expiry = new Date(expiryDate);
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return { label: 'Expired', color: 'bg-red-600', text: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200', progress: 0 };
    if (diffDays < 3) return { label: 'Kritis', color: 'bg-red-500', text: 'text-red-600', bg: 'bg-red-50', border: 'border-red-200', progress: (diffDays / 30) * 100 };
    if (diffDays < 7) return { label: 'Peringatan', color: 'bg-amber-500', text: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200', progress: (diffDays / 30) * 100 };
    return { label: 'Aman', color: 'bg-teal-500', text: 'text-teal-700', bg: 'bg-teal-50', border: 'border-teal-200', progress: (diffDays / 30) * 100 };
};

const formatDaysLeft = (expiryDate) => {
    if (!expiryDate) return '-';
    const today = new Date();
    const expiry = new Date(expiryDate);
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'Sudah Expired';
    return `${diffDays} hari lagi`;
};
</script>

<template>
    <Head title="ED Tracker" />
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Monitoring Beyond Use Date</h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Pelacakan masa pakai aman untuk obat racikan aktif</p>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <div v-if="!submissions?.data?.length" class="bg-white p-12 text-center rounded-[1.5rem] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] text-slate-400 font-bold uppercase text-[11px] tracking-widest">
                Tidak ada racikan aktif yang terpantau dalam grid BUD.
            </div>
            
            <div v-for="s in (submissions?.data || [])" :key="s.id" class="bg-white border border-[#E8EEF6] rounded-[1.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-6 hover:shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all group">
                <div class="flex flex-col md:flex-row justify-between gap-6">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-4">
                            <h3 class="text-base font-black text-slate-900 uppercase tracking-tighter">{{ s.nama_racikan || 'Tanpa Nama' }}</h3>
                            <span v-if="s.label" :class="[getBudStatus(s.label.tanggal_kadaluarsa_bud).bg, getBudStatus(s.label.tanggal_kadaluarsa_bud).text, getBudStatus(s.label.tanggal_kadaluarsa_bud).border]" class="px-2.5 py-1 rounded-[16px] text-[9px] font-black uppercase border tracking-widest">
                                {{ getBudStatus(s.label.tanggal_kadaluarsa_bud).label }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Apoteker</p>
                                <p class="text-xs font-bold text-slate-800 uppercase leading-none">{{ s.user?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Tanggal Racik</p>
                                <p class="text-xs font-bold text-slate-800 leading-none">{{ s.label?.tanggal_racik ? new Date(s.label.tanggal_racik).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-rose-500 uppercase tracking-widest mb-1">Batas BUD</p>
                                <p class="text-xs font-black text-rose-600 leading-none">{{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Aturan Pakai</p>
                                <p class="text-xs font-bold text-slate-700 italic leading-tight">"{{ s.label?.aturan_pakai || '-' }}"</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="s.label" class="w-full md:w-64 space-y-3">
                        <div class="flex justify-between items-end">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Status Masa Pakai</span>
                            <span :class="getBudStatus(s.label.tanggal_kadaluarsa_bud).text" class="text-[10px] font-black uppercase tracking-widest">{{ formatDaysLeft(s.label.tanggal_kadaluarsa_bud) }}</span>
                        </div>
                        <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden border border-[#E8EEF6] shadow-inner">
                            <div :class="getBudStatus(s.label.tanggal_kadaluarsa_bud).color" class="h-full transition-all duration-1000 shadow-[0_0_8px_rgba(0,0,0,0.1)]" :style="{ width: `${Math.min(100, Math.max(5, getBudStatus(s.label.tanggal_kadaluarsa_bud).progress))}%` }"></div>
                        </div>
                        <p class="text-[8px] text-slate-400 text-right font-black uppercase tracking-tighter">Estimasi: {{ s.label.bud_hari || 0 }} Hari Operasional</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="submissions?.links?.length > 3" class="flex justify-center gap-1.5 pt-6">
            <template v-for="l in (submissions?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-emerald-500 hover:text-emerald-500'" 
                    class="w-9 h-9 flex items-center justify-center rounded-[16px] text-xs font-black transition-all border uppercase" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>
</template>
