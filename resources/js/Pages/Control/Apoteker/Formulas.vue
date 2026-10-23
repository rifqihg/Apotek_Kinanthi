<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    formulas: Object,
    medicines: { type: Array, default: () => [] },
    rawMaterials: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search || '');
const kategori = ref(props.filters?.kategori || 'all');

const filteredFormulas = computed(() => {
    let list = props.formulas?.data || [];
    if (kategori.value !== 'all') {
        const k = kategori.value.toLowerCase();
        list = list.filter(f => {
            const kat = (f.kategori_sediaan || '').toLowerCase();
            if (k.includes('padat')) return kat.includes('padat') || kat.includes('kapsul') || kat.includes('puyer');
            if (k.includes('cair') || k.includes('larutan') || k.includes('sirup')) return kat.includes('cair') || kat.includes('sirup') || kat.includes('larutan');
            if (k.includes('topikal') || k.includes('salep')) return kat.includes('topikal') || kat.includes('salep') || kat.includes('krim');
            return kat.includes(k);
        });
    }
    if (search.value && search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(f => 
            f.nama_formula?.toLowerCase().includes(q) ||
            f.kode_formula?.toLowerCase().includes(q) ||
            f.kategori_sediaan?.toLowerCase().includes(q) ||
            f.indikasi?.toLowerCase().includes(q)
        );
    }
    return list;
});

const performServerSearch = useDebounceFn(() => {
    router.reload({
        data: {
            search: search.value || undefined,
            page: 1,
        },
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
        only: ['formulas', 'filters'],
    });
}, 400);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) performServerSearch();
});

const clearSearch = () => {
    search.value = '';
    performServerSearch();
};

const resetAllFilters = () => {
    search.value = '';
    kategori.value = 'all';
    performServerSearch();
};

const showModal = ref(false);
const editMode = ref(false);
const editId = ref(null);
const form = useForm({ 
    nama_resep: '', 
    kategori_sediaan: '', 
    target_qty: '',
    target_unit: '',
    signa_default: '',
    bud_days: 14,
    instruksi_racik: '',
    ingredients: []
});

const editFormula = (f) => {
    editMode.value = true;
    editId.value = f.id;
    
    form.clearErrors();
    form.nama_resep = f.nama_resep || '';
    form.kategori_sediaan = f.kategori_sediaan || '';
    form.target_qty = f.target_qty || '';
    form.target_unit = f.target_unit || '';
    form.signa_default = f.signa_default || '';
    form.bud_days = f.bud_days || 14;
    form.instruksi_racik = f.instruksi_racik || '';
    
    form.ingredients = [];
    if (f.ingredients && f.ingredients.length > 0) {
        f.ingredients.forEach(ing => {
            form.ingredients.push({
                _key: Date.now() + Math.random(),
                source_type: ing.source_type,
                source_id: ing.source_id,
                qty_required: ing.qty_required,
                unit_label: ing.unit || ''
            });
        });
    }
    
    showModal.value = true;
};

const cloneFormula = (f) => {
    editMode.value = false;
    editId.value = null;
    
    form.clearErrors();
    form.nama_resep = `${f.nama_resep || 'Formula'} (Salinan)`;
    form.kategori_sediaan = f.kategori_sediaan || '';
    form.target_qty = f.target_qty || '';
    form.target_unit = f.target_unit || '';
    form.signa_default = f.signa_default || '';
    form.bud_days = f.bud_days || 14;
    form.instruksi_racik = f.instruksi_racik || '';
    
    form.ingredients = [];
    if (f.ingredients && f.ingredients.length > 0) {
        f.ingredients.forEach(ing => {
            form.ingredients.push({
                _key: Date.now() + Math.random(),
                source_type: ing.source_type,
                source_id: ing.source_id,
                qty_required: ing.qty_required,
                unit_label: ing.unit || ''
            });
        });
    }
    
    showModal.value = true;
};

const addIngredient = (type) => {
    form.ingredients.push({
        _key: Date.now() + Math.random(),
        source_type: type,
        source_id: '',
        qty_required: '',
        unit_label: ''
    });
};

const removeIngredient = (index) => {
    form.ingredients.splice(index, 1);
};

