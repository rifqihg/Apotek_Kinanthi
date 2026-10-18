<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    month: { type: String, default: () => new Date().toISOString().substring(0, 7) },
    summary: { type: Object, default: () => ({}) },
    reportData: { type: Array, default: () => [] }
});

const selectedMonth = ref(props.month);

const handleMonthChange = () => {
    router.get(route('control.apoteker.reports.sipnap'), { month: selectedMonth.value }, {
        preserveState: true,
        preserveScroll: true
    });
};

const classificationBadge = (c) => {
    switch (c) {
        case 'narkotika':
            return { label: 'Narkotika', cls: 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400 border-red-200 dark:border-red-900' };
        case 'psikotropika':
            return { label: 'Psikotropika', cls: 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-400 border-purple-200 dark:border-purple-900' };
        default:
            return { label: c, cls: 'bg-slate-50 text-slate-700 border-slate-200' };
    }
};

const formatMonthLabel = (m) => {
    if (!m) return '-';
    const [year, month] = m.split('-');
    const date = new Date(year, month - 1, 1);
    return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};
</script>

<template>
    <Head title="Laporan Mutasi SIPNAP" />

    <div class="space-y-6 animate-in fade-in duration-500">
        <!-- HEADER: HERO BANNER & STATS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- Hero Banner -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-red-50/50 via-white to-purple-50/50 dark:from-card dark:via-card dark:to-purple-950/20">
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 border border-red-200 dark:border-red-900 mb-1.5">
                        Permenkes RI · Kepatuhan SIPNAP
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Rekapitulasi Mutasi SIPNAP
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Laporan agregasi pergerakan persediaan Narkotika & Psikotropika periode {{ formatMonthLabel(month) }} untuk pelaporan Kemenkes.
                    </p>
                </div>

                <!-- Month Picker & Export PDF Action -->
                <div class="relative z-10 flex items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs">
                    <div class="flex items-center gap-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider whitespace-nowrap">Periode:</label>
                        <input 
                            type="month" 
                            v-model="selectedMonth" 
                            @change="handleMonthChange"
                            class="h-9 px-3 bg-background border border-input rounded-lg text-xs font-semibold text-foreground focus:ring-1 focus:ring-primary outline-none transition-colors"
                        />
                    </div>
                    <a 
                        :href="route('control.apoteker.reports.sipnap.export-pdf', { month: selectedMonth })" 
                        target="_blank"
                        class="inline-flex items-center gap-2 px-3.5 h-9 rounded-md bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white text-xs font-semibold shadow-xs transition-colors whitespace-nowrap cursor-pointer"
                        title="Unduh Berkas Laporan Resmi SIPNAP (PDF)"
                    >
                        <!-- Official Adobe PDF Icon -->
                        <svg class="w-4 h-4 shrink-0 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.5 2h9l5.5 5.5v12.5a2 2 0 0 1-2 2h-12.5a2 2 0 0 1-2-2v-16a2 2 0 0 1 2-2zm8 1.5v4.5h4.5l-4.5-4.5zm-8.5 7.5v6.5h1.5v-2.25h1.25a2.125 2.125 0 0 0 0-4.25h-2.75zm1.5 1.5h1.25a.625.625 0 0 1 0 1.25h-1.25v-1.25zm4.5-1.5v6.5h2.25a2.5 2.5 0 0 0 2.5-2.5v-1.5a2.5 2.5 0 0 0-2.5-2.5h-2.25zm1.5 1.5h.75a1 1 0 0 1 1 1v1.5a1 1 0 0 1-1 1h-.75v-3.5zm5.5-1.5v6.5h1.5v-2.5h2v-1.5h-2v-1h2.25v-1.5h-3.75z"/>
                        </svg>
                        <span>Export PDF</span>
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground">TOTAL ITEM</span>
                        <div class="w-6 h-6 rounded-full bg-red-50 dark:bg-red-950/50 border border-red-100 dark:border-red-900 flex items-center justify-center text-red-600 dark:text-red-400 shrink-0">
                            <span class="text-xs font-bold">{{ summary.total_items || 0 }}</span>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-xs text-muted-foreground">Narkotika: <strong class="text-red-600">{{ summary.total_narkotika || 0 }}</strong></span><br>
                        <span class="text-xs text-muted-foreground">Psikotropika: <strong class="text-purple-600">{{ summary.total_psikotropika || 0 }}</strong></span>
                    </div>
                    <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground">Sediaan golongan khusus</p>
                </div>

                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground">MUTASI BULAN INI</span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-xs text-muted-foreground">Masuk: <strong class="text-emerald-600">+{{ summary.total_pemasukan || 0 }}</strong></span><br>
                        <span class="text-xs text-muted-foreground">Keluar: <strong class="text-rose-600">-{{ summary.total_pengeluaran || 0 }}</strong></span>
                    </div>
                    <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground">Faktur vs Resep Pasien</p>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left border-collapse min-w-[890px]">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center w-12 min-w-12 whitespace-nowrap">No</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[220px] whitespace-nowrap">Identitas Obat</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[130px] whitespace-nowrap">Golongan</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[80px] whitespace-nowrap">Satuan</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Stok Awal</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center text-emerald-600 min-w-[100px] whitespace-nowrap">Pemasukan</th>
                        <th class="py-3 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center text-rose-600 min-w-[100px] whitespace-nowrap">Pengeluaran</th>
                        <th class="py-3 px-4 text-xs font-semibold text-foreground uppercase tracking-wider text-center min-w-[110px] whitespace-nowrap">Stok Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!reportData.length">
                        <td colspan="8" class="px-6 py-12 text-center text-muted-foreground text-sm">
                            Tidak ada sediaan obat berklasifikasi Narkotika atau Psikotropika terdaftar di sistem.
                        </td>
                    </tr>
                    <tr v-for="(item, idx) in reportData" :key="item.id" class="hover:bg-muted/30 transition-colors">
                        <td class="py-3 px-4 text-center font-medium text-muted-foreground">{{ idx + 1 }}</td>
                        <td class="py-3 px-4">
                            <span class="font-semibold text-foreground text-sm block">{{ item.nama_obat }}</span>
                            <span class="font-mono text-xs text-muted-foreground">{{ item.batch_no || '-' }}</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span :class="classificationBadge(item.classification).cls" class="text-xs px-2.5 py-0.5 rounded-md font-bold uppercase tracking-wider border inline-block whitespace-nowrap">
                                {{ classificationBadge(item.classification).label }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center text-sm font-medium text-foreground">
                            {{ item.satuan }}
                        </td>
                        <td class="py-3 px-4 text-center text-sm font-medium text-foreground">
                            {{ Number(item.stok_awal).toLocaleString('id-ID') }}
                        </td>
                        <td class="py-3 px-4 text-center text-sm font-medium text-emerald-600">
                            +{{ Number(item.pemasukan).toLocaleString('id-ID') }}
                        </td>
                        <td class="py-3 px-4 text-center text-sm font-medium text-rose-600">
                            -{{ Number(item.pengeluaran).toLocaleString('id-ID') }}
                        </td>
                        <td class="py-3 px-4 text-center text-sm font-semibold text-foreground bg-muted/20">
                            {{ Number(item.stok_akhir).toLocaleString('id-ID') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
