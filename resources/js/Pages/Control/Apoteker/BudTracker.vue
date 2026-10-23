<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ submissions: Object });

const search = ref('');
const category = ref('all');
const sort = ref('closest');
const expandedIds = ref(new Set());

const toggleExpand = (id) => {
    if (expandedIds.value.has(id)) {
        expandedIds.value.delete(id);
    } else {
        expandedIds.value.add(id);
    }
};

const getBudStatus = (expiryDate) => {
    if (!expiryDate) return { label: 'Unknown', color: 'bg-slate-500', text: 'text-slate-700 dark:text-slate-300', bg: 'bg-slate-50 dark:bg-muted', border: 'border-slate-200 dark:border-border', progress: 0 };
    const today = new Date();
    const expiry = new Date(expiryDate);
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return { label: 'Expired', color: 'bg-rose-600', text: 'text-rose-700 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40', border: 'border-rose-200 dark:border-rose-900', progress: 0 };
    if (diffDays < 3) return { label: 'Kritis', color: 'bg-rose-500', text: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40', border: 'border-rose-200 dark:border-rose-900', progress: (diffDays / 30) * 100 };
    if (diffDays < 7) return { label: 'Peringatan', color: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40', border: 'border-amber-200 dark:border-amber-900', progress: (diffDays / 30) * 100 };
    return { label: 'Aman', color: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40', border: 'border-emerald-200 dark:border-emerald-900', progress: (diffDays / 30) * 100 };
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

const filteredSubmissions = computed(() => {
    let list = [...(props.submissions?.data || [])];

    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(s => 
            (s.nama_racikan && s.nama_racikan.toLowerCase().includes(q)) ||
            (s.user?.name && s.user.name.toLowerCase().includes(q)) ||
            (s.label?.aturan_pakai && s.label.aturan_pakai.toLowerCase().includes(q))
        );
    }

    if (category.value !== 'all') {
        list = list.filter(s => {
            const st = getBudStatus(s.label?.tanggal_kadaluarsa_bud);
            if (category.value === 'peringatan') {
                return st.label === 'Expired' || st.label === 'Kritis' || st.label === 'Peringatan';
            }
            if (category.value === 'aman') {
                return st.label === 'Aman';
            }
            return true;
        });
    }

    list.sort((a, b) => {
        const dateA = a.label?.tanggal_kadaluarsa_bud ? new Date(a.label.tanggal_kadaluarsa_bud).getTime() : 0;
        const dateB = b.label?.tanggal_kadaluarsa_bud ? new Date(b.label.tanggal_kadaluarsa_bud).getTime() : 0;
        return sort.value === 'furthest' ? dateB - dateA : dateA - dateB;
    });

    return list;
});
</script>

<template>
    <Head title="Monitoring Beyond Use Date (BUD)" />
    <div class="space-y-8">
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Sorting Selector -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="category = 'all'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="category === 'all' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Semua Status</span>
                            <span class="sm:hidden">Semua</span>
                        </button>

                        <button
                            type="button"
                            @click="category = 'peringatan'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="category === 'peringatan' 
                                ? 'bg-amber-500 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="category === 'peringatan' ? 'bg-white' : 'bg-amber-500'"></span>
                            <span class="hidden sm:inline">Peringatan (≤ 7 Hari)</span>
                            <span class="sm:hidden">Peringatan</span>
                            <span 
                                v-if="stats.critical > 0"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="category === 'peringatan' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
                            >{{ stats.critical }}</span>
                        </button>

                        <button
                            type="button"
                            @click="category = 'aman'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="category === 'aman' 
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="category === 'aman' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400'"></span>
                            <span class="hidden sm:inline">Aman & Stabil</span>
                            <span class="sm:hidden">Aman</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <select 
                            v-model="sort" 
                            class="h-9 pl-3 pr-8 min-w-[125px] bg-slate-100 dark:bg-muted border-0 rounded-lg text-xs font-semibold text-slate-700 dark:text-muted-foreground focus:ring-1 focus:ring-emerald-500 cursor-pointer shrink-0"
                        >
                            <option value="closest">Urut: Terdekat</option>
                            <option value="furthest">Urut: Terjauh</option>
                        </select>
                    </div>
                </div>

                <!-- Baris 2: Full-Width Search Input -->
                <div class="relative w-full">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Cari racikan, nomor resep, pasien, atau batch..." 
                        class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                    />
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                        <button v-if="search" type="button" @click="search=''" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SISI KANAN: 2 METRIC CARDS (lg:col-span-4) -->
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

        <!-- DAFTAR RACIKAN AKTIF TERPANTAU (ACCORDION 2-KOLOM) -->
        <div class="pt-1">
            <div class="flex items-center justify-between mb-2.5">
                <h2 class="text-xs font-bold text-slate-600 dark:text-muted-foreground uppercase tracking-wider">
                    Daftar Racikan Aktif Terpantau
                </h2>
                <span class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground">
                    {{ filteredSubmissions.length }} Racikan Terpantau
                </span>
            </div>

            <div v-if="!filteredSubmissions.length" class="bg-white dark:bg-card p-10 text-center rounded-xl border border-slate-200/80 dark:border-border shadow-2xs text-muted-foreground font-medium text-xs">
                Tidak ada racikan aktif yang terpantau dalam grid BUD.
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-3.5 items-start">
                <div 
                    v-for="s in filteredSubmissions" 
                    :key="s.id" 
                    class="bg-white dark:bg-card border rounded-xl shadow-2xs overflow-hidden transition-all duration-150 flex flex-col"
                    :class="expandedIds.has(s.id) ? 'border-emerald-500/50 ring-1 ring-emerald-500/20' : 'border-slate-200/80 dark:border-border hover:border-slate-300 dark:hover:border-slate-700'"
                >
                    <!-- HEADER BARIS (TRIGGER ACCORDION) -->
                    <div 
                        @click="toggleExpand(s.id)"
                        class="px-4 py-3 sm:px-4.5 sm:py-3.5 flex items-center justify-between gap-3 cursor-pointer select-none transition-colors hover:bg-slate-50/80 dark:hover:bg-muted/40"
                    >
                        <!-- Kiri: Badge Status & Nama Racikan -->
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span 
                                v-if="s.label" 
                                :class="[getBudStatus(s.label.tanggal_kadaluarsa_bud).bg, getBudStatus(s.label.tanggal_kadaluarsa_bud).text, getBudStatus(s.label.tanggal_kadaluarsa_bud).border]" 
                                class="px-2.5 py-0.5 rounded-md text-xs font-bold border shrink-0 uppercase tracking-wide"
                            >
                                {{ getBudStatus(s.label.tanggal_kadaluarsa_bud).label }}
                            </span>
                            
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-foreground truncate leading-tight">
                                    {{ s.nama_racikan || 'Tanpa Nama' }}
                                </h3>
                                <p v-if="s.label?.nama_pasien" class="text-xs text-muted-foreground truncate mt-0.5">
                                    Pasien: {{ s.label.nama_pasien }}
                                </p>
                            </div>
                        </div>

                        <!-- Kanan: Status Sisa Hari, Batas Tanggal, & Chevron -->
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right">
                                <p class="text-sm font-bold leading-tight" :class="getBudStatus(s.label?.tanggal_kadaluarsa_bud).text">
                                    {{ formatDaysLeft(s.label?.tanggal_kadaluarsa_bud) }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-muted-foreground font-medium mt-0.5">
                                    Batas: {{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID') : '-' }}
                                </p>
                            </div>

                            <!-- Chevron Button -->
                            <div 
                                class="w-7 h-7 rounded-lg flex items-center justify-center bg-slate-100/70 dark:bg-muted/60 text-slate-500 hover:text-slate-700 dark:text-muted-foreground dark:hover:text-foreground transition-transform duration-200 shrink-0 ml-1"
                                :class="{ 'rotate-180 text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50': expandedIds.has(s.id) }"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- SLIM PROGRESS BAR (INDIKATOR MASA PAKAI) -->
                    <div v-if="s.label" class="h-1 w-full bg-slate-100 dark:bg-muted/40 overflow-hidden">
                        <div 
                            :class="getBudStatus(s.label.tanggal_kadaluarsa_bud).color" 
                            class="h-full transition-all duration-500" 
                            :style="{ width: `${Math.min(100, Math.max(5, getBudStatus(s.label.tanggal_kadaluarsa_bud).progress))}%` }"
                        ></div>
                    </div>

                    <!-- ACCORDION BODY (DETAIL RACIKAN LEGA & TERSTRUKTUR) -->
                    <div 
                        v-if="expandedIds.has(s.id)" 
                        class="border-t border-slate-100 dark:border-border/60 bg-slate-50/70 dark:bg-muted/20 px-4 py-3.5 sm:px-4.5 sm:py-4 transition-all space-y-3"
                    >
                        <!-- Baris 1: Grid 2 Kolom untuk Metadata Ringkas -->
                        <div class="grid grid-cols-2 gap-3 pb-3 border-b border-slate-200/60 dark:border-border/60">
                            <div class="space-y-2">
                                <div>
                                    <p class="text-[10.5px] font-semibold text-slate-400 dark:text-muted-foreground uppercase tracking-wider mb-0.5">Apoteker</p>
                                    <p class="text-xs font-semibold text-slate-700 dark:text-foreground">{{ s.user?.name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10.5px] font-semibold text-slate-400 dark:text-muted-foreground uppercase tracking-wider mb-0.5">Tanggal Racik</p>
                                    <p class="text-xs font-semibold text-slate-700 dark:text-foreground">{{ s.label?.tanggal_racik ? new Date(s.label.tanggal_racik).toLocaleDateString('id-ID') : '-' }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div>
                                    <p class="text-[10.5px] font-semibold text-rose-500 uppercase tracking-wider mb-0.5">Batas BUD</p>
                                    <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID') : '-' }}</p>
                                    <p class="text-[10.5px] text-muted-foreground">Estimasi: {{ s.label?.bud_hari || 0 }} hari operasional</p>
                                </div>
                                <div v-if="s.label?.nama_pasien">
                                    <p class="text-[10.5px] font-semibold text-slate-400 dark:text-muted-foreground uppercase tracking-wider mb-0.5">Pasien</p>
                                    <p class="text-xs font-semibold text-slate-700 dark:text-foreground">{{ s.label.nama_pasien }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Baris 2: Aturan Pakai Full-Width (Lega & Nyaman Dibaca) -->
                        <div>
                            <p class="text-[10.5px] font-semibold text-slate-400 dark:text-muted-foreground uppercase tracking-wider mb-1">Aturan Pakai</p>
                            <div class="px-3 py-2 rounded-lg bg-white dark:bg-card border border-slate-200/80 dark:border-border text-xs text-slate-700 dark:text-slate-300 italic font-medium leading-relaxed">
                                "{{ s.label?.aturan_pakai || '-' }}"
                            </div>
                        </div>
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