const updateUnit = (index) => {
    const ing = form.ingredients[index];
    if (ing.source_type === 'medicine') {
        const med = props.medicines.find(m => m.id === ing.source_id);
        ing.unit_label = med ? med.satuan_stok : '';
    } else {
        const rm = props.rawMaterials.find(r => r.id === ing.source_id);
        ing.unit_label = rm ? rm.satuan_dasar_penggunaan : '';
    }
};

const availableUnits = computed(() => {
    switch (form.kategori_sediaan) {
        case 'Larutan Air/Sirup':
            return ['mL', 'Botol', 'Tetes'];
        case 'Sediaan Padat/Puyer/Kapsul':
            return ['Kapsul', 'Bungkus'];
        case 'Sediaan Topikal/Lainnya':
            return ['Gram', 'Tube', 'Pot'];
        default:
            return [];
    }
});

watch(() => form.kategori_sediaan, () => {
    form.target_unit = '';
});

const stats = computed(() => {
    const list = props.formulas?.data || [];
    const total = props.formulas?.total ?? list.length;
    const standard = list.filter(f => f.kategori_sediaan).length;
    return { total, standard };
});

function submit() { 
    if (editMode.value && editId.value) {
        form.put(route('control.apoteker.formulas.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => { 
                showModal.value = false; 
                form.reset(); 
                editMode.value = false;
                editId.value = null;
            }
        });
    } else {
        form.post(route('control.apoteker.formulas.store'), { 
            preserveScroll: true,
            onSuccess: () => { 
                showModal.value = false; 
                form.reset(); 
            } 
        }); 
    }
}
</script>

