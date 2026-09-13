<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ prescriptions: Object });

const stats = computed(() => {
    const list = props.prescriptions?.data || [];
    const pending = list.filter(p => p.status === 'baru' || p.status === 'diproses').length;
    const ready = list.filter(p => p.status === 'siap_ambil').length;
    return { pending, ready };
});

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
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Daftar Resep & Skrining Klinis
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pemeriksaan kelengkapan resep dokter, verifikasi dosis, dan pemrosesan peracikan obat pasien.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Prescription Sheet Rx (behind left) -->
                            <rect x="4" y="4" width="30" height="44" rx="4" stroke="#2563EB" stroke-width="2.2" fill="#FFFFFF" />
                            <path d="M10 10v14M10 10h7a4 4 0 0 1 0 8h-7M15 18l4.5 6" stroke="#2563EB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="10" y1="29" x2="27" y2="29" stroke="#2563EB" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                            <line x1="10" y1="34" x2="24" y2="34" stroke="#2563EB" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                            <line x1="10" y1="39" x2="26" y2="39" stroke="#2563EB" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                            <!-- Tilted Capsule (in front right) -->
                            <g transform="translate(37, 33) rotate(28)">
                                <rect x="-7.5" y="-18" width="15" height="36" rx="7.5" stroke="#2563EB" stroke-width="2.2" fill="#FFFFFF" />
                                <path d="M-7.5 -10.5 A7.5 7.5 0 0 1 7.5 -10.5 V0 H-7.5 Z" fill="#BFDBFE" />
                                <line x1="-7.5" y1="0" x2="7.5" y2="0" stroke="#2563EB" stroke-width="1.8" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Perlu Diproses -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            PERLU DIPROSES
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.pending > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.pending > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.pending }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.pending > 0 ? 'Resep baru / peracikan' : 'Semua resep selesai' }}
                    </p>
                </div>

                <!-- Card 2: Siap Diambil -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            SIAP DIAMBIL
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.ready }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Menunggu pasien di kasir
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                Daftar Antrean Resep Pasien
            </h2>
            <Link :href="route('control.apoteker.prescriptions.create')" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Resep Baru
            </Link>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">No. Resep</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Pasien</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Dokter</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Tgl Resep</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jml Item</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!prescriptions?.data?.length">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-12 h-12 bg-muted rounded-md flex items-center justify-center text-muted-foreground border border-border">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <span class="text-sm font-medium text-muted-foreground">Belum ada resep aktif.</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="rx in (prescriptions?.data || [])" :key="rx.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3 font-medium text-foreground text-sm tracking-wide">{{ rx.prescription_number }}</td>
                        <td class="px-4 py-3 font-medium text-foreground">{{ rx.patient?.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground text-sm">{{ rx.doctor_name }}</td>
                        <td class="px-4 py-3 text-center text-muted-foreground text-sm">{{ formatDate(rx.prescription_date) }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-foreground text-sm">{{ rx.prescription_items?.length || 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="statusColor(rx.status)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block whitespace-nowrap">
                                {{ rx.status ? rx.status.replace(/_/g, ' ') : 'unknown' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Link :href="route('control.apoteker.prescriptions.show', rx.id)" class="inline-flex items-center justify-center text-foreground hover:text-primary bg-secondary hover:bg-secondary/80 border border-border h-8 px-3 rounded-md text-xs font-medium transition-colors">
                                Detail
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="px-4 py-3 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs text-muted-foreground font-medium">Halaman {{ prescriptions?.current_page || 1 }}</span>
                <div v-if="prescriptions?.links?.length > 3" class="flex items-center gap-1">
                    <template v-for="l in (prescriptions?.links || [])" :key="l.label">
                        <Link 
                            v-if="l.url" 
                            :href="l.url" 
                            :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                            class="w-8 h-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                            v-html="l.label"
                        ></Link>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
