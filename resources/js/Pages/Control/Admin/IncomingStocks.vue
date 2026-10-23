<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableFooter } from '@/Components/ui/table';
import IncomingInvoiceModal from './Partials/IncomingInvoiceModal.vue';
import EditIncomingInvoiceModal from './Partials/EditIncomingInvoiceModal.vue';
import { invoiceGrandTotal } from './Partials/useStockCalculations';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    stocks: Object,
    settings: Object,
    filters: Object,
    suppliers: Array,
    medicines: Array,
    past_invoices: Array,
});

const showInputModal = ref(false);
const showEditModal = ref(false);
const editingInvoice = ref(null);

const showPhotoLightbox = ref(false);
const previewInvoice = ref(null);

const openPhotoPreview = (group) => {
    previewInvoice.value = group;
    showPhotoLightbox.value = true;
};

const closePhotoPreview = () => {
    showPhotoLightbox.value = false;
    previewInvoice.value = null;
};

const openEditInvoiceModal = (group) => {
    editingInvoice.value = group;
    showEditModal.value = true;
};

const searchInvoiceQuery = ref(props.filters?.search || '');
const activeFilterTab = ref('all');
const selectedSupplierFilter = ref('');
const openAccordions = ref({});

const activeStocks = computed(() => props.stocks || { data: [], total: 0 });

const handleLiveSearch = useDebounceFn((val) => {
    router.get(route('control.admin.incoming-stocks'), {
        search: val || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        showProgress: false,
        only: ['stocks', 'filters'],
    });
}, 400);

watch(searchInvoiceQuery, (newVal) => {
    handleLiveSearch(newVal);
});

const clearSearch = () => {
    searchInvoiceQuery.value = '';
    handleLiveSearch('');
};

const toggleAccordion = (faktur_no) => {
    openAccordions.value[faktur_no] = !openAccordions.value[faktur_no];
};

const groupedInvoices = computed(() => {
    const rawStocks = activeStocks.value?.data || [];
    const groups = {};

    rawStocks.forEach(stock => {
        if (!groups[stock.faktur_no]) {
            groups[stock.faktur_no] = {
                faktur_no: stock.faktur_no,
                supplier: stock.supplier,
                tanggal_faktur: stock.tanggal_faktur,
                foto_faktur_path: stock.foto_faktur_path,
                ppn_persen: stock.ppn_persen,
                items: [],
                subtotal: 0,
            };
        }
        groups[stock.faktur_no].items.push(stock);
        groups[stock.faktur_no].subtotal += Number(stock.jumlah_harga || 0);
    });

    const result = Object.values(groups);
    result.sort((a, b) => new Date(b.tanggal_faktur) - new Date(a.tanggal_faktur));
    return result;
});

const filteredInvoices = computed(() => {
    let list = groupedInvoices.value || [];

    if (activeFilterTab.value === 'pending_photo') {
        list = list.filter(g => !g.foto_faktur_path);
    } else if (activeFilterTab.value === 'complete') {
        list = list.filter(g => !!g.foto_faktur_path);
    }

    if (selectedSupplierFilter.value) {
        list = list.filter(g => String(g.supplier?.id) === String(selectedSupplierFilter.value) || g.supplier?.nama_pbf === selectedSupplierFilter.value);
    }

    if (searchInvoiceQuery.value && searchInvoiceQuery.value.trim()) {
        const q = searchInvoiceQuery.value.toLowerCase().trim();
        list = list.filter(g => {
            const matchFaktur = g.faktur_no?.toLowerCase().includes(q);
            const matchSupplier = g.supplier?.nama_pbf?.toLowerCase().includes(q);
            const matchItems = g.items?.some(it => 
                it.medicine?.nama_obat?.toLowerCase().includes(q) ||
                it.no_batch?.toLowerCase().includes(q)
            );
            return matchFaktur || matchSupplier || matchItems;
        });
    }

    return list;
});

const stats = computed(() => {
    const list = groupedInvoices.value || [];
    const total = list.length;
    const pendingPhoto = list.filter(g => !g.foto_faktur_path).length;
    const complete = Math.max(0, total - pendingPhoto);
    const totalAmount = list.reduce((sum, g) => sum + invoiceGrandTotal(g), 0);
    const uniqueSuppliers = new Set(list.map(g => g.supplier?.id || g.supplier_id || g.nama_pbf).filter(Boolean)).size;
    return { total, pendingPhoto, complete, totalAmount, uniqueSuppliers };
});