<template>
    <Head title="Formula Obat" />

    <div class="space-y-8">
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Tombol Buat Formula (Desktop) -->
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="kategori = 'all'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="kategori === 'all' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Semua Kategori</span>
                            <span class="sm:hidden">Semua</span>
                        </button>

                        <button
                            type="button"
                            @click="kategori = 'Sediaan Padat/Puyer/Kapsul'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="kategori === 'Sediaan Padat/Puyer/Kapsul' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span>Padat</span><span class="hidden sm:inline"> / Kapsul</span>
                        </button>

                        <button
                            type="button"
                            @click="kategori = 'Larutan Air/Sirup'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="kategori === 'Larutan Air/Sirup' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span>Cair</span><span class="hidden sm:inline"> / Sirup</span>
                        </button>

                        <button
                            type="button"
                            @click="kategori = 'Sediaan Topikal/Lainnya'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="kategori === 'Sediaan Topikal/Lainnya' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span>Topikal</span><span class="hidden sm:inline"> / Salep</span>
                        </button>
                    </div>

                    <button 
                        @click="editMode=false;editId=null;showModal=true;form.reset();form.clearErrors()" 
                        class="hidden sm:inline-flex h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center justify-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Buat Formula</span>
                    </button>
                </div>

                <!-- Baris 2: Search Input & Tombol Buat Formula (Mobile) -->
                <div class="flex items-center gap-2 w-full">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari nama formula, instruksi racik, atau sediaan..." 
                            class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                        />
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                            <button v-if="search" type="button" @click="clearSearch" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <button 
                        @click="editMode=false;editId=null;showModal=true;form.reset();form.clearErrors()" 
                        class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Buat</span>
                    </button>
                </div>
            </div>

            <!-- SISI KANAN: 2 METRIC CARDS (lg:col-span-4) -->
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

        <div class="flex items-center justify-between mt-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                    Katalog Formula & Protokol Peracikan
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">Standarisasi pustaka formula racikan apotek dan instruksi klinis sediaan.</p>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-if="!filteredFormulas.length" class="col-span-full py-16 flex flex-col items-center justify-center bg-card border border-border rounded-lg shadow-sm text-center">
                <div class="w-12 h-12 bg-muted text-muted-foreground rounded-md flex items-center justify-center mb-3 border border-border">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <p class="text-foreground font-semibold text-sm">Tidak ada formula racikan ditemukan</p>
                <p v-if="search || kategori !== 'all'" class="text-muted-foreground text-xs mt-1 max-w-sm">Coba sesuaikan kata kunci atau filter kategori sediaan yang Anda pilih.</p>
                <p v-else class="text-muted-foreground text-xs mt-1 max-w-sm">Klik tombol Tambah Formula Baru untuk mulai membangun basis data instruksi racikan standar.</p>
            </div>

            <div v-for="f in filteredFormulas" :key="f.id" class="bg-card border border-slate-300 dark:border-slate-700 rounded-md p-5 transition-colors hover:border-emerald-500 flex flex-col group relative">
                <!-- Action Buttons -->
                <div class="absolute top-4 right-4 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button type="button" @click="cloneFormula(f)" class="p-1.5 text-muted-foreground hover:text-emerald-600 bg-background border border-border hover:border-emerald-500 rounded-md shadow-sm transition-colors cursor-pointer" title="Duplikasi / Salin Formula Ini">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    </button>
                    <button type="button" @click="editFormula(f)" class="p-1.5 text-muted-foreground hover:text-emerald-600 bg-background border border-border hover:border-emerald-500 rounded-md shadow-sm transition-colors cursor-pointer" title="Lihat/Edit Formula">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                </div>
                
                <h2 class="text-foreground font-bold text-base sm:text-lg leading-tight pr-8 mb-1 tracking-tight">{{ f.nama_resep }}</h2>
                <div class="flex items-center gap-1.5 mb-4">
                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold text-[10px] uppercase tracking-wider">{{ f.kategori_sediaan || 'Kustom' }}</span>
                    <span class="text-slate-300 dark:text-slate-600 text-[10px]">&bull;</span>
                    <span class="text-muted-foreground text-[10px] font-medium">{{ f.ingredients?.length || 0 }} Komposisi</span>
                </div>
                
                <div class="bg-muted/30 border border-border/50 rounded-md p-3 flex-1 mt-auto">
                    <p class="text-muted-foreground text-xs leading-relaxed line-clamp-2" :class="!f.instruksi_racik ? 'italic opacity-70' : ''">
                        {{ f.instruksi_racik || 'Tidak ada instruksi khusus pemrosesan.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="formulas.links?.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-border">
            <span class="text-xs text-muted-foreground font-medium">
                Menampilkan {{ formulas.from || 0 }} - {{ formulas.to || 0 }} dari {{ formulas.total || 0 }} formula
            </span>
            <div class="flex items-center gap-1">
                <template v-for="l in formulas.links" :key="l.label">
                        <Link 
                            v-if="l.url" 
                            :href="l.url"
                            preserve-state 
                            preserve-scroll 
                            :only="['formulas', 'filters']"
                            :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                            class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                            v-html="l.label"
                        />
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
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-border">
                <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-foreground font-semibold text-base">{{ editMode ? 'Lihat / Edit Formula' : 'Registrasi Formula Baru' }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Standarisasi alur kerja compounding.</p>
                    </div>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- SECTION 1: HEADER & YIELD -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-border pb-1">1. Identitas & Target Hasil</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Resep / Sediaan <span class="text-red-500">*</span></label>
                                <input v-model="form.nama_resep" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: Kapsul Flu & Batuk Anak" required />
                            </div>
                            
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Kategori Sediaan <span class="text-red-500">*</span></label>
                                <select v-model="form.kategori_sediaan" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                    <option value="" disabled>Pilih Kategori...</option>
                                    <option value="Larutan Air/Sirup">Cair / Sirup / Larutan</option>
                                    <option value="Sediaan Padat/Puyer/Kapsul">Padat / Kapsul / Puyer</option>
                                    <option value="Sediaan Topikal/Lainnya">Topikal / Salep / Krim</option>
                                </select>
                            </div>

                            <div class="col-span-2 sm:col-span-1 flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5" title="Kuantitas standar untuk 1 resep (misal: 1, 10, atau 100)">Skala Dasar Formula <span class="text-red-500">*</span></label>
                                    <input v-model="form.target_qty" type="number" min="0.01" step="any" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none" placeholder="100" required />
                                </div>
                                <div class="w-24">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Satuan <span class="text-red-500">*</span></label>
                                    <select v-model="form.target_unit" class="w-full h-9 bg-background border border-input rounded-md px-2 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none disabled:opacity-50 disabled:bg-muted" :disabled="!form.kategori_sediaan" required>
                                        <option value="" disabled>Pilih...</option>
                                        <option v-for="u in availableUnits" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: KOMPOSISI BAHAN (BOM) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-border pb-1">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">2. Komposisi Baku (BOM)</h4>
                            <div class="flex gap-2">
                                <button type="button" @click="addIngredient('medicine')" class="text-[10px] font-semibold bg-blue-50 text-blue-600 border border-blue-200 px-2 py-1 rounded hover:bg-blue-100 transition-colors">+ Obat Jadi</button>
                                <button type="button" @click="addIngredient('raw_material')" class="text-[10px] font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-1 rounded hover:bg-emerald-100 transition-colors">+ Bahan Baku</button>
                            </div>
                        </div>
                        
                        <div v-if="form.ingredients.length === 0" class="text-center py-6 border-2 border-dashed border-border rounded-lg bg-muted/20">
                            <p class="text-xs text-muted-foreground">Belum ada bahan. Tambahkan komposisi racikan.</p>
                        </div>
                        
                        <div v-else class="space-y-2.5">
                            <div v-for="(ing, index) in form.ingredients" :key="ing._key" class="flex items-start gap-2 bg-muted/10 p-2 border border-border rounded-lg">
                                <!-- Dropdown Pilih Obat/Bahan -->
                                <div class="flex-1">
                                    <select v-model="ing.source_id" @change="updateUnit(index)" class="w-full h-8 bg-background border border-input rounded text-xs text-foreground focus:ring-1 focus:ring-ring outline-none" required>
                                        <option value="" disabled>Pilih {{ ing.source_type === 'medicine' ? 'Obat Jadi' : 'Bahan Baku' }}...</option>
                                        <template v-if="ing.source_type === 'medicine'">
                                            <option v-for="med in medicines" :key="med.id" :value="med.id">{{ med.nama_obat }}</option>
                                        </template>
                                        <template v-else>
                                            <option v-for="rm in rawMaterials" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }}</option>
                                        </template>
                                    </select>
                                </div>
                                
                                <!-- Input Kuantitas -->
                                <div class="w-40 sm:w-44 relative flex-shrink-0 flex items-center">
                                    <input v-model="ing.qty_required" type="number" min="0.01" step="any" placeholder="Qty" class="w-full h-8 bg-background border border-input rounded text-xs text-foreground pr-[92px] pl-2 focus:ring-1 focus:ring-ring outline-none" required />
                                    <div class="absolute right-1 flex items-center justify-center pointer-events-none">
                                        <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-1.5 py-0.5 rounded text-[9px] font-bold tracking-wider truncate max-w-[85px]" :title="ing.unit_label || '-'">{{ ing.unit_label || '-' }}</span>
                                    </div>
                                </div>
                                
                                <!-- Hapus Baris -->
                                <button type="button" @click="removeIngredient(index)" class="w-8 h-8 flex-shrink-0 flex items-center justify-center text-red-500 hover:bg-red-50 rounded transition-colors" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.ingredients" class="text-red-500 text-xs mt-1">{{ form.errors.ingredients }}</p>
                    </div>

                    <!-- SECTION 3: PARAMETER KLINIS -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-border pb-1">3. Parameter Klinis</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Aturan Pakai (Signa)</label>
                                <input v-model="form.signa_default" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: S 3 dd 1 Caps" />
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Estimasi BUD (Hari) <span class="text-red-500">*</span></label>
                                <input v-model="form.bud_days" type="number" min="1" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="14" required />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Instruksi Prosedur Pembuatan</label>
                                <textarea v-model="form.instruksi_racik" rows="3" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none resize-none transition-colors leading-relaxed" placeholder="1. Gerus bahan A... 2. Tambahkan bahan B..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-border">
                        <button type="button" @click="showModal=false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">Batal</button>
                        <button type="submit" :disabled="form.processing || form.ingredients.length === 0" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50">{{ editMode ? 'Simpan Perubahan' : 'Simpan Formula Master' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
