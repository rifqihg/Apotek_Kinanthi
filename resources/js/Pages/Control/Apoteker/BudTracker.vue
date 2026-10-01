<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ submissions: Object });

const search = ref('');
const category = ref('all');
const sort = ref('closest');

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

const stats = computed(() => {
    const list = props.submissions?.data || [];
    let critical = 0;
    let safe = 0;
    list.forEach(s => {
        const st = getBudStatus(s.label?.tanggal_kadaluarsa_bud);
        if (st.label === 'Expired' || st.label === 'Kritis' || st.label === 'Peringatan') critical++;
        else safe++;
    });
    return { critical, safe };
});

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
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900 mb-1.5">
                        KLINIS · BUD TRACKER
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Monitoring Beyond Use Date (BUD)
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pelacakan masa pakai aman stabilitas sediaan racikan, batas degradasi kimia, dan penjaminan mutu pasien.
                    </p>
                </div>

                <!-- Right Integrated Control Panel -->
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                    <div class="relative w-full sm:w-48">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" type="text" placeholder="Cari racikan..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button v-if="search" type="button" @click="search=''" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <select v-model="category" class="w-full sm:w-auto h-9 bg-background border border-input rounded-lg px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors cursor-pointer max-w-[120px] sm:max-w-none">
                        <option value="all">Status: Semua</option>
                        <option value="kritis">Kritis</option>
                        <option value="peringatan">Peringatan</option>
                        <option value="aman">Aman</option>
                    </select>

                    <select v-model="sort" class="w-full sm:w-auto h-9 bg-background border border-input rounded-lg px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors cursor-pointer max-w-[120px] sm:max-w-none">
                        <option value="closest">Urut: Terdekat</option>
                        <option value="furthest">Urut: Terjauh</option>
                    </select>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: BUD Peringatan -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            PERINGATAN (≤ 7 HARI)
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.critical > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.critical > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.critical }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.critical > 0 ? 'Mendekati masa kedaluwarsa' : 'Semua racikan stabil' }}
                    </p>
                </div>

                <!-- Card 2: BUD Stabil -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            BUD AMAN
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.safe }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Masa pakai dalam batas aman
                    </p>
                </div>
            </div>
        </div>

        <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
            Daftar Racikan Aktif Terpantau
        </h2>

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
                <Link 
                    v-if="l.url" 
                    :href="l.url"
                    preserve-scroll 
                    preserve-state 
                    :class="l.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-muted-foreground hover:bg-muted hover:text-foreground'" 
                    class="h-9 w-9 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                    v-html="l.label"
                />
            </template>
        </div>
    </div>
</template>
