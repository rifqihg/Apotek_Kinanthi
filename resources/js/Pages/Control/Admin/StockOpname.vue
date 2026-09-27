<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    opnames: Object,
    medicines: Array,
    rawMaterials: Array,
    categories: Array,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const date = ref(props.filters?.date || '');
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        search: search.value || undefined,
        status: status.value !== 'all' ? status.value : undefined,
        date: date.value || undefined,
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

watch(status, () => {
    applyFilters();
});

watch(date, () => {
    applyFilters();
});

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const resetAllFilters = () => {
    search.value = '';
    status.value = 'all';
    date.value = '';
    applyFilters();
};

const stats = computed(() => {
    const list = props.opnames?.data || [];
    const total = props.opnames?.total ?? list.length;
    const inProgress = list.filter(op => op.status !== 'completed').length;
    const completed = list.filter(op => op.status === 'completed').length;
    return { total, inProgress, completed };
});

const showModal = ref(false);

const form = useForm({
    tanggal_opname: new Date().toISOString().split('T')[0],
    keterangan: '',
    mode: 'worksheet',
    scope_type: 'all',
    category_id: '',
    items: [
        {
            type: 'medicine',
            medicine_id: '',
            raw_material_id: '',
            warehouse_inventory_id: '',
            raw_material_stock_id: '',
            batch_no: '',
            system_stock: 0,
            qty_besar: 0,
            qty_menengah: 0,
            qty_kecil: 0,
            qty_fisik: 0,
        }
    ]
});

const getMedicinePackaging = (medicineId) => {
    const med = props.medicines?.find(m => m.id === medicineId);
    if (!med) return null;
    const inc = med.latest_active_incoming_stock || med.latestActiveIncomingStock;
    const rSmall = Math.max(1, Number(inc?.small_to_mid_ratio) || 1);
    const rMid = Math.max(1, Number(inc?.mid_to_big_ratio) || 1);
    const rBig = (rMid > 1 && rSmall > 1) ? (rMid * rSmall) : (Number(inc?.unit_conversion_factor) || (rMid * rSmall));
    const satuanPrimer = med.satuan_stok || 'Unit';

    return {
        satuan_input: inc?.satuan_input || null,
        mid_unit: inc?.mid_unit || null,
        satuan_stok: satuanPrimer,
        rBig,
        rSmall,
        hasBig: Boolean(inc?.satuan_input && rBig > 1 && inc?.satuan_input !== satuanPrimer),
        hasMid: Boolean(inc?.mid_unit && rSmall > 1 && inc?.mid_unit !== inc?.satuan_input && inc?.mid_unit !== satuanPrimer),
    };
};

const calculateItemTotal = (item) => {
    if (item.type === 'raw_material') {
        item.qty_fisik = Number(item.qty_kecil) || 0;
        return;
    }
    const pkg = getMedicinePackaging(item.medicine_id);
    if (!pkg) {
        item.qty_fisik = Number(item.qty_kecil) || 0;
        return;
    }
    const qBig = (pkg.hasBig ? Number(item.qty_besar) || 0 : 0) * pkg.rBig;
    const qMid = (pkg.hasMid ? Number(item.qty_menengah) || 0 : 0) * pkg.rSmall;
    const qSmall = Number(item.qty_kecil) || 0;
    item.qty_fisik = Number((qBig + qMid + qSmall).toFixed(2));
};

const onMedicineChange = (item) => {
    item.warehouse_inventory_id = '';
    item.batch_no = '';
    item.system_stock = 0;
    item.qty_besar = 0;
    item.qty_menengah = 0;
    item.qty_kecil = 0;
    item.qty_fisik = 0;
    const med = props.medicines?.find(m => m.id === item.medicine_id);
    const batches = med?.warehouse_inventories || med?.warehouseInventories || [];
    if (med && batches.length === 1) {
        const b = batches[0];
        item.warehouse_inventory_id = b.id;
        item.batch_no = b.batch_no;
        item.system_stock = Number(b.stock_on_hand) || 0;
    }
};

const onRawMaterialChange = (item) => {
    item.raw_material_stock_id = '';
    item.batch_no = '';
    item.system_stock = 0;
    item.qty_kecil = 0;
    item.qty_fisik = 0;
    const rm = props.rawMaterials?.find(r => r.id === item.raw_material_id);
    if (rm && rm.stocks?.length === 1) {
        const b = rm.stocks[0];
        item.raw_material_stock_id = b.id;
        item.batch_no = b.batch_no;
        item.system_stock = Number(b.stock_on_hand) || 0;
    }
};

