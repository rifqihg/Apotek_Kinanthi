<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    returns: Object,
    activeIncomingStocks: Array,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search || '');
const date = ref(props.filters?.date || '');
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        search: search.value || undefined,
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

watch(date, () => {
    applyFilters();
});

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const resetAllFilters = () => {
    search.value = '';
    date.value = '';
    applyFilters();
};

const stats = computed(() => {
    const list = props.returns?.data || [];
    const total = props.returns?.total ?? list.length;
    const totalUnits = list.reduce((acc, curr) => acc + (Number(curr.qty_retur) || 0), 0);
    const uniqueSuppliers = new Set(list.map(r => r.supplier?.id || r.supplier_id).filter(Boolean)).size;
    return { total, totalUnits, uniqueSuppliers };
});

const showModal = ref(false);

const form = useForm({
    incoming_stock_id: '',
    qty_retur_input: 1,
    satuan_level: 'terkecil',
    alasan_retur: '',
});

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

// State for filtering in modal
const selectedSupplierId = ref('');

// Get unique suppliers from active stocks
const availableSuppliers = computed(() => {
    const suppliers = [];
    const supplierIds = new Set();
    props.activeIncomingStocks.forEach(stock => {
        if (stock.supplier && !supplierIds.has(stock.supplier.id)) {
            supplierIds.add(stock.supplier.id);
            suppliers.push(stock.supplier);
        }
    });
    return suppliers;
});

// Get stocks for selected supplier
const availableStocks = computed(() => {
    if (!selectedSupplierId.value) return [];
    return props.activeIncomingStocks.filter(stock => stock.supplier_id === selectedSupplierId.value);
});

// Selected stock details
const selectedStockData = computed(() => {
    if (!form.incoming_stock_id) return null;
    return props.activeIncomingStocks.find(s => s.id === form.incoming_stock_id);
});

// Calculate multiplier based on selected unit
const currentMultiplier = computed(() => {
    if (!selectedStockData.value) return 1;
    const st = selectedStockData.value;
    if (form.satuan_level === 'besar') {
        if (st.mid_to_big_ratio > 0 && st.small_to_mid_ratio > 0) {
            return st.mid_to_big_ratio * st.small_to_mid_ratio;
        }
        return st.unit_conversion_factor || 1;
    } else if (form.satuan_level === 'menengah') {
        return st.small_to_mid_ratio || 1;
    }
    return 1;
});

// Converted total base quantity to return
const convertedQtyToReturn = computed(() => {
    return form.qty_retur_input * currentMultiplier.value;
});

// Get the actual unit name for UI string (DUS, STRIP, etc)
const getSelectedUnitName = () => {
    if (!selectedStockData.value) return '';
    const st = selectedStockData.value;
    if (form.satuan_level === 'besar') return st.satuan_input || 'Besar';
    if (form.satuan_level === 'menengah') return st.mid_unit || 'Menengah';
    return st.medicine?.satuan_stok || 'Terkecil';
};

// Maximum base returnable qty
const maxQty = computed(() => {
    if (!selectedStockData.value) return 0;
    return selectedStockData.value.qty_converted || 0;
});

// Maximum input returnable qty based on selected unit
const maxQtyInput = computed(() => {
    if (!selectedStockData.value) return 0;
    return Math.floor(maxQty.value / currentMultiplier.value);
});

// Submit form
const submitReturn = () => {
    if (convertedQtyToReturn.value > maxQty.value) {
        alert('Kuantitas melebihi batas stok tersedia!');
        return;
    }
    
    const reasonWithUnit = `[${form.qty_retur_input} ${getSelectedUnitName()}] ${form.alasan_retur}`;

    form.transform((data) => ({
        ...data,
        qty_retur: convertedQtyToReturn.value,
        alasan_retur: reasonWithUnit
    })).post(route('control.admin.returns.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            selectedSupplierId.value = '';
        },
    });
};
</script>

