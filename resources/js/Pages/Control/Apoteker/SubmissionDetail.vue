<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

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
        preserveScroll: true,
        onFinish: () => showCancelModal.value = false
    });
}

function printLabel() {
    window.open(route('control.apoteker.compounding.label', props.submission.id), '_blank', 'width=450,height=350');
}
</script>

<template>
    <Head :title="`Detail: ${submission.nama_racikan}`" />
    <div class="space-y-8">
        <!-- Back Button & Header -->
        <div class="flex flex-col gap-4">
            <div>
                <Link :href="route('control.apoteker.submissions')" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground hover:text-foreground transition-colors mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali Ke Log Racikan
                </Link>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-foreground tracking-tight">{{ submission.nama_racikan }}</h1>
                            <p class="text-xs text-muted-foreground mt-1 flex items-center gap-2">
                                PIC: <span class="bg-muted text-foreground px-2 py-0.5 rounded-md font-medium">{{ submission.user?.name }}</span> 
                                <span>/</span> 
                                Basis: <span class="bg-muted text-foreground px-2 py-0.5 rounded-md font-medium">{{ submission.formula?.nama_resep || 'Kustom' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="printLabel" class="px-3 h-8 border border-input bg-background hover:bg-accent text-foreground rounded-md text-xs font-medium transition-colors flex items-center gap-1.5 shadow-2xs">
                            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            Cetak Etiket
                        </button>
                        <button v-if="submission.status === 'pending_final_verification'" @click="confirmCancel" class="px-3 h-8 bg-secondary hover:bg-secondary/80 text-red-600 dark:text-red-400 border border-border rounded-md text-xs font-medium transition-colors">
                            Batalkan
                        </button>
                        <span :class="statusClass(submission.status)" class="text-xs px-3 py-1 rounded-md font-semibold uppercase tracking-wider border">{{ submission.status === 'pending_final_verification' ? 'Menunggu Harga' : submission.status?.replace(/_/g, ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Case: Pending Pricing (Admin will set the price) -->
        <div v-if="submission.status === 'pending_final_verification'" class="bg-amber-500/10 border border-amber-500/20 rounded-lg shadow-sm p-4">
            <h3 class="text-amber-600 dark:text-amber-400 font-semibold text-sm mb-1 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Menunggu Penetapan Harga oleh Admin
            </h3>
            <p class="text-xs text-muted-foreground">
                Pengajuan racikan telah selesai dan stok bahan baku sudah dikurangi. Admin akan menetapkan biaya jasa, kemasan, margin, dan harga jual final.
            </p>
        </div>

        <!-- Case: Approved Warning -->
        <div v-if="submission.status === 'approved'" class="bg-card border border-border rounded-lg p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    💡
                </div>
                <div>
                    <h4 class="font-semibold text-sm text-foreground">Protokol Produksi Tervalidasi</h4>
                    <p class="text-muted-foreground text-xs leading-relaxed mt-1">
                        Bahan baku dianggap telah keluar dari grid steril. Jika terjadi kegagalan produksi atau pembatalan setelah persetujuan, silakan hubungi admin untuk penyesuaian stok secara manual.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Composition Board -->
            <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        <h3 class="text-foreground font-semibold text-sm">Komposisi Bahan ({{ submission.details?.length }} Items)</h3>
                    </div>
                </div>
                <div class="divide-y divide-border flex-1 overflow-y-auto min-h-[300px]">
                    <div v-for="d in submission.details" :key="d.id" class="px-6 py-4 hover:bg-muted/30 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-2">
                                <p class="text-foreground font-medium text-sm">{{ d.raw_material?.nama_bahan || d.medicine?.nama_obat }}</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="flex items-center gap-1 bg-muted px-2 py-0.5 rounded-md border border-border">
                                        <span class="text-xs text-muted-foreground uppercase">Awal:</span>
                                        <span class="text-xs font-semibold text-foreground">
                                            {{ Number(d.qty_needed).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                                            <span class="text-[11px] font-normal text-muted-foreground ml-0.5">
                                                {{ d.source_type === 'raw_material' ? (d.raw_material?.satuan_pembelian || d.raw_material?.satuan_dasar_penggunaan || 'g') : (d.medicine?.satuan_terkecil || d.medicine?.satuan_stok || 'Pcs') }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="d.suggested_batch_no" class="inline-flex items-center gap-2 bg-muted px-2.5 py-1 rounded-md border border-border text-xs">
                                    <span class="uppercase font-semibold text-xs bg-background px-1.5 py-0.5 border border-border rounded text-foreground">FEFO</span> 
                                    <span class="text-xs text-muted-foreground tracking-wide">Batch: {{ d.suggested_batch_no }} / Exp: {{ new Date(d.suggested_expired_date).toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}) }}</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs text-muted-foreground mb-1">Massa Neto</p>
                                <p class="text-foreground font-semibold text-sm tabular-nums">
                                    {{ 
                                        d.source_type === 'medicine' && (!d.net_quantity || Number(d.net_quantity) === 0)
                                            ? Number(d.qty_needed).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' ' + (d.medicine?.satuan_terkecil || d.medicine?.satuan_stok || 'Pcs')
                                            : Number(d.net_quantity !== null && d.net_quantity !== undefined ? d.net_quantity : (d.qty_needed || 0)).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' ' + (d.raw_material?.satuan_dasar_penggunaan || 'g')
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Labels & Notes -->
            <div class="space-y-6">
                <!-- Etiket Card -->
                <div v-if="submission.label" class="bg-card border border-border rounded-lg shadow-sm p-6 space-y-4">
                    <h3 class="text-foreground font-semibold text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        Informasi Etiket Validasi
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-muted/40 p-3 rounded-md border border-border">
                                <span class="block text-xs text-muted-foreground uppercase tracking-wider mb-1">Tanggal Produksi</span>
                                <span class="text-xs font-semibold text-foreground">{{ new Date(submission.label.tanggal_racik).toLocaleDateString('id-ID', {day:'numeric',month:'long',year:'numeric'}) }}</span>
                            </div>
                            <div class="bg-muted/40 p-3 rounded-md border border-border">
                                <span class="block text-xs text-muted-foreground uppercase tracking-wider mb-1">Interval BUD</span>
                                <span class="text-xs font-semibold text-foreground">{{ submission.label.bud_hari }} Hari Kalender</span>
                            </div>
                        </div>

                        <div class="bg-emerald-500/10 p-4 rounded-md border border-emerald-500/20">
                            <span class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-1">Batas Masa Pakai (Beyond Use Date)</span>
                            <span class="text-base font-bold text-foreground">{{ submission.label?.tanggal_kadaluarsa_bud ? new Date(submission.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID', {day:'numeric',month:'long',year:'numeric'}) : '-' }}</span>
                        </div>

                        <div class="bg-muted/40 p-4 rounded-md border border-border space-y-3">
                            <div>
                                <span class="block text-xs text-muted-foreground uppercase tracking-wider mb-1">Aturan Pakai / Signa</span>
                                <span class="text-sm font-medium text-foreground">{{ submission.label?.aturan_pakai || '-' }}</span>
                            </div>
                            <div class="pt-2 border-t border-border">
                                <span class="block text-xs text-muted-foreground uppercase tracking-wider mb-1">Entitas Pasien</span>
                                <span class="text-xs font-medium text-foreground">{{ submission.label?.nama_pasien || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Tracking -->
                <div class="bg-card border border-border rounded-lg shadow-sm p-6 space-y-4">
                    <h3 class="text-foreground font-semibold text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Workflow & Feedback
                    </h3>
                    
                    <div class="space-y-4 relative ml-3 pl-6 border-l-2 border-border">
                        <!-- Submission Note -->
                        <div class="relative">
                            <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-xs font-bold leading-none">1</div>
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Original Apoteker Note</p>
                            <div class="bg-muted/40 rounded-md p-3 border border-border">
                                <p class="text-xs text-foreground italic">"{{ submission.apoteker_note || 'Tidak ada catatan instruksi.' }}"</p>
                            </div>
                        </div>

                        <!-- Admin Response -->
                        <div class="relative">
                            <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full" :class="submission.admin_note ? 'bg-emerald-500' : 'bg-muted border border-border'"></div>
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Evaluasi Finansial / Admin</p>
                            <div class="bg-muted/40 rounded-md p-3 border border-border">
                                <p class="text-xs text-foreground">{{ submission.admin_note || 'Protokol sedang dalam tahap review admin.' }}</p>
                            </div>
                        </div>

                        <!-- Processor -->
                        <div v-if="submission.approved_at" class="relative">
                            <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center text-white">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Validasi Final Selesai</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full bg-muted flex items-center justify-center text-xs font-semibold text-foreground border border-border">{{ submission.approver?.name?.charAt(0) }}</div>
                                <div>
                                    <span class="text-xs font-medium text-foreground">{{ submission.approver?.name || 'Admin' }}</span>
                                    <span class="text-xs text-muted-foreground ml-2">{{ submission.approved_at ? new Date(submission.approved_at).toLocaleString('id-ID') : '-' }}</span>
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
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity" @click="showCancelModal = false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md p-6 text-center border border-border">
                <div class="w-12 h-12 bg-red-500/10 text-red-600 dark:text-red-400 rounded-md flex items-center justify-center mx-auto mb-3 border border-red-500/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <h3 class="text-base font-semibold text-foreground mb-2">Batalkan Protokol?</h3>
                <p class="text-muted-foreground text-xs leading-relaxed mb-6">
                    Ini akan mengembalikan alokasi stok ke batch FEFO. Tindakan ini hanya tersedia sebelum persetujuan admin.
                </p>
                
                <div class="flex justify-end gap-2">
                    <button @click="showCancelModal = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">
                        Tutup
                    </button>
                    <button @click="proceedCancel" class="px-4 h-9 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors">
                        Ya, Batalkan Sekarang
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
