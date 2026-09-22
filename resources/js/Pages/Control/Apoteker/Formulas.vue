<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    formulas: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search || '');
const kategori = ref(props.filters?.kategori || 'all');
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        search: search.value || undefined,
        kategori: kategori.value !== 'all' ? kategori.value : undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        }
    });
};

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

watch(kategori, () => {
    applyFilters();
});

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const resetAllFilters = () => {
    search.value = '';
    kategori.value = 'all';
    applyFilters();
};

const showModal = ref(false);
const form = useForm({ nama_resep:'', kategori_sediaan:'', instruksi_racik:'' });

const stats = computed(() => {
    const list = props.formulas?.data || [];
    const total = props.formulas?.total ?? list.length;
    const standard = list.filter(f => f.kategori_sediaan).length;
    return { total, standard };
});

function submit() { 
    const routeName = route().current().startsWith('control.admin') 
        ? 'control.admin.formulas.store' 
        : 'control.apoteker.formulas.store';
    form.post(route(routeName), { onSuccess: () => { showModal.value = false; form.reset(); } }); 
}
</script>

<template>
    <Head title="Formula Obat" />

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Pustaka Resep & Formula Obat
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Standarisasi prototipe formula racikan apotek, panduan instruksi pengerjaan, dan takaran bahan baku.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Formula Document Sheet (behind left) -->
                        <rect x="4" y="4" width="30" height="44" rx="4" stroke="#0D9488" stroke-width="2.2" fill="#FFFFFF" />
                        <rect x="11" y="4" width="7" height="8" rx="1.2" fill="#0D9488" />
                        <rect x="8" y="16" width="15" height="3.5" rx="1" fill="#99F6E4" />
                        <line x1="8" y1="24" x2="27" y2="24" stroke="#0D9488" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <line x1="8" y1="29" x2="23" y2="29" stroke="#0D9488" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <line x1="8" y1="34" x2="25" y2="34" stroke="#0D9488" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <!-- Mortar & Pestle (in front right) -->
                        <g transform="translate(19, 16)">
                            <path d="M12 2l16 17" stroke="#0F766E" stroke-width="4" stroke-linecap="round" />
                            <circle cx="12" cy="2" r="2.2" fill="#0F766E" />
                            <path d="M4 18h28c0 11-6 17-14 17S4 29 4 18z" stroke="#0D9488" stroke-width="2.2" fill="#FFFFFF" />
                            <ellipse cx="18" cy="18" rx="14" ry="4" stroke="#0D9488" stroke-width="1.8" fill="#F0FDFA" />
                            <ellipse cx="18" cy="18" rx="10" ry="2.2" fill="#99F6E4" />
                            <path d="M12 35h12v2H12z" fill="#0D9488" />
                        </g>
                    </svg>
                </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Total Formula -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            FORMULA AKTIF
                        </span>
                        <div class="w-6 h-6 rounded-full bg-teal-50 dark:bg-teal-950/50 border border-teal-100 dark:border-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-teal-600 dark:text-teal-400 leading-none">
                            {{ stats.total }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Master racikan tercatat
                    </p>
                </div>

                <!-- Card 2: Terstandarisasi -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            KATEGORI SEDIAAN
                        </span>
                        <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted border border-slate-200 dark:border-border flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                            {{ stats.standard }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Kategori terklasifikasi
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION & FILTER BAR -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                    Katalog Formula & Protokol Peracikan
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">Standarisasi pustaka formula racikan apotek dan instruksi klinis sediaan.</p>
            </div>
            <button @click="showModal=true;form.reset();form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Formula Baru
            </button>
        </div>

        <!-- FILTER CONTROLS -->
        <div class="bg-card rounded-lg border border-border p-3.5 shadow-2xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1">
                <!-- Live Search Box -->
                <div class="relative flex-1 sm:max-w-md">
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Cari nama resep, kode, kategori, indikasi klinis..." 
                        class="w-full h-9 pl-9 pr-8 bg-background border border-input rounded-md text-xs text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" 
                    />
                    <svg v-if="!isSearching" class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <svg v-else class="animate-spin w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-emerald-600 pointer-events-none" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <button 
                        v-if="search" 
                        type="button" 
                        @click="clearSearch" 
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1 rounded-full hover:bg-muted transition-colors cursor-pointer"
                        title="Hapus pencarian"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Kategori Filter Dropdown -->
                <div class="w-full sm:w-56">
                    <select 
                        v-model="kategori" 
                        class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer"
                    >
                        <option value="all">Semua Kategori</option>
                        <option value="Larutan Air/Sirup">Larutan Air / Sirup</option>
                        <option value="Sediaan Padat/Puyer/Kapsul">Padat / Puyer / Kapsul</option>
                        <option value="Sediaan Topikal/Lainnya">Topikal / Salep / Lainnya</option>
                        <option value="Kapsul">Kapsul</option>
                        <option value="Puyer">Puyer</option>
                        <option value="Sirup">Sirup</option>
                        <option value="Salep">Salep</option>
                    </select>
                </div>
            </div>

            <!-- Reset Filter Button -->
            <div v-if="search || kategori !== 'all'" class="shrink-0">
                <button 
                    type="button" 
                    @click="resetAllFilters" 
                    class="h-9 px-3 rounded-md text-xs font-medium border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset Filter
                </button>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-if="!formulas?.data?.length" class="col-span-full py-16 flex flex-col items-center justify-center bg-card border border-border rounded-lg shadow-sm text-center">
                <div class="w-12 h-12 bg-muted text-muted-foreground rounded-md flex items-center justify-center mb-3 border border-border">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <p class="text-foreground font-semibold text-sm">Tidak ada formula racikan ditemukan</p>
                <p v-if="search || kategori !== 'all'" class="text-muted-foreground text-xs mt-1 max-w-sm">Coba sesuaikan kata kunci atau filter kategori sediaan yang Anda pilih.</p>
                <p v-else class="text-muted-foreground text-xs mt-1 max-w-sm">Klik tombol Tambah Formula Baru untuk mulai membangun basis data instruksi racikan standar.</p>
            </div>

            <div v-for="f in (formulas?.data || [])" :key="f.id" class="bg-card border border-border rounded-lg p-5 shadow-sm hover:shadow-md transition-all flex flex-col group">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-9 h-9 rounded-md bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <span class="inline-flex items-center bg-muted text-muted-foreground border border-border text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider">
                        {{ f.kategori_sediaan || 'Kustom' }}
                    </span>
                </div>
                <h2 class="text-foreground font-semibold text-sm leading-tight mb-2">{{ f.nama_resep }}</h2>
                <p class="text-muted-foreground text-xs leading-relaxed line-clamp-3 flex-1">{{ f.instruksi_racik || 'Tidak ada instruksi khusus pemrosesan.' }}</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="formulas.links?.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-border">
            <span class="text-xs text-muted-foreground font-medium">
                Menampilkan {{ formulas.from || 0 }} - {{ formulas.to || 0 }} dari {{ formulas.total || 0 }} formula
            </span>
            <div class="flex items-center gap-1">
                <template v-for="l in formulas.links" :key="l.label">
                    <button 
                        v-if="l.url" 
                        @click="router.visit(l.url, { preserveState: true, preserveScroll: true })" 
                        :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                        v-html="l.label"
                    ></button>
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                </template>
            </div>
        </div>
        <div v-else-if="formulas.data && formulas.data.length > 0" class="flex justify-between items-center pt-4 border-t border-border">
            <span class="text-xs text-muted-foreground font-medium">
                Menampilkan {{ formulas.data.length }} formula
            </span>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showModal=false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md overflow-hidden border border-border">
                <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-foreground font-semibold text-base">Registrasi Formula Baru</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Standarisasi alur kerja compounding.</p>
                    </div>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Resep / Sediaan <span class="text-red-500">*</span></label>
                        <input v-model="form.nama_resep" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Nama racikan standar..." required />
                        <p v-if="form.errors.nama_resep" class="text-red-500 text-xs mt-1">{{ form.errors.nama_resep }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Kategori Sediaan <span class="text-red-500">*</span></label>
                        <select v-model="form.kategori_sediaan" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                            <option value="" disabled>Pilih Kategori...</option>
                            <option value="Larutan Air/Sirup">Larutan Air/Sirup (BUD 14 Hari)</option>
                            <option value="Sediaan Padat/Puyer/Kapsul">Sediaan Padat/Puyer/Kapsul (BUD 30 Hari)</option>
                            <option value="Sediaan Topikal/Lainnya">Lainnya / Topikal</option>
                        </select>
                        <p v-if="form.errors.kategori_sediaan" class="text-red-500 text-xs mt-1">{{ form.errors.kategori_sediaan }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Instruksi Racik</label>
                        <textarea v-model="form.instruksi_racik" rows="4" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none resize-none transition-colors leading-relaxed" placeholder="Catatan cara pembuatan atau peringatan khusus..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showModal=false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50">Simpan Formula</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