const onBatchChange = (item) => {
    if (item.type === 'medicine') {
        const med = props.medicines?.find(m => m.id === item.medicine_id);
        const batches = med?.warehouse_inventories || med?.warehouseInventories || [];
        const b = batches.find(b => b.id === item.warehouse_inventory_id);
        if (b) {
            item.batch_no = b.batch_no;
            item.system_stock = Number(b.stock_on_hand) || 0;
        }
    } else if (item.type === 'raw_material') {
        const rm = props.rawMaterials?.find(r => r.id === item.raw_material_id);
        const b = rm?.stocks?.find(b => b.id === item.raw_material_stock_id);
        if (b) {
            item.batch_no = b.batch_no;
            item.system_stock = Number(b.stock_on_hand) || 0;
        }
    }
};

const formatDate = (d) => {
    if (!d) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d));
    } catch {
        return String(d).split('T')[0] || '-';
    }
};

const formatBatchOption = (b) => {
    if (!b) return '';
    const ed = b.expired_date ? formatDate(b.expired_date) : '-';
    const stock = Number(b.stock_on_hand || 0);
    return `${b.batch_no} (ED: ${ed}) - Stok: ${stock}`;
};

const getBatchesForMedicine = (medicineId) => {
    const med = props.medicines?.find(m => m.id === medicineId);
    return med?.warehouse_inventories || med?.warehouseInventories || [];
};

const getBatchesForRawMaterial = (rawMaterialId) => {
    const rm = props.rawMaterials?.find(r => r.id === rawMaterialId);
    return rm?.stocks || [];
};

