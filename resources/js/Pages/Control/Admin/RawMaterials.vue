<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    materials: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const isSearching = ref(false);
const showModal = ref(false);
const editingMaterial = ref(null);

watch(() => props.filters?.search, (newSearch) => {
    if (newSearch !== undefined && newSearch !== search.value) {
        search.value = newSearch || '';
    }
});

const handleLiveSearch = useDebounceFn((val) => {
    isSearching.value = true;
    router.get(route('control.admin.raw-materials'), {
        search: val || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        },
    });
}, 300);

watch(search, (newVal) => {
    handleLiveSearch(newVal);
});

const clearSearch = () => {
    search.value = '';
};

const stats = computed(() => {
    const list = props.materials?.data || [];
    const total = props.materials?.total ?? list.length;
    const critical = list.filter(m => (Number(m.total_quantity) || 0) <= (Number(m.threshold_min) || 0)).length;
    const safe = list.filter(m => (Number(m.total_quantity) || 0) > (Number(m.threshold_min) || 0)).length;
    return { total, critical, safe };
});

const form = useForm({
    nama_bahan: '',
    bentuk_sediaan: '',
    kode_bahan: '',
    satuan_ukur: '',
    expected_quantity: '',
    batch_no: '',
    expired_date: '',
    harga_beli_satuan: '',
    threshold_min: '',
    deskripsi: '',
});