const handleGlobalKeydown = (e) => {
    if (e.key === 'F2' && !showInputModal.value) {
        e.preventDefault();
        showInputModal.value = true;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
<Head title="Faktur Masuk" />
<div class="space-y-8">
    <!-- BENTO COCKPIT HEADER -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
            <!-- Baris 1: Filter Tabs (Pills) di kiri & Tombol Input Faktur di kanan -->
            <div class="flex items-center justify-between gap-2 w-full">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    <button
                        type="button"
                        @click="activeFilterTab = 'all'"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                        :class="activeFilterTab === 'all' 
                            ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span>Semua Faktur</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'all' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ stats.total }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'pending_photo'"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                        :class="activeFilterTab === 'pending_photo' 
                            ? 'bg-amber-500 text-white shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="activeFilterTab === 'pending_photo' ? 'bg-white' : 'bg-amber-500'"></span>
                        <span>Perlu Berkas</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'pending_photo' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
                        >{{ stats.pendingPhoto }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'complete'"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                        :class="activeFilterTab === 'complete' 
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="activeFilterTab === 'complete' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400'"></span>
                        <span>Lengkap</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'complete' ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ stats.complete }}</span>
                    </button>
                </div>

                <!-- Tombol Input Faktur di Desktop -->
                <button 
                    @click="showInputModal = true" 
                    class="hidden sm:inline-flex h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer justify-center"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    <span>Input Faktur</span>
                </button>
            </div>

            <!-- Baris 2: Search Input & Dropdown PBF -->
            <div class="flex items-center gap-2 w-full">
                <div class="relative flex-1">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input 
                        v-model="searchInvoiceQuery" 
                        type="text" 
                        placeholder="Cari nomor faktur, supplier, obat..." 
                        class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                    />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                        <button 
                            v-if="searchInvoiceQuery" 
                            type="button" 
                            @click="clearSearch" 
                            class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="w-36 sm:w-44 shrink-0">
                    <select 
                        v-model="selectedSupplierFilter" 
                        class="w-full h-9 px-2.5 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 cursor-pointer truncate transition-all"
                    >
                        <option value="">Semua PBF</option>
                        <option v-for="sup in (suppliers || [])" :key="sup.id" :value="sup.id">
                            {{ sup.nama_pbf }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SISI KANAN: 2 METRIK KARTU BERDAMPINGAN (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
            <!-- Card 1: Total Belanja Faktur -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        TOTAL BELANJA
                    </span>
                    <span class="text-[9.5px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900">
                        Inc. PPN
                    </span>
                </div>
                <div class="my-1 truncate">
                    <span class="text-lg sm:text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        Rp {{ stats.totalAmount.toLocaleString('id-ID') }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    {{ stats.total }} faktur terdata
                </p>
            </div>

            <!-- Card 2: Audit Berkas Fisik -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        AUDIT BERKAS
                    </span>
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" :class="stats.pendingPhoto > 0 ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted text-slate-400 dark:text-muted-foreground'">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.pendingPhoto > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                        {{ stats.pendingPhoto }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Faktur</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    {{ stats.pendingPhoto > 0 ? 'Belum ada bukti fisik' : 'Semua berkas lengkap' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Accordion List -->
    <div class="space-y-3">
        <div v-if="filteredInvoices.length === 0" class="text-center py-12 bg-card rounded-lg border border-border text-sm text-muted-foreground">
            <p v-if="activeFilterTab !== 'all' || selectedSupplierFilter">Tidak ada faktur yang sesuai dengan filter yang dipilih.</p>
            <p v-else>Belum ada faktur yang tercatat.</p>
        </div>

        <div v-for="group in filteredInvoices" :key="group.faktur_no" class="bg-card rounded-lg border border-border overflow-hidden transition-colors">
            <!-- Accordion Header -->
            <button @click="toggleAccordion(group.faktur_no)" class="w-full flex flex-col md:flex-row md:items-center justify-between p-4 md:px-5 hover:bg-muted/50 transition-colors gap-3 text-left cursor-pointer">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-md bg-muted text-foreground flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-base text-foreground tracking-tight">{{ group.faktur_no }}</span>
                            <span class="px-2 py-0.5 bg-muted text-muted-foreground rounded-md text-xs font-medium">{{ group.items.length }} Item</span>
                            <button 
                                v-if="group.foto_faktur_path && group.items && group.items.length" 
                                type="button" 
                                @click.stop="openPhotoPreview(group)" 
                                class="h-5 px-3 rounded-lg text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/25 transition-all inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Lihat Foto / Dokumen Faktur tanpa load"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                <span>Lihat Faktur</span>
                            </button>
                            <button 
                                v-if="!group.foto_faktur_path" 
                                type="button" 
                                @click.stop="openEditInvoiceModal(group)" 
                                class="h-5 px-2.5 rounded-lg text-xs font-semibold bg-amber-500/15 text-amber-800 dark:text-amber-300 hover:bg-amber-500/25 border border-amber-500/30 transition-all inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Upload foto fisik nota yang belum ada"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span>+ Upload Nota</span>
                            </button>
                            <button 
                                type="button" 
                                @click.stop="openEditInvoiceModal(group)" 
                                class="h-5 px-3 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-muted text-slate-700 dark:text-foreground hover:bg-slate-200 dark:hover:bg-muted/80 border border-slate-200/80 dark:border-border transition-all inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Koreksi data faktur / upload foto nota"
                            >
                                <svg class="w-3 h-3 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Faktur</span>
                            </button>

                        </div>
                        <div class="text-sm text-muted-foreground mt-0.5">{{ group.supplier?.nama_pbf }} &bull; {{ new Date(group.tanggal_faktur).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between md:justify-end gap-5 md:w-auto w-full border-t md:border-t-0 pt-3 md:pt-0 border-border">
                    <div class="text-left md:text-right">
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Total Tagihan (Inc PPN {{ group.ppn_persen }}%)</p>
                        <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="w-7 h-7 rounded-md bg-muted/60 flex items-center justify-center text-muted-foreground shrink-0 transition-transform duration-200" :class="{'rotate-180': openAccordions[group.faktur_no]}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>
            </button>

            <!-- Accordion Body (Table) -->
            <div v-show="openAccordions[group.faktur_no]" class="border-t border-border bg-muted/20">
                <div class="w-full overflow-x-auto custom-scrollbar">
                    <Table class="w-full min-w-[1000px]">
                        <TableHeader>
                            <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                                <TableHead class="min-w-[240px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Nama Obat</TableHead>
                                <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Batch</TableHead>
                                <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Exp Date</TableHead>
                                <TableHead class="min-w-[140px] py-2 pl-5 pr-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Jumlah Barang (Qty)</TableHead>
                                <TableHead class="min-w-[140px] py-2 px-3 pr-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Harga Beli</TableHead>
                                <TableHead class="min-w-[90px] py-2 px-3 pr-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Disc (%)</TableHead>
                                <TableHead class="min-w-[150px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Jumlah Harga</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in group.items" :key="item.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                                <TableCell class="py-2.5 px-3 align-middle overflow-hidden">
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-sm font-medium text-foreground leading-tight truncate">{{ item.medicine?.nama_obat }}</span>
                                        <span class="text-xs text-muted-foreground leading-tight mt-0.5">{{ item.satuan_input }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2.5 px-3 align-middle text-center overflow-hidden">
                                    <span class="text-sm font-medium text-foreground tracking-tight truncate block text-center">{{ item.batch_no }}</span>
                                </TableCell>
                                <TableCell class="py-2.5 px-3 align-middle text-center">
                                    <span class="text-sm font-medium text-foreground tabular-nums block text-center">{{ new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) }}</span>
                                </TableCell>
                                <TableCell class="py-2.5 pl-5 pr-1 align-middle text-center">
                                    <div class="flex items-center justify-center">
                                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md border border-border/80 bg-muted/40 text-xs font-medium text-foreground">
                                            <span class="font-semibold tabular-nums text-foreground">{{ item.qty_masuk }}</span>
                                            <span class="text-muted-foreground uppercase text-[11px] tracking-wide">{{ item.satuan_input }}</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2.5 px-3 pr-4 align-middle text-right">
                                    <span class="text-sm font-medium tabular-nums text-foreground block">Rp {{ Number(item.harga_beli_satuan).toLocaleString('id-ID') }}</span>
                                </TableCell>
                                <TableCell class="py-2.5 px-3 pr-4 align-middle text-right">
                                    <span class="text-sm font-medium tabular-nums text-foreground block">{{ Number(item.diskon_persen) }}%</span>
                                </TableCell>
                                <TableCell class="py-2.5 px-3 align-middle text-right">
                                    <span class="text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400 block">Rp {{ Number(item.jumlah_harga).toLocaleString('id-ID') }}</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <TableFooter class="bg-card border-t border-border">
                            <TableRow class="h-9 border-b border-border/40">
                                <TableCell colspan="6" class="py-1.5 px-3 pr-4 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sub Nota</TableCell>
                                <TableCell class="py-1.5 px-3 text-right font-medium text-sm text-foreground tabular-nums">Rp {{ group.subtotal.toLocaleString('id-ID') }}</TableCell>
                            </TableRow>
                            <TableRow class="h-9 border-b border-border/40">
                                <TableCell colspan="6" class="py-1.5 px-3 pr-4 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">PPN ({{ group.ppn_persen }}%)</TableCell>
                                <TableCell class="py-1.5 px-3 text-right font-medium text-sm text-rose-600 dark:text-rose-400 tabular-nums">+ Rp {{ (group.subtotal * (group.ppn_persen/100)).toLocaleString('id-ID') }}</TableCell>
                            </TableRow>
                            <TableRow class="h-10 bg-muted/30">
                                <TableCell colspan="6" class="py-2 px-3 pr-4 text-right text-xs font-bold text-foreground uppercase tracking-wider">Total</TableCell>
                                <TableCell class="py-2 px-3 text-right font-bold text-emerald-600 dark:text-emerald-400 text-sm tabular-nums">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
            </div>
        </div>

        <!-- Pagination Footer -->
        <div v-if="activeStocks.links && activeStocks.links.length > 3" class="px-5 py-3 bg-card rounded-lg border border-border flex items-center justify-between shadow-sm">
            <p class="text-xs font-medium text-muted-foreground">Halaman {{ activeStocks.current_page || 1 }} dari {{ activeStocks.last_page || 1 }} (Total {{ activeStocks.total || 0 }} data faktur)</p>
            <div class="flex items-center gap-1">
                <template v-for="(link, i) in activeStocks.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll :only="['stocks', 'filters']"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal Input Faktur Baru -->
    <IncomingInvoiceModal 
        v-model="showInputModal"
        :suppliers="suppliers"
        :medicines="medicines"
        :default-ppn="settings?.default_ppn"
        :past-invoices="past_invoices"
    />

    <!-- Modal Edit Faktur (Smart Guarded) -->
    <EditIncomingInvoiceModal
        v-model="showEditModal"
        :invoice="editingInvoice"
        :suppliers="suppliers"
        :medicines="medicines"
    />

    <!-- ═══ MODAL PRATINJAU FOTO FAKTUR (ZERO-LOAD LIGHTBOX) ═══ -->
    <Teleport to="body">
        <div 
            v-if="showPhotoLightbox && previewInvoice" 
            class="fixed inset-0 bg-black/80 backdrop-blur-xs z-[9999] flex flex-col items-center justify-center p-3 sm:p-6 animate-in fade-in duration-150"
            @click.self="closePhotoPreview"
        >
            <div class="relative max-w-4xl w-full bg-card border border-border rounded-xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden" @click.stop>
                <!-- Lightbox Header -->
                <div class="p-3.5 px-4 border-b border-border flex items-center justify-between bg-muted/30">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-md bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-foreground leading-tight">
                                Berkas Faktur: {{ previewInvoice.faktur_no }}
                            </h4>
                            <p class="text-[11px] text-muted-foreground">
                                {{ previewInvoice.supplier?.nama_pbf }} &bull; {{ previewInvoice.tanggal_faktur }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a 
                            v-if="previewInvoice.items && previewInvoice.items.length" 
                            :href="route('control.admin.incoming-stocks.faktur-file', previewInvoice.items[0].id)" 
                            target="_blank" 
                            class="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md border border-input bg-background hover:bg-muted transition-colors font-medium"
                            title="Buka file asli di tab baru untuk unduh/cetak"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            <span>Tab Baru</span>
                        </a>
                        <button 
                            type="button" 
                            @click="closePhotoPreview" 
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Lightbox Body -->
                <div class="p-4 flex-1 flex items-center justify-center bg-slate-950/15 overflow-auto min-h-[350px] max-h-[75vh]">
                    <iframe 
                        v-if="previewInvoice.foto_faktur_path && previewInvoice.foto_faktur_path.toLowerCase().endsWith('.pdf') && previewInvoice.items && previewInvoice.items.length" 
                        :src="route('control.admin.incoming-stocks.faktur-file', previewInvoice.items[0].id)" 
                        class="w-full h-[70vh] border-0 rounded-lg shadow-inner bg-white"
                    ></iframe>
                    <img 
                        v-else-if="previewInvoice.items && previewInvoice.items.length" 
                        :src="route('control.admin.incoming-stocks.faktur-file', previewInvoice.items[0].id)" 
                        alt="Foto Berkas Faktur" 
                        class="max-w-full max-h-[72vh] object-contain rounded-lg shadow-md"
                    />
                    <div v-else class="text-xs text-muted-foreground py-10">
                        File faktur tidak tersedia.
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</div>
</template>
