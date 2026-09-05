<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ submission: Object });

const statusClass = (s) => ({
    pending_final_verification: 'bg-amber-50 text-amber-600 border-amber-200',
    active: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    rejected: 'bg-rose-50 text-rose-600 border-rose-200',
    cancelled: 'bg-slate-100 text-slate-500 border-[#E8EEF6]'
}[s] || 'bg-slate-50 text-slate-600 border-[#E8EEF6]');

const showCancelModal = ref(false);

function confirmCancel() {
    showCancelModal.value = true;
}

function proceedCancel() {
    router.post(route('control.apoteker.submissions.cancel', props.submission.id), {}, {
        onFinish: () => showCancelModal.value = false
    });
}
</script>

<template>
    <Head :title="`Detail: ${submission.nama_racikan}`" />
    <div class="space-y-6">
        <!-- Back Button & Header -->
        <div class="flex flex-col gap-4">
            <div>
                <Link :href="route('control.apoteker.submissions')" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-emerald-600 transition-all mb-4 group">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali Ke Log Racikan
                </Link>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 rounded-[1.2rem] bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-lg shadow-slate-900/10 active:scale-95 transition-all">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">{{ submission.nama_racikan }}</h2>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight mt-1.5 flex items-center gap-2">
                                PIC: <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{{ submission.user?.name }}</span> 
                                <span class="text-slate-200">/</span> 
                                Basis: <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">{{ submission.formula?.nama_resep || 'Kustom' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button v-if="submission.status === 'pending_final_verification'" @click="confirmCancel" class="px-5 py-2.5 bg-white border border-rose-100 text-rose-600 hover:bg-rose-50 rounded-[16px] text-[10px] font-black uppercase tracking-widest transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95">
                            Batalkan
                        </button>
                        <span :class="statusClass(submission.status)" class="text-[10px] px-5 py-2.5 rounded-[16px] font-black uppercase tracking-[0.15em] border shadow-[0_8px_24px_rgba(15,23,42,0.04)]">{{ submission.status === 'pending_final_verification' ? 'Menunggu Harga' : submission.status?.replace(/_/g, ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Case: Pending Pricing (Admin will set the price) -->
        <div v-if="submission.status === 'pending_final_verification'" class="bg-amber-50 border border-amber-200 rounded-[2rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-8">
            <h3 class="text-amber-800 font-black text-sm uppercase tracking-tight mb-2 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Menunggu Penetapan Harga oleh Admin
            </h3>
            <p class="text-[11px] font-bold text-amber-700 uppercase tracking-tight">
                Pengajuan racikan telah selesai dan stok bahan baku sudah dikurangi. Admin akan menetapkan biaya jasa, kemasan, margin, dan harga jual final.
            </p>
        </div>


        <!-- Case: Approved Warning -->
        <div v-if="submission.status === 'approved'" class="bg-emerald-900 text-white rounded-[2rem] p-8 shadow-2xl relative overflow-hidden group">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
            <div class="flex items-start gap-6 relative z-10">
                <div class="w-12 h-12 rounded-[24px] bg-white/10 flex items-center justify-center shrink-0 text-2xl shadow-inner border border-white/10 shrink-0">
                    💡
                </div>
                <div class="space-y-4">
                    <div>
                        <h4 class="font-black text-sm uppercase tracking-widest text-emerald-400">Protokol Produksi Tervalidasi</h4>
                        <p class="text-emerald-100/70 text-xs font-bold uppercase tracking-tight leading-relaxed mt-2">
                            Bahan baku dianggap telah keluar dari grid steril. Jika terjadi kegagalan produksi atau pembatalan setelah persetujuan, 
                            <span class="text-white">gunakan modul pemusnahan</span> untuk audit stok yang akurat.
                        </p>
                    </div>
                    <Link :href="route('control.apoteker.disposals')" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 px-5 py-2.5 rounded-[16px] text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-950/20 active:scale-95">
                        Buka Modul Pemusnahan
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </Link>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Composition Board -->
            <div class="bg-white border border-[#E8EEF6] rounded-[2rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        <h3 class="text-slate-800 font-black text-sm uppercase tracking-tight">Komposisi Bahan ({{ submission.details?.length }} Items)</h3>
                    </div>
                </div>
                <div class="divide-y divide-slate-100 flex-1 overflow-y-auto min-h-[400px] scrollbar-v-minimal">
                    <div v-for="d in submission.details" :key="d.id" class="px-8 py-5 hover:bg-emerald-50/20 transition-all group overflow-x-auto">
                        <div class="flex items-start justify-between gap-6 min-w-[500px]">
                            <div class="space-y-3">
                                <p class="text-slate-900 font-black text-sm uppercase tracking-tight leading-tight group-hover:text-emerald-700 transition-colors">{{ d.raw_material?.nama_bahan || d.medicine?.nama_obat }}</p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex items-center gap-1.5 bg-slate-100 px-2 py-0.5 rounded border border-[#E8EEF6]">
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Awal:</span>
                                        <span class="text-[10px] font-black text-slate-700 tabular-nums">{{ Number(d.qty_needed).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 bg-rose-50 px-2 py-0.5 rounded border border-rose-100">
                                        <span class="text-[8px] font-black text-rose-400 uppercase tracking-widest">Loss:</span>
                                        <span class="text-[10px] font-black text-rose-700 tabular-nums">{{ Number(d.wastage_percentage).toLocaleString('id-ID', { maximumFractionDigits: 1 }) }}%</span>
                                    </div>
                                </div>
                                <div v-if="d.suggested_batch_no" class="inline-flex items-center gap-3 bg-emerald-50 border border-emerald-100 text-emerald-700 px-3 py-1.5 rounded-[16px]">
                                    <span class="uppercase font-black text-[8px] tracking-[0.2em] bg-white px-1.5 py-0.5 border border-emerald-200 rounded shadow-[0_8px_24px_rgba(15,23,42,0.04)]">FEFO</span> 
                                    <span class="text-[10px] font-black font-mono tracking-tighter uppercase">Batch: {{ d.suggested_batch_no }} / Exp: {{ new Date(d.suggested_expired_date).toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}) }}</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest mb-1.5">Massa Neto</p>
                                <div class="inline-block bg-slate-900 text-white font-black text-xs h-[52px] px-6 rounded-[16px] shadow-lg shadow-slate-900/10 tabular-nums uppercase">
                                    {{ Number(d.qty_total_deducted).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ d.raw_material?.satuan_ukur || d.medicine?.satuan_stok }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Labels & Notes -->
            <div class="space-y-6">
                <!-- Etiket Card -->
                <div v-if="submission.label" class="bg-white border border-[#E8EEF6] rounded-[2rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-8 relative overflow-hidden group transition-all hover:border-emerald-200">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-emerald-50 rounded-full scale-0 group-hover:scale-100 transition-transform duration-700 opacity-50"></div>
                    
                    <h3 class="text-slate-800 font-black text-sm uppercase tracking-tight mb-8 flex items-center gap-3 relative z-10">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        Informasi Etiket Validasi
                    </h3>
                    
                    <div class="space-y-4 relative z-10">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-slate-50 p-4 rounded-[24px] border border-[#E8EEF6]">
                                <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Tanggal Produksi</span>
                                <span class="text-xs font-black text-slate-900 uppercase tracking-tighter">{{ new Date(submission.label.tanggal_racik).toLocaleDateString('id-ID', {day:'numeric',month:'long',year:'numeric'}) }}</span>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-[24px] border border-[#E8EEF6]">
                                <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Interval BUD</span>
                                <span class="text-xs font-black text-slate-900 uppercase tracking-tighter">{{ submission.label.bud_hari }} Hari Kalender</span>
                            </div>
                        </div>

                        <div class="bg-emerald-900 p-5 rounded-[24px] border border-emerald-800 shadow-xl shadow-emerald-950/20">
                            <span class="block text-[9px] font-black text-emerald-400 uppercase tracking-widest mb-2">Batas Masa Pakai (Beyond Use Date)</span>
                            <span class="text-lg font-black text-white uppercase tracking-tighter">{{ submission.label?.tanggal_kadaluarsa_bud ? new Date(submission.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID', {day:'numeric',month:'long',year:'numeric'}) : '-' }}</span>
                        </div>

                        <div class="bg-slate-50 p-5 rounded-[24px] border border-[#E8EEF6] space-y-4">
                            <div>
                                <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Aturan Pakai / Signa</span>
                                <span class="text-sm font-black text-slate-900 uppercase tracking-tight leading-tight">{{ submission.label?.aturan_pakai || '-' }}</span>
                            </div>
                            <div class="pt-4 border-t border-[#E8EEF6]/60 border-dashed">
                                <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Entitas Pasien</span>
                                <span class="text-xs font-black text-slate-900 uppercase tracking-tighter">{{ submission.label?.nama_pasien || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Tracking -->
                <div class="bg-white border border-[#E8EEF6] rounded-[2rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-8 space-y-8">
                    <h3 class="text-slate-800 font-black text-sm uppercase tracking-tight flex items-center gap-3">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Workflow & Feedback
                    </h3>
                    
                    <div class="space-y-6 relative ml-4 pl-8 border-l-2 border-[#E8EEF6]">
                        <!-- Submission Note -->
                        <div class="relative">
                            <div class="absolute -left-[41px] top-0 w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[8px] font-black shadow-lg">1</div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Original Apoteker Note</p>
                            <div class="bg-slate-50 rounded-[24px] p-4 border border-[#E8EEF6]">
                                <p class="text-xs font-bold text-slate-600 uppercase tracking-tight leading-relaxed italic">"{{ submission.apoteker_note || 'Tidak ada catatan instruksi.' }}"</p>
                            </div>
                        </div>

                        <!-- Admin Response -->
                        <div class="relative">
                            <div class="absolute -left-[41px] top-0 w-5 h-5 rounded-full" :class="submission.admin_note ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Evaluasi Finansial / Admin</p>
                            <div class="bg-slate-50 rounded-[24px] p-4 border border-[#E8EEF6]">
                                <p class="text-xs font-bold text-slate-600 uppercase tracking-tight leading-relaxed">{{ submission.admin_note || 'Protokol sedang dalam tahap review admin.' }}</p>
                            </div>
                        </div>

                        <!-- Processor -->
                        <div v-if="submission.approved_at" class="relative">
                            <div class="absolute -left-[41px] top-0 w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center text-white shadow-lg overflow-hidden">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Validasi Final Selesai</p>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black border border-[#E8EEF6]">{{ submission.approver?.name?.charAt(0) }}</div>
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-black text-slate-800 uppercase tracking-tighter">{{ submission.approver?.name || 'Admin' }}</span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ submission.approved_at ? new Date(submission.approved_at).toLocaleString('id-ID') : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pembatalan -->
    <Teleport to="body">
        <div v-if="showCancelModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity" @click="showCancelModal = false"></div>
            <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-md p-10 text-center transform transition-all border border-[#E8EEF6]">
                <div class="w-24 h-24 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner border border-rose-100">
                    <span class="text-4xl animate-bounce">⚠️</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tighter mb-3">Batalkan Protokol?</h3>
                <p class="text-slate-500 text-[11px] font-bold uppercase tracking-tight leading-relaxed mb-10">
                    Ini akan mengembalikan alokasi stok ke batch FEFO. Tindakan ini hanya tersedia sebelum persetujuan admin.
                </p>
                
                <div class="flex flex-col gap-4">
                    <button @click="proceedCancel" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-4 rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-rose-200 transition-all active:scale-95">
                        Ya, Batalkan Sekarang
                    </button>
                    <button @click="showCancelModal = false" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 py-4 rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] transition-all">
                        Tutup Dialos
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.scrollbar-v-minimal::-webkit-scrollbar { width: 5px; }
.scrollbar-v-minimal::-webkit-scrollbar-track { background: transparent; }
.scrollbar-v-minimal::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.08); border-radius: 10px; }
.scrollbar-v-minimal::-webkit-scrollbar-button { display: none; }
.scrollbar-v-minimal:hover::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); }
</style>