const addItem = () => {
    form.items.push({
        type: 'medicine',
        medicine_id: '',
        raw_material_id: '',
        warehouse_inventory_id: '',
        raw_material_stock_id: '',
        batch_no: '',
        system_stock: 0,
        qty_besar: 0,
        qty_menengah: 0,
        qty_kecil: 0,
        qty_fisik: 0,
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('control.admin.stock-opname.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};
</script>

<template>
    <Head title="Stock Opname Admin" />

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Manajemen Stock Opname
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pencocokan fisik berkala antara persediaan obat di rak apotek dengan saldo data tercatat pada sistem.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Clipboard Audit Sheet (behind left) -->
                            <rect x="11" y="4" width="14" height="5.5" rx="2" stroke="#4F46E5" stroke-width="1.8" fill="#EEF2FF" />
                            <circle cx="18" cy="6.8" r="1.2" fill="#4F46E5" />
                            <rect x="4" y="8" width="30" height="43" rx="4" stroke="#4F46E5" stroke-width="2.2" fill="#FFFFFF" />
                            <rect x="8" y="17" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 19.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="19.5" x2="28" y2="19.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <rect x="8" y="26" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 28.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="28.5" x2="26" y2="28.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <rect x="8" y="35" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 37.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="37.5" x2="27" y2="37.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <!-- Magnifying Glass Scanner (in front right) -->
                            <g transform="translate(24, 18)">
                                <circle cx="15" cy="15" r="12" stroke="#4F46E5" stroke-width="2.2" fill="#FFFFFF" />
                                <circle cx="15" cy="15" r="9" fill="#EEF2FF" fill-opacity="0.7" />
                                <path d="M10.5 15.5l3 3 6-6" stroke="#4F46E5" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M24 24l8 8" stroke="#4F46E5" stroke-width="3.5" stroke-linecap="round" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Sesi Draft / Aktif -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            SESI AKTIF / DRAFT
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.inProgress > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.inProgress > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.inProgress }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.inProgress > 0 ? 'Perlu finalisasi fisik' : 'Semua sesi selesai' }}
                    </p>
                </div>

                <!-- Card 2: Audit Selesai -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            AUDIT SELESAI
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.completed }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Stok telah diselaraskan
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION & FILTER BAR -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                    Daftar Sesi Stock Opname
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">Filter dan pantau riwayat audit fisik persediaan obat dan bahan baku.</p>
            </div>
            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Buat Stock Opname Baru
            </button>
        </div>

        <!-- FILTER CONTROLS -->
        <div class="bg-card rounded-lg border border-border p-3.5 shadow-2xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1">
                <!-- Live Search Box -->
                <div class="relative flex-1 sm:max-w-xs">
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Cari ID, catatan, penanggung jawab..." 
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

                <!-- Status Filter -->
                <div class="w-full sm:w-40">
                    <select 
                        v-model="status" 
                        class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer"
                    >
                        <option value="all">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="completed">Selesai</option>
                    </select>
                </div>

                <!-- Date Filter -->
                <div class="w-full sm:w-44">
                    <input 
                        v-model="date" 
                        type="date" 
                        class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors"
                        title="Filter tanggal sesi opname"
                    />
                </div>
            </div>

            <!-- Reset Filter Button -->
            <div v-if="search || status !== 'all' || date" class="shrink-0">
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

        <!-- Table -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <Table class="w-full table-fixed">
                <TableHeader>
                    <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                        <TableHead class="w-[16%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Tanggal</TableHead>
                        <TableHead class="w-[28%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Keterangan / ID</TableHead>
                        <TableHead class="w-[20%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Penanggung Jawab</TableHead>
                        <TableHead class="w-[12%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Jumlah Item</TableHead>
                        <TableHead class="w-[12%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Status</TableHead>
                        <TableHead class="w-[12%] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="opnames.data.length === 0">
                        <TableCell colspan="6" class="py-12 px-3 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mb-2">
                                    <svg class="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-foreground">Tidak ada riwayat sesi stock opname ditemukan.</p>
                                <p v-if="search || status !== 'all' || date" class="text-xs text-muted-foreground mt-1">Coba sesuaikan kata kunci atau filter yang Anda gunakan.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="op in opnames.data" :key="op.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                        <TableCell class="py-2.5 px-3 align-middle font-medium text-foreground text-sm tabular-nums">
                            {{ new Date(op.opname_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle">
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold text-foreground">#SO-{{ op.id }}</span>
                                <span class="text-xs text-muted-foreground line-clamp-1">{{ op.catatan || '-' }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-sm font-medium text-foreground">
                            {{ op.creator?.name || 'Admin' }}
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-center">
                            <span class="text-sm font-medium text-foreground tabular-nums">{{ op.items_count || 0 }} item</span>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-center">
                            <span v-if="op.status === 'completed'" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Selesai
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Draft
                            </span>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-right">
                            <Link :href="route('control.admin.stock-opname.show', op.id)" class="inline-flex items-center justify-center text-foreground bg-background border border-input hover:bg-accent h-7 px-2.5 rounded text-xs font-medium transition-colors">Lihat Detail</Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div v-if="opnames.links?.length > 3" class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 bg-muted/30">
                <span class="text-xs text-muted-foreground font-medium">
                    Menampilkan {{ opnames.from || 0 }} - {{ opnames.to || 0 }} dari {{ opnames.total || 0 }} sesi
                </span>
                <div class="flex items-center gap-1.5">
                    <template v-for="l in opnames.links" :key="l.label">
                        <Link v-if="l.url" :href="l.url" preserve-state preserve-scroll :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="l.label" />
                        <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-background/80 backdrop-blur-sm p-4">
                <div class="absolute inset-0" @click="showModal = false"></div>
                <div class="relative bg-card rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col border border-border">
                    <div class="px-6 py-4 border-b border-border flex justify-between items-center bg-muted/30">
                        <div>
                            <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">Buat Stock Opname Baru</h3>
                            <p class="text-xs text-muted-foreground">Penyesuaian stok sistem dengan fisik.</p>
                        </div>
                        <button @click="showModal = false" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Top Form Details -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Tanggal Opname</label>
                                    <input type="date" v-model="form.tanggal_opname" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Keterangan (Opsional)</label>
                                    <input type="text" v-model="form.keterangan" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" placeholder="Contoh: Audit Rutin Akhir Bulan">
                                </div>
                            </div>

                            <!-- Mode Switcher -->
                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Metode Opname</label>
                                <div class="flex items-center gap-2 p-1 bg-muted/60 rounded-lg border border-border w-fit">
                                    <button type="button" @click="form.mode = 'worksheet'" :class="form.mode === 'worksheet' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'" class="px-3.5 py-1.5 rounded-md text-xs transition-colors">
                                        Lembar Kerja Otomatis (Worksheet)
                                    </button>
                                    <button type="button" @click="form.mode = 'manual'" :class="form.mode === 'manual' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'" class="px-3.5 py-1.5 rounded-md text-xs transition-colors">
                                        Input Manual (Sampling / Audit Acak)
                                    </button>
                                </div>
                            </div>

                            <!-- WORKSHEET MODE OPTIONS -->
                            <div v-if="form.mode === 'worksheet'" class="space-y-4 bg-muted/20 p-4 rounded-lg border border-border">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Cakupan Lembar Kerja</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <label class="flex items-center gap-2.5 p-3 rounded-md border cursor-pointer transition-colors" :class="form.scope_type === 'all' ? 'border-primary bg-primary/5 text-foreground font-semibold' : 'border-input bg-background text-muted-foreground hover:bg-muted/30'">
                                            <input type="radio" v-model="form.scope_type" value="all" class="text-primary focus:ring-primary h-4 w-4" />
                                            <span class="text-xs">Semua Obat Reguler</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 p-3 rounded-md border cursor-pointer transition-colors" :class="form.scope_type === 'category' ? 'border-primary bg-primary/5 text-foreground font-semibold' : 'border-input bg-background text-muted-foreground hover:bg-muted/30'">
                                            <input type="radio" v-model="form.scope_type" value="category" class="text-primary focus:ring-primary h-4 w-4" />
                                            <span class="text-xs">Kategori Tertentu</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 p-3 rounded-md border cursor-pointer transition-colors" :class="form.scope_type === 'raw_materials' ? 'border-primary bg-primary/5 text-foreground font-semibold' : 'border-input bg-background text-muted-foreground hover:bg-muted/30'">
                                            <input type="radio" v-model="form.scope_type" value="raw_materials" class="text-primary focus:ring-primary h-4 w-4" />
                                            <span class="text-xs">Khusus Bahan Baku</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="form.scope_type === 'category'" class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Pilih Kategori (misal: Narkotika / Psikotropika)</label>
                                    <select v-model="form.category_id" class="w-full h-9 px-3 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" required>
                                        <option value="" disabled>Pilih Kategori...</option>
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>

                                <div class="flex items-start gap-2.5 p-3 rounded-md bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs leading-relaxed">
                                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p>
                                        Sistem akan langsung menyiapkan lembar kerja yang memuat seluruh batch aktif dan tanggal kadaluwarsa (ED). Petugas dapat langsung menghitung fisik di rak apotek tanpa perlu menambah baris satu per satu.
                                    </p>
                                </div>
                            </div>

                            <!-- MANUAL SAMPLING MODE -->
                            <div v-if="form.mode === 'manual'" class="border-t border-border pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">Item Sampling yang Diopname</h4>
                                        <p class="text-[11px] text-muted-foreground mt-0.5">Pilih obat dan batch fisik yang dihitung di rak apotek.</p>
                                    </div>
                                    <button type="button" @click="addItem" class="text-xs bg-muted text-foreground hover:bg-accent font-medium px-3 py-1.5 rounded-md transition-colors border border-border">
                                        + Tambah Baris
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(item, idx) in form.items" :key="idx" class="p-3.5 rounded-lg border border-border bg-muted/10 space-y-3">
                                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                            <div class="w-full sm:w-36">
                                                <select v-model="item.type" @change="item.medicine_id = ''; item.raw_material_id = ''; onMedicineChange(item)" class="w-full h-9 px-3 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium">
                                                    <option value="medicine">Obat Jadi</option>
                                                    <option value="raw_material">Bahan Baku</option>
                                                </select>
                                            </div>
                                            <div class="flex-1 w-full">
                                                <select v-if="item.type === 'medicine'" v-model="item.medicine_id" @change="onMedicineChange(item)" class="w-full h-9 px-3 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" required>
                                                    <option value="" disabled>Pilih Obat Jadi...</option>
                                                    <option v-for="m in medicines" :key="m.id" :value="m.id">{{ m.nama_obat }}</option>
                                                </select>
                                                <select v-if="item.type === 'raw_material'" v-model="item.raw_material_id" @change="onRawMaterialChange(item)" class="w-full h-9 px-3 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" required>
                                                    <option value="" disabled>Pilih Bahan Baku...</option>
                                                    <option v-for="rm in rawMaterials" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }}</option>
                                                </select>
                                            </div>
                                            <button type="button" @click="removeItem(idx)" :disabled="form.items.length === 1" class="w-9 h-9 shrink-0 flex items-center justify-center rounded-md bg-background border border-input text-muted-foreground hover:text-destructive hover:border-destructive transition-colors disabled:opacity-50">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>

                                        <!-- Batch Selection Row -->
                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center pt-2 border-t border-border/60">
                                            <!-- Batch Selector (sm:col-span-5) -->
                                            <div class="sm:col-span-5">
                                                <label class="block text-[10px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">Pilih Batch Fisik</label>
                                                <select v-if="item.type === 'medicine'" v-model="item.warehouse_inventory_id" @change="onBatchChange(item)" class="w-full h-8 px-2.5 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" :disabled="!item.medicine_id">
                                                    <option value="" disabled>Pilih Batch Gudang...</option>
                                                    <option v-for="b in getBatchesForMedicine(item.medicine_id)" :key="b.id" :value="b.id">
                                                        {{ formatBatchOption(b) }}
                                                    </option>
                                                </select>
                                                <select v-if="item.type === 'raw_material'" v-model="item.raw_material_stock_id" @change="onBatchChange(item)" class="w-full h-8 px-2.5 text-xs border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" :disabled="!item.raw_material_id">
                                                    <option value="" disabled>Pilih Batch Bahan Baku...</option>
                                                    <option v-for="b in getBatchesForRawMaterial(item.raw_material_id)" :key="b.id" :value="b.id">
                                                        {{ formatBatchOption(b) }}
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Multi-Packaging Parallel Inputs (sm:col-span-7) -->
                                            <div class="sm:col-span-7">
                                                <label class="block text-[10px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                                    Hitungan Fisik di Rak
                                                    <span v-if="item.system_stock > 0" class="text-muted-foreground font-normal lowercase">(stok sistem: {{ item.system_stock }})</span>
                                                </label>
                                                
                                                <!-- If Medicine with Multi Packaging -->
                                                <div v-if="item.type === 'medicine' && getMedicinePackaging(item.medicine_id)" class="flex items-center gap-2">
                                                    <!-- Big Unit Input (if available) -->
                                                    <div v-if="getMedicinePackaging(item.medicine_id).hasBig" class="flex-1 flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs">
                                                        <input type="number" v-model.number="item.qty_besar" @input="calculateItemTotal(item)" min="0" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                                            {{ getMedicinePackaging(item.medicine_id).satuan_input }}
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Mid Unit Input (if available) -->
                                                    <div v-if="getMedicinePackaging(item.medicine_id).hasMid" class="flex-1 flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs">
                                                        <input type="number" v-model.number="item.qty_menengah" @input="calculateItemTotal(item)" min="0" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                                            {{ getMedicinePackaging(item.medicine_id).mid_unit }}
                                                        </span>
                                                    </div>

                                                    <!-- Small Unit Input -->
                                                    <div class="flex-1 flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs">
                                                        <input type="number" v-model.number="item.qty_kecil" @input="calculateItemTotal(item)" min="0" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                                            {{ getMedicinePackaging(item.medicine_id).satuan_stok }}
                                                        </span>
                                                    </div>

                                                    <!-- Total Base Unit Badge -->
                                                    <div class="shrink-0 px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-md text-emerald-700 dark:text-emerald-400 font-bold text-xs tabular-nums whitespace-nowrap shadow-2xs">
                                                        = {{ item.qty_fisik }} {{ getMedicinePackaging(item.medicine_id).satuan_stok }}
                                                    </div>
                                                </div>

                                                <!-- Fallback Single Input -->
                                                <div v-else class="flex items-center gap-2">
                                                    <div class="flex-1 flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs">
                                                        <input type="number" v-model.number="item.qty_kecil" @input="calculateItemTotal(item)" min="0" step="0.01" class="w-full h-8 px-3 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                                        <span class="pr-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                                            {{ item.type === 'raw_material' ? (rawMaterials?.find(r => r.id === item.raw_material_id)?.satuan || 'Satuan') : 'Satuan' }}
                                                        </span>
                                                    </div>
                                                    <div class="shrink-0 px-2.5 py-1 bg-muted/60 border border-border rounded-md text-foreground font-semibold text-xs tabular-nums shadow-2xs">
                                                        = {{ item.qty_fisik }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-border flex justify-end gap-3 bg-muted/30">
                        <button type="button" @click="showModal = false" class="px-4 h-9 text-sm font-medium text-foreground bg-background border border-input hover:bg-accent rounded-md transition-colors">Batal</button>
                        <button type="button" @click="submit" :disabled="form.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50 shadow-sm">
                            {{ form.mode === 'worksheet' ? 'Generate Lembar Opname' : 'Simpan Opname' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