<template>
    <Head title="Retur Obat ke Supplier" />

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Manajemen Retur Obat
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pencatatan pengembalian sediaan obat yang cacat, rusak, atau mendekati kedaluwarsa ke pihak distributor resmi.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Return Medicine Box (behind left) -->
                            <rect x="4" y="11" width="30" height="39" rx="4" stroke="#D97706" stroke-width="2.2" fill="#FFFFFF" />
                            <rect x="14" y="11" width="8" height="39" fill="#FEF3C7" stroke="#FDE68A" stroke-width="1.2" />
                            <circle cx="18" cy="30" r="6" stroke="#D97706" stroke-width="1.5" fill="#FFFBEB" />
                            <path d="M18 27v3.5M18 33v.5" stroke="#D97706" stroke-width="1.8" stroke-linecap="round" />
                            <!-- Return Circular Arrow Badge (in front right) -->
                            <g transform="translate(22, 5)">
                                <circle cx="16" cy="16" r="14" stroke="#D97706" stroke-width="2.2" fill="#FFFBEB" />
                                <circle cx="16" cy="16" r="10.5" fill="#FEF3C7" fill-opacity="0.6" />
                                <path d="M22.5 10.5A9 9 0 1 0 23.5 18" stroke="#D97706" stroke-width="2.2" stroke-linecap="round" fill="none" />
                                <path d="M23.5 6.5v4.5h-4.5" stroke="#D97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Sediaan Diretur -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            SEDIAAN DIRETUR
                        </span>
                        <div class="w-6 h-6 rounded-full bg-amber-50 dark:bg-amber-950/50 border border-amber-100 dark:border-amber-900 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-amber-600 dark:text-amber-400 leading-none">
                            {{ stats.totalUnits }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Klaim sediaan fisik
                    </p>
                </div>

                <!-- Card 2: PBF Rekanan -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            PBF REKANAN
                        </span>
                        <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted border border-slate-200 dark:border-border flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                            {{ stats.uniqueSuppliers }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Distributor terkait retur
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION & FILTER BAR -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                    Riwayat Retur Obat
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">Pencatatan pengembalian obat ke distributor resmi dengan sinkronisasi stok.</p>
            </div>
            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 px-4 h-10 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Buat Retur
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
                        placeholder="Cari no. retur, PBF, nama obat, faktur, batch, alasan..." 
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

                <!-- Date Filter -->
                <div class="w-full sm:w-44">
                    <input 
                        v-model="date" 
                        type="date" 
                        class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors"
                        title="Filter tanggal retur"
                    />
                </div>
            </div>

            <!-- Reset Filter Button -->
            <div v-if="search || date" class="shrink-0">
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

        <!-- TABLE: RIWAYAT RETUR -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <Table class="w-full min-w-[920px]">
                <TableHeader>
                    <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                        <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Tgl Retur</TableHead>
                        <TableHead class="min-w-[180px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Supplier</TableHead>
                        <TableHead class="min-w-[240px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Faktur & Obat</TableHead>
                        <TableHead class="min-w-[140px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Ekuivalen Stok Dasar</TableHead>
                        <TableHead class="min-w-[140px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Alasan (Fisik Retur)</TableHead>
                        <TableHead class="min-w-[100px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!returns.data || returns.data.length === 0">
                        <TableCell colspan="6" class="py-12 px-3 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mb-2">
                                    <svg class="w-5 h-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-foreground">Tidak ada riwayat retur obat yang ditemukan.</p>
                                <p v-if="search || date" class="text-xs text-muted-foreground mt-1">Coba sesuaikan kata kunci atau filter tanggal yang Anda gunakan.</p>
                                <p v-else class="text-xs text-muted-foreground mt-1">Klik Buat Retur untuk mengembalikan obat.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="item in returns.data" :key="item.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                        <TableCell class="py-2.5 px-3 align-middle">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-foreground leading-tight">{{ formatDate(item.created_at) }}</span>
                                <span class="text-xs text-muted-foreground leading-tight mt-0.5">Oleh: {{ item.creator?.name || 'Sistem' }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle">
                            <span class="text-sm font-medium text-foreground">{{ item.supplier?.nama_pbf || '-' }}</span>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-foreground leading-tight">{{ item.incoming_stock?.medicine?.nama_obat || '-' }}</span>
                                <div class="flex items-center gap-1.5 mt-0.5 text-xs text-muted-foreground">
                                    <span class="font-medium text-foreground/80">{{ item.incoming_stock?.faktur_no || '-' }}</span>
                                    <span>·</span>
                                    <span>Batch: {{ item.incoming_stock?.batch_no || '-' }}</span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-center">
                            <span class="text-sm font-semibold tabular-nums text-destructive">
                                -{{ item.qty_retur }} <span class="text-xs font-normal uppercase">{{ item.incoming_stock?.medicine?.satuan_stok }}</span>
                            </span>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle">
                            <p class="text-xs text-muted-foreground max-w-[250px] truncate" :title="item.alasan_retur">
                                {{ item.alasan_retur }}
                            </p>
                        </TableCell>
                        <TableCell class="py-2.5 px-3 align-middle text-right">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                SELESAI
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            
            <!-- PAGINATION -->
            <div v-if="returns.links?.length > 3" class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 bg-muted/30">
                <span class="text-xs text-muted-foreground font-medium">
                    Menampilkan {{ returns.from || 0 }} - {{ returns.to || 0 }} dari {{ returns.total || 0 }} riwayat retur
                </span>
                <div class="flex items-center gap-1.5">
                    <template v-for="l in returns.links" :key="l.label">
                        <Link v-if="l.url" :href="l.url" preserve-state preserve-scroll :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="l.label" />
                        <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                    </template>
                </div>
            </div>
            <div v-else-if="returns.data && returns.data.length > 0" class="px-4 py-3 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs text-muted-foreground font-medium">
                    Menampilkan {{ returns.data.length }} riwayat
                </span>
            </div>
        </div>
        </div>

    <!-- MODAL BUAT RETUR -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
        <form @submit.prevent="submitReturn" class="w-full max-w-lg bg-card rounded-lg shadow-lg flex flex-col max-h-[90vh] overflow-hidden border border-border animate-in zoom-in-95 duration-200">
            
            <!-- HEADER -->
            <div class="px-6 py-4 border-b border-border bg-muted/30 flex-none flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-foreground">Form Retur Obat</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Kembalikan obat ke supplier (faktur)</p>
                </div>
                <button type="button" @click="showModal = false" class="p-1 text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- BODY -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                
                <!-- Supplier -->
                <div>
                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Pilih Supplier</label>
                    <select v-model="selectedSupplierId" @change="form.incoming_stock_id = ''" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                        <option value="">-- Pilih Supplier --</option>
                        <option v-for="sup in availableSuppliers" :key="sup.id" :value="sup.id">
                            {{ sup.nama_pbf }}
                        </option>
                    </select>
                </div>

                <!-- Faktur & Obat -->
                <div v-if="selectedSupplierId">
                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Pilih Faktur / Obat</label>
                    <select v-model="form.incoming_stock_id" required class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                        <option value="">-- Pilih Faktur (Batch) --</option>
                        <option v-for="st in availableStocks" :key="st.id" :value="st.id">
                            [{{ st.faktur_no }}] {{ st.medicine?.nama_obat }} - Batch {{ st.batch_no }}
                        </option>
                    </select>
                </div>

                <!-- Detail & Stok (Readonly) -->
                <div v-if="selectedStockData" class="p-4 bg-muted/40 border border-border rounded-md space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-muted-foreground">Obat</span>
                        <span class="font-medium text-foreground">{{ selectedStockData.medicine?.nama_obat }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-muted-foreground">Batch / EXP</span>
                        <span class="font-medium text-foreground">{{ selectedStockData.batch_no }} / {{ formatDate(selectedStockData.expired_date) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm pt-2 border-t border-border">
                        <span class="text-muted-foreground">Stok Tersedia (Faktur)</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary/10 text-primary rounded-md font-semibold text-xs">
                            {{ selectedStockData.qty_converted }} <span class="text-xs uppercase">{{ selectedStockData.medicine?.satuan_stok }}</span>
                        </span>
                    </div>
                </div>

                <!-- Satuan Retur -->
                <div v-if="selectedStockData">
                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Satuan Fisik Retur</label>
                    <select v-model="form.satuan_level" required class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                        <option v-if="selectedStockData.satuan_input" value="besar">{{ selectedStockData.satuan_input }} (Kemasan Terbesar)</option>
                        <option v-if="selectedStockData.mid_unit" value="menengah">{{ selectedStockData.mid_unit }} (Kemasan Menengah)</option>
                        <option v-if="selectedStockData.medicine?.satuan_stok" value="terkecil">{{ selectedStockData.medicine.satuan_stok }} (Satuan Dasar)</option>
                    </select>
                    <p class="text-xs text-muted-foreground mt-1">Pilih kemasan fisik asli yang akan dikembalikan ke supplier.</p>
                </div>

                <!-- Jumlah Retur -->
                <div v-if="selectedStockData">
                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Jumlah Retur ({{ getSelectedUnitName() }})</label>
                    <input type="number" v-model="form.qty_retur_input" required min="1" :max="maxQtyInput"
                        class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm font-semibold text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" />
                    <div class="flex items-center justify-between mt-1">
                        <p class="text-xs text-muted-foreground">Maksimal retur: {{ maxQtyInput }} {{ getSelectedUnitName() }}</p>
                        <p class="text-xs font-semibold text-primary">Ekuivalen: -{{ convertedQtyToReturn }} {{ selectedStockData.medicine?.satuan_stok }}</p>
                    </div>
                    <p v-if="form.errors.qty_retur" class="text-xs text-red-500 mt-1">{{ form.errors.qty_retur }}</p>
                </div>

                <!-- Alasan -->
                <div v-if="selectedStockData">
                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Alasan Retur</label>
                    <select v-model="form.alasan_retur" required class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                        <option value="">-- Pilih Alasan --</option>
                        <option value="Kemasan Rusak">Kemasan Rusak</option>
                        <option value="Obat Rusak / Cacat">Obat Rusak / Cacat</option>
                        <option value="Mendekati Kadaluarsa (ED)">Mendekati Kadaluarsa (ED)</option>
                        <option value="Salah Kirim Barang">Salah Kirim Barang</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                    <p v-if="form.errors.alasan_retur" class="text-xs text-red-500 mt-1">{{ form.errors.alasan_retur }}</p>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t border-border bg-card flex-none flex justify-end gap-2">
                <button type="button" @click="showModal = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground text-sm font-medium rounded-md transition-colors">
                    Batal
                </button>
                <button type="submit" :disabled="form.processing || !selectedStockData" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-md shadow-sm transition-colors">
                    {{ form.processing ? 'Memproses...' : 'Simpan Retur' }}
                </button>
            </div>
        </form>
        </div>
    </Teleport>
</template>