const openAdd = () => {
    editingMaterial.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (m) => {
    editingMaterial.value = m;
    form.nama_bahan = m.nama_bahan;
    form.bentuk_sediaan = m.bentuk_sediaan || '';
    form.kode_bahan = m.kode_bahan || '';
    form.satuan_ukur = m.satuan_ukur || '';
    form.harga_beli_satuan = m.harga_beli_satuan || '';
    form.threshold_min = m.threshold_min || '';
    form.deskripsi = m.deskripsi || '';
    // Non-editable after creation:
    form.expected_quantity = '';
    form.batch_no = '';
    form.expired_date = '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingMaterial.value = null;
};

const submitForm = () => {
    if (editingMaterial.value) {
        form.put(route('control.admin.raw-materials.update', editingMaterial.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.post(route('control.admin.raw-materials.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
};

const deleteMaterial = (m) => {
    if (!confirm(`Hapus bahan baku "${m.nama_bahan}"? Tindakan ini tidak dapat diurungkan.`)) return;
    router.delete(route('control.admin.raw-materials.destroy', m.id), { preserveScroll: true });
};
</script>

<template>
<Head title="Data Bahan Baku" />
<div class="space-y-6">
    <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- HERO BANNER CARD (lg:col-span-8) -->
        <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
            <!-- Left Text Column -->
            <div class="relative z-10 max-w-sm sm:max-w-md">
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                    Inventaris Bahan Baku Racikan
                </h1>
                <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                    Pengelolaan stok bahan dasar kimia, serbuk, dan cairan sediaan murni untuk kebutuhan peracikan internal apoteker.
                </p>
            </div>

            <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
            <div class="relative z-10 flex items-center justify-center shrink-0">
                <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Chemical Container (behind left) -->
                        <rect x="9" y="4" width="14" height="4.5" rx="1.8" stroke="#059669" stroke-width="2" fill="#ECFDF5" />
                        <rect x="4" y="8.5" width="24" height="42" rx="5" stroke="#059669" stroke-width="2.2" fill="#FFFFFF" />
                        <rect x="7.5" y="18" width="17" height="19" rx="3" stroke="#A7F3D0" stroke-width="1.4" fill="#F0FDF4" />
                        <path d="M16 22c-2.8 1.8-2.8 7 0 9 2.8-2 2.8-7.2 0-9z" fill="#059669" />
                        <line x1="16" y1="22" x2="16" y2="31" stroke="#059669" stroke-width="1.2" stroke-linecap="round" />
                        <!-- Mortar & Pestle (in front right) -->
                        <g transform="translate(19, 15)">
                            <path d="M12 2l16 18" stroke="#047857" stroke-width="4" stroke-linecap="round" />
                            <circle cx="12" cy="2" r="2.2" fill="#047857" />
                            <path d="M4 19h28c0 12-6 18-14 18S4 31 4 19z" stroke="#059669" stroke-width="2.2" fill="#FFFFFF" />
                            <ellipse cx="18" cy="19" rx="14" ry="4" stroke="#059669" stroke-width="1.8" fill="#ECFDF5" />
                            <ellipse cx="18" cy="19" rx="10" ry="2.2" fill="#A7F3D0" />
                            <path d="M12 37h12v2H12z" fill="#059669" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 2 STAT CARDS (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-3">
            <!-- Card 1: Stok Kritis -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        STOK KRITIS
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
                    {{ stats.critical > 0 ? 'Perlu restock segera' : 'Semua stok aman' }}
                </p>
            </div>

            <!-- Card 2: Siap Racik -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        SIAP RACIK
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
                    Stok aman tersedia
                </p>
            </div>
        </div>
    </div>

    <!-- SEARCH & ACTION BAR -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:flex-1">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input v-model="search" type="text" placeholder="Cari nama atau kode bahan baku..." class="w-full h-10 pl-10 pr-10 bg-background border border-input rounded-md text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" />
            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                <svg v-if="isSearching" class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <button 
                    v-else-if="search" 
                    type="button" 
                    @click="clearSearch" 
                    class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                    title="Hapus pencarian"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <button @click="openAdd" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700 h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Bahan Baku
        </button>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <Table>
            <TableHeader>
                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Bahan</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Kode</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Satuan</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Harga Beli</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Stok Total</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Threshold</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status Stok</TableHead>
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-if="!materials?.data || materials.data.length === 0">
                    <TableCell colspan="8" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">Belum ada bahan baku terdaftar.</TableCell>
                </TableRow>
                <TableRow v-for="m in (materials?.data || [])" :key="m.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                    <TableCell class="py-2 px-3 whitespace-nowrap align-middle">
                        <div class="flex flex-col">
                            <span class="font-medium text-foreground text-sm leading-tight">{{ m.nama_bahan }}</span>
                            <span class="text-xs text-muted-foreground leading-tight mt-0.5">{{ m.bentuk_sediaan || '-' }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="py-2 px-3 text-center text-xs font-mono text-muted-foreground align-middle">{{ m.kode_bahan || '-' }}</TableCell>
                    <TableCell class="py-2 px-3 text-center text-xs text-muted-foreground uppercase align-middle">
                        {{ m.satuan_ukur }}
                    </TableCell>
                    <TableCell class="py-2 px-3 text-right font-medium text-foreground text-xs tabular-nums align-middle">
                        Rp {{ Number(m.harga_beli_satuan || 0).toLocaleString('id-ID') }}
                    </TableCell>
                    <TableCell class="py-2 px-3 text-right align-middle">
                        <span :class="m.stok_total < m.threshold_min ? 'text-destructive font-semibold' : 'text-foreground font-medium'" class="text-sm tabular-nums">
                            {{ Number(m.stok_total).toLocaleString('id-ID') }}
                        </span>
                    </TableCell>
                    <TableCell class="py-2 px-3 text-right text-xs text-muted-foreground tabular-nums align-middle">{{ Number(m.threshold_min).toLocaleString('id-ID') }}</TableCell>
                    <TableCell class="py-2 px-3 text-center align-middle">
                        <span :class="m.stok_total < m.threshold_min ? 'text-destructive' : 'text-emerald-600'" class="text-xs font-medium inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full" :class="m.stok_total < m.threshold_min ? 'bg-destructive' : 'bg-emerald-500'"></span>
                            {{ m.stok_total < m.threshold_min ? 'Kritis' : 'Aman' }}
                        </span>
                    </TableCell>
                    <TableCell class="py-2 px-3 text-center align-middle">
                        <div class="flex items-center justify-center gap-1">
                            <button @click="openEdit(m)" class="h-7 w-7 rounded-md bg-background border border-input hover:bg-accent text-muted-foreground hover:text-foreground transition-colors flex items-center justify-center" title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="deleteMaterial(m)" class="h-7 w-7 rounded-md bg-background border border-input hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors flex items-center justify-center" title="Hapus">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination Footer -->
        <div v-if="materials.links && materials.links.length > 3" class="px-5 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
            <p class="text-xs font-medium text-muted-foreground">Halaman {{ materials.current_page || 1 }} dari {{ materials.last_page || 1 }} (Total {{ materials.total || 0 }} bahan baku)</p>
            <div class="flex items-center gap-1">
                <template v-for="(link, i) in materials.links" :key="i">
                    <button v-if="link.url" @click="router.visit(link.url, { preserveState: true, preserveScroll: true })" 
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-foreground hover:bg-accent'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label"></button>
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add / Edit -->
<Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-card rounded-lg shadow-lg w-full max-w-lg overflow-hidden border border-border">
            <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">{{ editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru' }}</h3>
                    <p class="text-xs text-muted-foreground">{{ editingMaterial ? 'Perbarui data master bahan baku.' : 'Daftarkan bahan baku baru. Stok awal langsung aktif.' }}</p>
                </div>
                <button @click="closeModal" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form @submit.prevent="submitForm" class="p-6 space-y-4 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Nama Bahan Baku <span class="text-destructive">*</span></label>
                        <input v-model="form.nama_bahan" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: Paracetamol">
                        <p v-if="form.errors.nama_bahan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.nama_bahan }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Bentuk Sediaan <span class="text-destructive">*</span></label>
                        <input v-model="form.bentuk_sediaan" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Serbuk, Cairan, dll">
                        <p v-if="form.errors.bentuk_sediaan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.bentuk_sediaan }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Kode Bahan</label>
                        <input v-model="form.kode_bahan" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Satuan Ukur <span class="text-destructive">*</span></label>
                        <input v-model="form.satuan_ukur" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Gram, mL, dll">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Harga Beli / Satuan <span class="text-destructive">*</span></label>
                        <input v-model="form.harga_beli_satuan" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                        <p v-if="form.errors.harga_beli_satuan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.harga_beli_satuan }}</p>
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Minimum Stok (Threshold) <span class="text-destructive">*</span></label>
                        <input v-model="form.threshold_min" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                    </div>

                    <!-- Only shown when creating a new item -->
                    <template v-if="!editingMaterial">
                        <div class="col-span-2 pt-3 border-t border-border">
                            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Data Stok Awal</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Qty Stok Awal <span class="text-destructive">*</span></label>
                            <input v-model="form.expected_quantity" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                            <p v-if="form.errors.expected_quantity" class="text-destructive text-xs font-medium mt-1">{{ form.errors.expected_quantity }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">No. Batch</label>
                            <input v-model="form.batch_no" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional (auto-generate)">
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Tanggal Kadaluarsa <span class="text-destructive">*</span></label>
                            <input v-model="form.expired_date" type="date" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                            <p v-if="form.errors.expired_date" class="text-destructive text-xs font-medium mt-1">{{ form.errors.expired_date }}</p>
                        </div>
                    </template>

                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Deskripsi / Catatan</label>
                        <textarea v-model="form.deskripsi" rows="2" class="w-full p-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors resize-none" placeholder="Opsional"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <button type="button" @click="closeModal" class="h-9 px-4 rounded-md border border-input bg-background text-foreground text-sm font-medium hover:bg-accent transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm transition-colors disabled:opacity-50 shadow-sm">
                        {{ form.processing ? 'Menyimpan...' : (editingMaterial ? 'Simpan Perubahan' : 'Tambah Bahan Baku') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</Teleport>
</template>
