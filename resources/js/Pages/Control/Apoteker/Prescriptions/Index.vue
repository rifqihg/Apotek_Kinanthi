<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    prescriptions: Object,
    filters: { type: Object, default: () => ({}) }
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const date = ref(props.filters?.date || '');
const isFiltering = ref(false);

const applyFilters = () => {
    isFiltering.value = true;
    router.get(route(route().current()), {
        search: search.value || undefined,
        status: status.value || undefined,
        date: date.value || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { isFiltering.value = false; }
    });
};

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

watch([status, date], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    status.value = '';
    date.value = '';
    applyFilters();
};

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

const isControlledRx = (rx) => {
    return rx.prescription_items?.some(item => 
        ['narkotika', 'psikotropika'].includes(item.medicine?.classification)
    ) ?? false;
};
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
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 mb-1.5">
                        KLINIS · ANTRIAN RESEP
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Daftar Resep & Skrining Klinis
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pemeriksaan kelengkapan resep dokter, verifikasi dosis, dan pemrosesan peracikan obat pasien.
                    </p>
                </div>

                <!-- Right Integrated Control Panel -->
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                    <div class="relative w-full sm:w-48">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" type="text" placeholder="Cari no. resep..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <svg v-if="isFiltering" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <button v-else-if="search" type="button" @click="search=''; applyFilters()" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <select v-model="status" class="w-full sm:w-auto h-9 bg-background border border-input rounded-lg px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors cursor-pointer">
                        <option value="">Status: Resep Aktif</option>
                        <option value="all">Semua Status</option>
                        <option value="baru">Baru</option>
                        <option value="diproses">Diproses</option>
                        <option value="siap_ambil">Siap Ambil</option>
                        <option value="selesai">Selesai</option>
                    </select>

                    <Link :href="route('control.apoteker.prescriptions.create')" class="w-full sm:w-auto h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs flex items-center justify-center gap-1.5 whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Input Resep</span>
                    </Link>
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

        <div class="flex items-center justify-between mt-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                    Daftar Antrean Resep Pasien
                </h2>
            </div>
        </div>

        <!-- ADDITIONAL FILTERS -->
        <div class="bg-card border border-border p-3 rounded-lg shadow-sm flex flex-wrap gap-3 items-center">
            <!-- Date Filter -->
            <input v-model="date" type="date" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer" title="Filter Tanggal Resep" />

            <!-- Reset Button -->
            <button v-if="search || status || date" @click="clearFilters" class="h-9 px-3 text-xs font-medium border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5 rounded-md cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Reset Filter
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left min-w-[970px]">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[260px] whitespace-nowrap">No. Resep</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[160px] whitespace-nowrap">Pasien</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[160px] whitespace-nowrap">Dokter</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[110px] whitespace-nowrap">Tgl Resep</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[80px] whitespace-nowrap">Jml Item</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[120px] whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[80px] whitespace-nowrap">Aksi</th>
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
                        <td class="px-4 py-3 font-medium text-foreground text-sm tracking-wide">
                            <div class="flex flex-col items-start gap-1">
                                <span>{{ rx.prescription_number }}</span>
                                <span v-if="isControlledRx(rx)" class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800" title="Resep Narkotika / Psikotropika - Wajib Cek Fisik Resep & SIP">
                                    <svg class="w-2.5 h-2.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    Resep Narkotika / Psikotropika - Wajib Cek Fisik Resep &amp; SIP
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium text-foreground">{{ rx.patient?.name }}</td>
                        <td class="px-4 py-3 font-medium text-foreground">{{ rx.doctor_name }}</td>
                        <td class="px-4 py-3 text-center font-medium text-foreground">{{ formatDate(rx.prescription_date) }}</td>
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
                <span class="text-xs text-muted-foreground font-medium">Halaman {{ prescriptions?.current_page || 1 }} dari {{ prescriptions?.last_page || 1 }} (Total {{ prescriptions?.total || 0 }} resep)</span>
                <div v-if="prescriptions?.links?.length > 3" class="flex items-center gap-1">
                    <template v-for="(l, i) in (prescriptions?.links || [])" :key="i">
                        <Link 
                            v-if="l.url" 
                            :href="l.url"
                            preserve-state 
                            preserve-scroll 
                            :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                            class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                            v-html="l.label"
                        />
                        <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
