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
            <h1 class="text-xl font-bold tracking-tight text-foreground">Monitoring Beyond Use Date</h1>
            <p class="text-xs text-muted-foreground mt-0.5">Pelacakan masa pakai aman untuk obat racikan aktif</p>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <div v-if="!submissions?.data?.length" class="bg-card p-12 text-center rounded-lg border border-border shadow-sm text-muted-foreground font-medium text-xs">
                Tidak ada racikan aktif yang terpantau dalam grid BUD.
            </div>
            
            <div v-for="s in (submissions?.data || [])" :key="s.id" class="bg-card border border-border rounded-lg shadow-sm p-5 transition-colors">
                <div class="flex flex-col md:flex-row justify-between gap-6">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-4">
                            <h3 class="text-base font-semibold text-foreground">{{ s.nama_racikan || 'Tanpa Nama' }}</h3>
                            <span v-if="s.label" :class="[getBudStatus(s.label.tanggal_kadaluarsa_bud).bg, getBudStatus(s.label.tanggal_kadaluarsa_bud).text, getBudStatus(s.label.tanggal_kadaluarsa_bud).border]" class="px-2.5 py-0.5 rounded-md text-xs font-semibold border">
                                {{ getBudStatus(s.label.tanggal_kadaluarsa_bud).label }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground mb-1">Apoteker</p>
                                <p class="text-sm font-medium text-foreground">{{ s.user?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground mb-1">Tanggal Racik</p>
                                <p class="text-sm font-medium text-foreground">{{ s.label?.tanggal_racik ? new Date(s.label.tanggal_racik).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-rose-500 mb-1">Batas BUD</p>
                                <p class="text-sm font-semibold text-rose-600">{{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground mb-1">Aturan Pakai</p>
                                <p class="text-sm text-muted-foreground italic">"{{ s.label?.aturan_pakai || '-' }}"</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="s.label" class="w-full md:w-64 space-y-2">
                        <div class="flex justify-between items-end">
                            <span class="text-xs font-medium text-muted-foreground">Status Masa Pakai</span>
                            <span :class="getBudStatus(s.label.tanggal_kadaluarsa_bud).text" class="text-xs font-semibold">{{ formatDaysLeft(s.label.tanggal_kadaluarsa_bud) }}</span>
                        </div>
                        <div class="h-2 w-full bg-muted rounded-full overflow-hidden border border-border">
                            <div :class="getBudStatus(s.label.tanggal_kadaluarsa_bud).color" class="h-full transition-all duration-1000" :style="{ width: `${Math.min(100, Math.max(5, getBudStatus(s.label.tanggal_kadaluarsa_bud).progress))}%` }"></div>
                        </div>
                        <p class="text-xs text-muted-foreground text-right font-medium">Estimasi: {{ s.label.bud_hari || 0 }} Hari Operasional</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="submissions?.links?.length > 3" class="flex justify-center gap-1.5 pt-6">
            <template v-for="l in (submissions?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-muted-foreground hover:bg-muted hover:text-foreground'" 
                    class="h-9 w-9 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>
</template>
