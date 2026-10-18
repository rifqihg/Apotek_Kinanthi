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

const filteredPrescriptions = computed(() => {
    let list = props.prescriptions?.data || [];
    if (status.value && status.value !== 'all') {
        list = list.filter(p => p.status === status.value);
    }
    if (search.value && search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(p => 
            p.nomor_resep?.toLowerCase().includes(q) ||
            p.nama_pasien?.toLowerCase().includes(q) ||
            p.nama_dokter?.toLowerCase().includes(q)
        );
    }
    return list;
});

const performServerSearch = useDebounceFn(() => {
    router.reload({
        data: {
            search: search.value || undefined,
            status: status.value || undefined,
            date: date.value || undefined,
            page: 1,
        },
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
        only: ['prescriptions', 'filters'],
    });
}, 400);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) performServerSearch();
});

watch(status, (newStatus) => {
    if (newStatus === 'all') {
        router.reload({
            data: {
                search: search.value || undefined,
                status: 'all',
                date: date.value || undefined,
                page: 1,
            },
            preserveState: true,
            preserveScroll: true,
            only: ['prescriptions', 'filters'],
        });
    }
});

watch(date, () => {
    router.reload({
        data: {
            search: search.value || undefined,
            status: status.value || undefined,
            date: date.value || undefined,
            page: 1,
        },
        preserveState: true,
        preserveScroll: true,
        only: ['prescriptions', 'filters'],
    });
});

const clearFilters = () => {
    search.value = '';
    status.value = '';
    date.value = '';
    performServerSearch();
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
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Tombol Input Resep (Desktop) -->
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="status = ''"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="status === '' 
                                ? 'bg-emerald-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span>Aktif</span>
                        </button>

                        <button
                            type="button"
                            @click="status = 'baru'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="status === 'baru' 
                                ? 'bg-indigo-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="status === 'baru' ? 'bg-white' : 'bg-indigo-500'"></span>
                            <span>Menunggu</span>
                        </button>

                        <button
                            type="button"
                            @click="status = 'diproses'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="status === 'diproses' 
                                ? 'bg-amber-500 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="status === 'diproses' ? 'bg-white' : 'bg-amber-500'"></span>
                            <span>Diracik</span>
                        </button>

                        <button
                            type="button"
                            @click="status = 'siap_ambil'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="status === 'siap_ambil' 
                                ? 'bg-emerald-700 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="status === 'siap_ambil' ? 'bg-white' : 'bg-emerald-500'"></span>
                            <span class="hidden sm:inline">Siap Ambil</span>
                            <span class="sm:hidden">Siap</span>
                            <span 
                                v-if="stats.ready > 0"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="status === 'siap_ambil' ? 'bg-white/20 text-white' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
                            >{{ stats.ready }}</span>
                        </button>

                        <button
                            type="button"
                            @click="status = 'all'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="status === 'all' 
                                ? 'bg-slate-800 dark:bg-slate-200 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Semua Status</span>
                            <span class="sm:hidden">Semua</span>
                        </button>
                    </div>

                    <Link 
                        :href="route('control.apoteker.prescriptions.create')" 
                        class="hidden sm:inline-flex h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span>Input Resep</span>
                    </Link>
                </div>

                <!-- Baris 2: Search Input & Tombol Input Resep (Mobile) -->
                <div class="flex items-center gap-2 w-full">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari nomor resep, pasien, dokter, faskes..." 
                            class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button v-if="search" type="button" @click="search=''; performServerSearch()" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <Link 
                        :href="route('control.apoteker.prescriptions.create')" 
                        class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span>Input</span>
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
                    <tr v-if="!filteredPrescriptions.length">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-12 h-12 bg-muted rounded-md flex items-center justify-center text-muted-foreground border border-border">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <span class="text-sm font-medium text-muted-foreground">Belum ada resep aktif.</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="rx in filteredPrescriptions" :key="rx.id" class="hover:bg-muted/30 transition-colors">
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
