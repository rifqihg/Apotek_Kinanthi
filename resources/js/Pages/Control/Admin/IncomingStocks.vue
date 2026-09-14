<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';

defineOptions({ layout: ControlLayout });
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';
import { formatInventoryThreshold } from '@/lib/utils';

const props = defineProps({ stocks: Object, suppliers: Array, medicines: Array, settings: Object, filters: Object });
const showModal = ref(false);
const page = usePage();
const searchInvoiceQuery = ref(props.filters?.search || '');
const activeSearch = ref(props.filters?.search || '');

const { data: queryStocks, isFetching } = useQuery({
    queryKey: computed(() => ['admin-incoming-stocks', activeSearch.value]),
    queryFn: async () => {
        const res = await axios.get(route('control.admin.incoming-stocks'), {
            params: { search: activeSearch.value },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        return res.data.props.stocks;
    },
    initialData: () => props.stocks,
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (prev) => prev,
});

const activeStocks = computed(() => queryStocks.value || props.stocks);

const handleSearch = () => {
    activeSearch.value = searchInvoiceQuery.value;
};

const hideDropdown = (item) => {
    setTimeout(() => {
        item.active_dropdown = false;
    }, 200);
};

// Global Dropdown Management
const activeDropdown = ref(null);
const toggleDropdown = (name, index) => {
    const key = name + '_' + index;
    activeDropdown.value = activeDropdown.value === key ? null : key;
};

// Close dropdown when clicking outside
const handleClickOutside = () => {
    activeDropdown.value = null;
};
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const openAccordions = ref({});

const toggleAccordion = (faktur_no) => {
    openAccordions.value[faktur_no] = !openAccordions.value[faktur_no];
};

const groupedInvoices = computed(() => {
    let rawStocks = activeStocks.value?.data || [];
    
    // Group by faktur_no
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

    let result = Object.values(groups);

    // Sort by Date
    result.sort((a, b) => new Date(b.tanggal_faktur) - new Date(a.tanggal_faktur));
    return result;
});

const stats = computed(() => {
    const list = groupedInvoices.value || [];
    const total = list.length;
    const pendingPhoto = list.filter(g => !g.foto_faktur_path).length;
    const uniqueSuppliers = new Set(list.map(g => g.supplier?.id || g.supplier_id || g.nama_pbf).filter(Boolean)).size;
    return { total, pendingPhoto, uniqueSuppliers };
});

const invoiceGrandTotal = (group) => {
    const ppnPercent = Number(group.ppn_persen) || 0;
    const ppn = group.subtotal * (ppnPercent / 100);
    return group.subtotal + ppn;
};

// MULTI-ITEM FORM LOGIC
const getEmptyItem = () => ({
    medicine_id: '',
    search_query: '',
    is_new_medicine: false,
    new_medicine_name: '',
    new_medicine_kategori: '',
    new_medicine_satuan_kecil: 'Tablet',
    new_medicine_rasio_konversi: 1,
    no_multi_unit: false,
    small_unit_skipped: false,
    unit_conversion_factor: 1,
    satuan_input: 'Box',
    mid_unit: 'Strip',
    mid_to_big_ratio: 1,
    small_to_mid_ratio: 1,
    min_stock_threshold: 10,
    batch_no: '',
    expired_date: '',
    qty_masuk: 1,
    harga_beli_satuan: 0,
    diskon_persen: 0,
    active_dropdown: false,
});

const form = useForm({ 
    faktur_no: '', 
    supplier_id: '', 
    tanggal_faktur: new Date().toISOString().substr(0, 10), 
    foto_faktur: null,
    items: [getEmptyItem()]
});

const ppnLocker = computed(() => {
    return Number(props.settings?.default_ppn || 11);
});

const addItem = () => {
    form.items.push(getEmptyItem());
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const filteredMedicines = (query) => {
    if (!query) return props.medicines || [];
    return (props.medicines || []).filter(m => 
        (m.nama_obat || '').toLowerCase().includes(query.toLowerCase())
    );
};

const selectMedicine = (index, m) => {
    const item = form.items[index];
    item.medicine_id = m.id;
    item.search_query = m.nama_obat;
    item.active_dropdown = false;
    item.unit_conversion_factor = m.rasio_konversi || 1;
    item.new_medicine_satuan_kecil = m.satuan_stok || 'Tablet';
    if (m.threshold_min !== undefined && m.threshold_min !== null) {
        item.min_stock_threshold = m.threshold_min;
    }
    if (m.satuan_besar) {
        item.satuan_input = m.satuan_besar;
    }
    if (m.satuan_menengah) {
        item.mid_unit = m.satuan_menengah;
        item.small_unit_skipped = false;
        item.no_multi_unit = false;
    } else if (m.satuan_besar && m.satuan_besar === m.satuan_stok && (!m.rasio_konversi || m.rasio_konversi == 1)) {
        item.no_multi_unit = true;
        item.small_unit_skipped = false;
    } else if (m.satuan_besar && m.satuan_besar !== m.satuan_stok) {
        item.no_multi_unit = false;
        item.small_unit_skipped = true;
    } else {
        item.no_multi_unit = false;
        item.small_unit_skipped = false;
    }
};

const getPrimaryUnit = (item) => {
    if (item.no_multi_unit) {
        return item.satuan_input || 'Unit';
    }
    return item.new_medicine_satuan_kecil || 'Tablet';
};

const getItemThresholdPreview = (item) => {
    if (item.no_multi_unit) {
        return `${item.min_stock_threshold || 0} ${getPrimaryUnit(item)}`;
    }
    const rSekunder = item.small_unit_skipped 
        ? (Number(item.unit_conversion_factor) || 1) 
        : (Number(item.small_to_mid_ratio) || 1);
    const rTersier = item.small_unit_skipped 
        ? 1 
        : (Number(item.mid_to_big_ratio) || 1);
    const midUnit = item.small_unit_skipped ? null : item.mid_unit;

    return formatInventoryThreshold(
        item.min_stock_threshold,
        rSekunder,
        rTersier,
        {
            tersier: item.satuan_input,
            sekunder: midUnit,
            primer: getPrimaryUnit(item),
        }
    );
};

const handleNoMultiUnitChange = (item) => {
    if (item.no_multi_unit) {
        item.small_unit_skipped = false;
    }
};

const midUnitCategories = [
    { name: 'Sediaan Padat', units: ['Box', 'Dus', 'Dst', 'Cc', 'Fold', 'Strip', 'Blister', 'Papan'] },
    { name: 'Sediaan Cair & Semi-Padat', units: ['Box', 'Pcs', 'Ampul', 'Vial', 'Sachet'] },
    { name: 'Sediaan Cair Generik', units: ['Pack', 'Slp'] },
];

const unitCategories = [
    { name: 'Sediaan Padat (Oral)', units: ['Tablet', 'Kaplet', 'Kapsul', 'Pcs', 'Butir', 'Strip', 'Blister'] },
    { name: 'Sediaan Cair (Sirup & Tetes)', units: ['Botol', 'Flsh', 'Btl', 'Sachet', 'Ssch'] },
    { name: 'Sediaan Topikal & Semi-Padat', units: ['Tube', 'Tbe', 'Pot'] },
    { name: 'Sediaan Steril & Injeksi', units: ['Ampul', 'Amp', 'Vial'] },
    { name: 'Sediaan Khusus', units: ['Suppositoria', 'Supp', 'Canister', 'Inhaler'] },
];

const singleUnitCategories = [
    { name: 'Sediaan Steril & Injeksi', units: ['Ampul', 'Vial', 'Infus', 'Pen'] },
    { name: 'Sediaan Cair & Sirup', units: ['Botol', 'Flsh', 'Sachet', 'Drop', 'Pack'] },
    { name: 'Sediaan Topikal & Salep', units: ['Tube', 'Pot', 'Gel'] },
    { name: 'Sediaan Padat & Oral', units: ['Tablet', 'Kaplet', 'Kapsul', 'Strip', 'Blister', 'Pcs', 'Box'] },
    { name: 'Sediaan Khusus & Lainnya', units: ['Suppositoria', 'Inhaler', 'Canister', 'Patch', 'Koyo'] },
];

const calculateItemSubtotal = (item) => {
    const h = Number(item.harga_beli_satuan) || 0;
    const q = Number(item.qty_masuk) || 0;
    const d = Number(item.diskon_persen) || 0;
    return (h * (1 - (d / 100))) * q;
};

const formSubtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + calculateItemSubtotal(item), 0);
});

const formGrandTotal = computed(() => {
    const ppn = formSubtotal.value * (ppnLocker.value / 100);
    return formSubtotal.value + ppn;
});

function submit() {
    form.items.forEach(item => {
        if (item.no_multi_unit) {
            item.unit_conversion_factor = 1;
            item.mid_to_big_ratio = 1;
            item.small_to_mid_ratio = 1;
            item.mid_unit = null;
            item.new_medicine_satuan_kecil = item.satuan_input;
        } else if (item.small_unit_skipped) {
            item.small_to_mid_ratio = item.unit_conversion_factor;
            item.mid_unit = null; // Clear mid unit so backend knows it's a 2-level hierarchy
            item.mid_to_big_ratio = 1;
        } else {
            item.unit_conversion_factor = (item.mid_to_big_ratio || 1) * (item.small_to_mid_ratio || 1);
        }
    });

    form.post(route('control.admin.incoming-stocks.store'), {
        forceFormData: true,
        onSuccess: () => { 
            showModal.value = false; 
            form.reset(); 
            form.items = [getEmptyItem()];
            form.clearErrors(); 
        }
    });
}
</script>

<template>
<Head title="Faktur Masuk" />
<div class="space-y-6">
    <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- HERO BANNER CARD (lg:col-span-8) -->
        <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
            <!-- Left Text Column -->
            <div class="relative z-10 max-w-sm sm:max-w-md">
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                    Penerimaan Faktur Masuk
                </h1>
                <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                    Verifikasi berkas pembelian sediaan farmasi dari distributor, nomor batch, dan tanggal kedaluwarsa.
                </p>
            </div>

            <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
            <div class="relative z-10 flex items-center justify-center shrink-0">
                <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Invoice Sheet (behind left) -->
                        <rect x="4" y="4" width="30" height="44" rx="3.5" stroke="#0284C7" stroke-width="2.2" fill="#FFFFFF" />
                        <rect x="8" y="9" width="13" height="4" rx="1.2" fill="#BAE6FD" />
                        <rect x="23" y="9" width="6.5" height="4" rx="1.2" fill="#0284C7" />
                        <line x1="8" y1="18" x2="26" y2="18" stroke="#0284C7" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <line x1="8" y1="23" x2="23" y2="23" stroke="#0284C7" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <line x1="8" y1="28" x2="25" y2="28" stroke="#0284C7" stroke-opacity="0.3" stroke-width="1.5" stroke-linecap="round" />
                        <circle cx="19" cy="37" r="5" stroke="#0284C7" stroke-width="1.5" fill="#E0F2FE" />
                        <path d="M16.5 37l1.8 1.8 4-4" stroke="#0284C7" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        <!-- Delivery Box (in front right) -->
                        <g transform="translate(20, 20)">
                            <rect x="0" y="3" width="31" height="28" rx="3" stroke="#0284C7" stroke-width="2.2" fill="#FFFFFF" />
                            <path d="M0 11h31" stroke="#0284C7" stroke-width="1.6" />
                            <rect x="12.5" y="3" width="6" height="28" fill="#E0F2FE" stroke="#0284C7" stroke-width="1.2" />
                            <rect x="3.5" y="15" width="6.5" height="8.5" rx="1" stroke="#BAE6FD" stroke-width="1.2" fill="#F0F9FF" />
                            <line x1="5" y1="18" x2="8.5" y2="18" stroke="#0284C7" stroke-width="0.9" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 2 STAT CARDS (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-3">
            <!-- Card 1: Perlu Bukti Fisik -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        PERLU BUKTI FISIK
                    </span>
                    <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.pendingPhoto > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.pendingPhoto > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                        {{ stats.pendingPhoto }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                    {{ stats.pendingPhoto > 0 ? 'Belum ada berkas fisik' : 'Semua berkas terlampir' }}
                </p>
            </div>

            <!-- Card 2: Total Faktur -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        TOTAL FAKTUR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ stats.total }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                    Faktur masuk diverifikasi
                </p>
            </div>
        </div>
    </div>

    <!-- SEARCH & ACTION BAR -->
    <div class="bg-card p-3 rounded-lg border border-border flex flex-col sm:flex-row gap-3 justify-between items-center">
        <div class="flex items-center gap-2 w-full sm:flex-1">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input 
                    type="text" 
                    v-model="searchInvoiceQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Cari no faktur atau nama supplier..." 
                    class="w-full h-9 pl-9 pr-3 bg-background border border-input rounded-md text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors"
                />
            </div>
            <button 
                @click="handleSearch"
                :disabled="isFetching"
                class="h-9 px-4 bg-primary hover:bg-primary/90 disabled:opacity-50 text-primary-foreground text-sm font-medium rounded-md transition-colors flex items-center justify-center gap-2 shrink-0 shadow-sm"
            >
                <svg v-if="isFetching" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Cari</span>
            </button>
        </div>
        <button @click="showModal=true; form.reset(); form.items=[getEmptyItem()]; form.clearErrors()" class="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium inline-flex items-center justify-center gap-2 transition-colors w-full sm:w-auto shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Input Faktur Baru
        </button>
    </div>

    <!-- Accordion List -->
    <div class="mt-4 space-y-3">
        <div v-if="groupedInvoices.length === 0" class="text-center py-12 bg-card rounded-lg border border-border text-sm text-muted-foreground">
            <p>Belum ada faktur yang tercatat.</p>
        </div>

        <div v-for="group in groupedInvoices" :key="group.faktur_no" class="bg-card rounded-lg border border-border overflow-hidden transition-colors">
            <!-- Accordion Header -->
            <button @click="toggleAccordion(group.faktur_no)" class="w-full flex flex-col md:flex-row md:items-center justify-between p-4 md:px-5 hover:bg-muted/50 transition-colors gap-3 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-md bg-muted text-foreground flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-base text-foreground tracking-tight">{{ group.faktur_no }}</span>
                            <span class="px-2 py-0.5 bg-muted text-muted-foreground rounded-md text-xs font-medium">{{ group.items.length }} Item</span>
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
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/40 border-b border-border">
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Barang</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Batch</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Exp Date</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jumlah Barang (Qty)</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Harga Beli</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Disc (%)</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Jumlah Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in group.items" :key="item.id" class="hover:bg-muted/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-foreground">{{ item.medicine?.nama_obat }}</p>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span class="text-xs text-muted-foreground">{{ item.satuan_input }}</span>
                                        <span class="text-muted-foreground/60 text-xs">&bull;</span>
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded bg-muted/60 text-muted-foreground border border-border font-medium">
                                            Ambang Batas: {{ item.medicine?.threshold_min ?? 10 }} {{ item.medicine?.satuan_stok || 'Unit' }}
                                        </span>
                                        <span 
                                            v-if="item.medicine"
                                            class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded font-semibold border"
                                            :class="Number(item.medicine.stok_total) <= Number(item.medicine.threshold_min) 
                                                ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' 
                                                : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'"
                                        >
                                            {{ Number(item.medicine.stok_total) <= Number(item.medicine.threshold_min) ? 'Perlu Restock' : 'Stok Aman' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center text-xs px-2 py-0.5 rounded border border-border bg-muted/40 text-muted-foreground tracking-wide">
                                        {{ item.batch_no }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-sm text-foreground">{{ item.qty_masuk }}</span>
                                    <span class="block text-[11px] text-muted-foreground mt-0.5">
                                        &asymp; {{ item.qty_converted || (item.qty_masuk * (item.unit_conversion_factor || 1)) }} {{ item.medicine?.satuan_stok || 'Unit' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-muted-foreground">Rp {{ Number(item.harga_beli_satuan).toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 text-center text-sm text-muted-foreground">{{ Number(item.diskon_persen) }}%</td>
                                <td class="px-4 py-3 text-right font-bold text-sm text-emerald-600 dark:text-emerald-400">Rp {{ Number(item.jumlah_harga).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-card border-t border-border">
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sub Nota</td>
                                <td class="px-4 py-2 text-right font-semibold text-sm text-foreground">Rp {{ group.subtotal.toLocaleString('id-ID') }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">PPN ({{ group.ppn_persen }}%)</td>
                                <td class="px-4 py-2 text-right font-semibold text-sm text-rose-600 dark:text-rose-400">+ Rp {{ (group.subtotal * (group.ppn_persen/100)).toLocaleString('id-ID') }}</td>
                            </tr>
                            <tr class="bg-muted/30">
                                <td colspan="6" class="px-4 py-3 text-right text-sm font-bold text-foreground uppercase tracking-wider">Total</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400 text-base">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Faktur Baru -->
    <Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-card rounded-lg border border-border shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200" @click.stop>
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-border flex items-center justify-between bg-card shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-muted text-foreground rounded-md flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground tracking-tight">Input Faktur Pembelian</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Tambahkan satu atau banyak obat dalam satu faktur.</p>
                    </div>
                </div>
                <button @click="showModal=false" class="w-8 h-8 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="flex-1 overflow-y-auto bg-card custom-scrollbar p-6 space-y-5">
                <!-- Bagian 1: Data Faktur -->
                <div class="bg-card p-4 rounded-lg border border-border space-y-4">
                    <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                        <span class="w-5 h-5 rounded-md bg-muted text-foreground flex items-center justify-center text-xs font-bold">1</span>
                        Informasi Faktur Utama
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">No. Faktur <span class="text-destructive">*</span></label>
                            <input v-model="form.faktur_no" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                            <p v-if="form.errors.faktur_no" class="mt-1 text-xs text-destructive">{{ form.errors.faktur_no }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Supplier <span class="text-destructive">*</span></label>
                            <div class="relative">
                                <div 
                                    @click.stop="toggleDropdown('supplier', 0)"
                                    class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-medium text-foreground transition-colors cursor-pointer flex justify-between items-center"
                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'supplier_0'}"
                                >
                                    <span class="truncate" :class="{'text-muted-foreground': !form.supplier_id}">
                                        {{ suppliers?.find(s => s.id === form.supplier_id)?.nama_pbf || 'Pilih Supplier...' }}
                                    </span>
                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'supplier_0'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                
                                <div v-if="activeDropdown === 'supplier_0'" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                    <div class="max-h-56 overflow-y-auto custom-scrollbar p-1 grid grid-cols-1 gap-0.5">
                                        <div 
                                            v-for="sup in suppliers" 
                                            :key="sup.id" 
                                            @click.stop="form.supplier_id = sup.id; activeDropdown = null"
                                            class="px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer transition-colors"
                                            :class="{'bg-accent text-accent-foreground font-semibold': form.supplier_id === sup.id}"
                                        >
                                            {{ sup.nama_pbf }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="form.errors.supplier_id" class="mt-1 text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tanggal Faktur <span class="text-destructive">*</span></label>
                            <input v-model="form.tanggal_faktur" type="date" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Upload Foto Faktur (Opsional)</label>
                            <input type="file" @change="e => form.foto_faktur = e.target.files[0]" class="block w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-muted file:text-foreground hover:file:bg-muted/80 cursor-pointer bg-background border border-input rounded-md p-1 transition-colors" accept="image/*,.pdf" />
                            <p v-if="form.errors.foto_faktur" class="mt-1 text-xs text-destructive">{{ form.errors.foto_faktur }}</p>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Daftar Obat (Multi-Item) -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-muted text-foreground flex items-center justify-center text-xs font-bold">2</span>
                            Daftar Obat (Items) <span class="text-destructive">*</span>
                        </h4>
                        <button type="button" @click="addItem" class="text-xs font-medium bg-muted hover:bg-muted/80 text-foreground px-3 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 border border-border">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Tambah Baris Obat
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(item, index) in form.items" :key="index" class="p-4 bg-background border border-border rounded-lg relative space-y-4">
                            <!-- Hapus Baris -->
                            <button v-if="form.items.length > 1" type="button" @click="removeItem(index)" class="absolute top-4 right-4 w-7 h-7 rounded-md bg-destructive/10 text-destructive flex items-center justify-center hover:bg-destructive/20 transition-colors" title="Hapus Baris">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>

                            <!-- Checkbox Obat Baru -->
                            <div class="flex items-center justify-between pr-10">
                                <label class="flex items-center gap-2 text-xs font-semibold text-foreground cursor-pointer uppercase tracking-wider">
                                    <input type="checkbox" v-model="item.is_new_medicine" class="rounded border-input text-primary focus:ring-ring w-4 h-4">
                                    Input Obat Baru (Belum ada di katalog)
                                </label>
                            </div>

                            <div class="grid grid-cols-12 gap-3">
                                <!-- PILIH OBAT (col-span-12 or 4) -->
                                <div class="col-span-12 md:col-span-4 relative">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">{{ item.is_new_medicine ? 'Nama Obat Baru' : 'Pilih Obat' }} <span class="text-destructive">*</span></label>
                                    
                                    <div v-if="!item.is_new_medicine" class="relative">
                                        <input type="text" v-model="item.search_query" @focus="item.active_dropdown = true" @blur="hideDropdown(item)" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Ketik nama obat..." required />
                                        <div v-if="item.active_dropdown" class="absolute z-20 w-full mt-1 bg-popover border border-border rounded-md shadow-md max-h-48 overflow-y-auto p-1">
                                            <div v-for="m in filteredMedicines(item.search_query)" :key="m.id" @click.prevent="selectMedicine(index, m)" class="px-3 py-1.5 hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer text-sm flex justify-between items-center">
                                                <span class="font-medium text-foreground">{{ m.nama_obat }}</span>
                                                <span class="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded">{{ m.satuan_stok }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input v-else v-model="item.new_medicine_name" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Masukkan nama obat baru..." required />
                                </div>
                                
                                <!-- QTY (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Qty <span class="text-destructive">*</span></label>
                                    <input v-model="item.qty_masuk" type="number" min="1" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>

                                <!-- HARGA BELI (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Harga Beli <span class="text-destructive">*</span></label>
                                    <input v-model="item.harga_beli_satuan" type="number" step="0.01" min="0" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>

                                <!-- DISC (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Disc (%) <span class="text-destructive">*</span></label>
                                    <input v-model="item.diskon_persen" type="number" step="0.01" min="0" max="100" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>

                                <!-- JUMLAH NETTO (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Total</label>
                                    <input :value="calculateItemSubtotal(item).toLocaleString('id-ID')" type="text" class="w-full h-9 bg-muted/40 border border-border rounded-md px-3 py-1 text-sm text-right font-semibold text-foreground" disabled />
                                </div>
                            </div>

                            <!-- BATCH & EXP DATE & KATEGORI (for new med) -->
                            <div class="grid grid-cols-12 gap-3">
                                <div class="col-span-6 md:col-span-3">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Batch No <span class="text-destructive">*</span></label>
                                    <input v-model="item.batch_no" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>
                                <div class="col-span-6 md:col-span-3">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Exp Date <span class="text-destructive">*</span></label>
                                    <input v-model="item.expired_date" type="date" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>
                                <div v-if="item.is_new_medicine" class="col-span-12 md:col-span-6">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Kategori Obat</label>
                                    <input v-model="item.new_medicine_kategori" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: Analgesik, Antibiotik..."/>
                                </div>
                            </div>

                            <!-- UNIT CONVERSION SECTION -->
                            <div class="mt-4 p-3 bg-muted/20 border border-border rounded-lg space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                    <h5 class="text-xs font-semibold text-foreground uppercase tracking-wider">Konversi Multi-Satuan</h5>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Checkbox 1: Tanpa Konversi Multi Satuan -->
                                        <label class="flex items-center gap-2 text-xs font-medium text-muted-foreground cursor-pointer bg-card px-2.5 py-1 rounded-md border border-border hover:bg-muted transition-colors">
                                            <input 
                                                type="checkbox" 
                                                v-model="item.no_multi_unit" 
                                                @change="handleNoMultiUnitChange(item)"
                                                class="rounded border-input text-primary focus:ring-ring w-3.5 h-3.5"
                                            >
                                            Tanpa Konversi Multi Satuan
                                        </label>

                                        <!-- Checkbox 2: Abaikan Satuan Menengah -->
                                        <label 
                                            v-if="!item.no_multi_unit"
                                            class="flex items-center gap-2 text-xs font-medium text-muted-foreground cursor-pointer bg-card px-2.5 py-1 rounded-md border border-border hover:bg-muted transition-colors"
                                        >
                                            <input 
                                                type="checkbox" 
                                                v-model="item.small_unit_skipped" 
                                                class="rounded border-input text-primary focus:ring-ring w-3.5 h-3.5"
                                            >
                                            Abaikan Satuan Menengah
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- KEMASAN TUNGGAL (1 LAPIS) -->
                                <div v-if="item.no_multi_unit" class="p-3 bg-card border border-border rounded-md space-y-2 w-full">
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">
                                            Satuan Kemasan (Tunggal / 1 Lapis)
                                        </label>
                                        <span class="text-[11px] text-muted-foreground">Tanpa konversi (1:1 fisik & stok)</span>
                                    </div>
                                    <div class="relative">
                                        <div 
                                            @click.stop="toggleDropdown('unit_single', index)"
                                            class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-medium flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                            :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit_single_' + index}"
                                        >
                                            <span class="truncate text-foreground font-semibold">{{ item.satuan_input || 'Pilih Satuan' }}</span>
                                            <svg class="w-4 h-4 text-muted-foreground transition-transform" :class="{'rotate-180': activeDropdown === 'unit_single_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </div>
                                        <div v-if="activeDropdown === 'unit_single_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                            <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                <div v-for="category in singleUnitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                    <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider">{{ category.name }}</div>
                                                    <div class="p-1 grid grid-cols-1 gap-0.5">
                                                        <div 
                                                            v-for="u in category.units" 
                                                            :key="u" 
                                                            @click.stop="item.satuan_input = u; item.new_medicine_satuan_kecil = u; activeDropdown = null"
                                                            class="px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer font-medium text-foreground pl-4"
                                                        >{{ u }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- KEMASAN MULTI-SATUAN (2 ATAU 3 LAPIS) -->
                                <div v-else class="flex flex-col md:flex-row gap-3">
                                    <!-- LEVEL 1: TERSIER -->
                                    <div class="p-3 bg-card border border-border rounded-md space-y-2 flex-none w-full transition-all" 
                                         :class="item.small_unit_skipped ? 'md:flex-1' : 'md:w-[155px] shrink-0'">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-xs font-semibold text-muted-foreground uppercase whitespace-nowrap">Tersier (Terbesar)</label>
                                        </div>
                                        <div class="relative">
                                            <div 
                                                @click.stop="toggleDropdown('unit1', index)"
                                                class="w-full h-9 bg-background border border-input rounded-md px-2.5 py-1 text-sm font-medium flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit1_' + index}"
                                            >
                                                <span class="truncate text-foreground font-semibold">{{ item.satuan_input || 'Pilih Satuan' }}</span>
                                                <svg class="w-4 h-4 text-muted-foreground transition-transform" :class="{'rotate-180': activeDropdown === 'unit1_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </div>
                                            <div v-if="activeDropdown === 'unit1_' + index" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                                <div class="max-h-32 overflow-y-auto custom-scrollbar p-1 grid grid-cols-1 gap-0.5">
                                                    <div 
                                                        v-for="u in ['Box', 'Karton', 'Dus', 'Kaleng', 'Botol', 'Tube', 'Pcs']" 
                                                        :key="u" @click.stop="item.satuan_input = u; activeDropdown = null"
                                                        class="px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer font-medium text-foreground"
                                                    >{{ u }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 2: SEKUNDER (MENENGAH) -->
                                    <div v-if="!item.small_unit_skipped" class="p-3 bg-card border border-border rounded-md space-y-2 w-full md:flex-1 min-w-0">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-xs font-semibold text-muted-foreground uppercase whitespace-nowrap">Sekunder (Menengah)</label>
                                        </div>
                                        <div class="flex gap-1.5 relative items-center">
                                            <div class="flex items-center gap-1.5 shrink-0 bg-muted/60 border border-border rounded-md px-2 h-9">
                                                <span class="text-xs font-medium text-muted-foreground whitespace-nowrap">1 {{ item.satuan_input || 'Box' }} =</span>
                                                <input v-model.number="item.mid_to_big_ratio" type="number" min="1" placeholder="1" class="w-14 h-7 bg-background border border-input rounded text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none px-1 transition-colors" required />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div 
                                                    @click.stop="toggleDropdown('unit2', index)"
                                                    class="w-full h-9 bg-background border border-input rounded-md px-2.5 py-1 text-sm font-medium flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit2_' + index}"
                                                >
                                                    <span class="truncate text-foreground font-semibold">{{ item.mid_unit || 'Pilih' }}</span>
                                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit2_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <div v-if="activeDropdown === 'unit2_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                                <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in midUnitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                        <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.mid_unit = u; activeDropdown = null"
                                                                class="px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer font-medium text-foreground pl-4"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 3: PRIMER (TERKECIL) -->
                                    <div class="p-3 bg-card border border-border rounded-md space-y-2 w-full md:flex-1 min-w-0">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-xs font-semibold text-muted-foreground uppercase whitespace-nowrap">{{ item.small_unit_skipped ? 'Primer' : 'Primer' }} (Terkecil)</label>
                                        </div>
                                        <div class="flex gap-1.5 relative items-center">
                                            <div class="flex items-center gap-1.5 shrink-0 bg-muted/60 border border-border rounded-md px-2 h-9">
                                                <span class="text-xs font-medium text-muted-foreground whitespace-nowrap">1 {{ !item.small_unit_skipped ? (item.mid_unit || 'Strip') : (item.satuan_input || 'Box') }} =</span>
                                                
                                                <input v-if="!item.small_unit_skipped" v-model.number="item.small_to_mid_ratio" type="number" min="1" placeholder="1" class="w-14 h-7 bg-background border border-input rounded text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none px-1 transition-colors" required />
                                                <input v-else v-model.number="item.unit_conversion_factor" type="number" min="1" placeholder="1" class="w-14 h-7 bg-background border border-input rounded text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none px-1 transition-colors" required />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div 
                                                    @click.stop="toggleDropdown('unit3', index)"
                                                    class="w-full h-9 bg-background border border-input rounded-md px-2.5 py-1 text-sm font-medium flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit3_' + index}"
                                                >
                                                    <span class="truncate text-foreground font-semibold">{{ item.new_medicine_satuan_kecil || 'Pilih' }}</span>
                                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit3_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <div v-if="activeDropdown === 'unit3_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                                <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in unitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                        <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.new_medicine_satuan_kecil = u; activeDropdown = null"
                                                                class="px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer font-medium text-foreground pl-4"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Baris Ambang Batas Stok Kritis & Visualisasi ERP -->
                            <div class="p-3.5 bg-card border border-border rounded-lg shadow-xs space-y-2">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                    <!-- Kolom Kiri: Input Ambang Batas -->
                                    <div class="space-y-1">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">
                                            Ambang Batas Stok Kritis <span class="text-destructive">*</span>
                                        </label>
                                        <div class="relative flex items-center">
                                            <input 
                                                v-model.number="item.min_stock_threshold" 
                                                type="number" 
                                                min="0" 
                                                class="w-full h-9 bg-background border border-input rounded-md pl-3.5 pr-24 text-sm font-bold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none transition-colors" 
                                                placeholder="10" 
                                                required 
                                            />
                                            <div class="absolute right-1.5 px-2.5 py-1 bg-muted rounded text-xs font-semibold text-foreground pointer-events-none uppercase tracking-wider border border-border">
                                                {{ getPrimaryUnit(item) }}
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground">
                                            Peringatan restock aktif jika sisa stok &le; ambang batas satuan terkecil (<span class="font-medium text-foreground">{{ getPrimaryUnit(item) }}</span>).
                                        </p>
                                    </div>

                                    <!-- Kolom Kanan: Visualisasi Konversi & ERP Formatter -->
                                    <div class="p-3 bg-muted/30 border border-border rounded-md flex flex-col justify-center gap-1">
                                        <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">
                                            Visualisasi Konversi Ambang Batas:
                                        </p>
                                        <p class="text-sm font-bold text-foreground break-words">
                                            {{ getItemThresholdPreview(item) }}
                                        </p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ item.no_multi_unit 
                                                ? 'Kemasan tunggal langsung (tanpa konversi multi-satuan bertingkat).' 
                                                : 'Estimasi kuota kemasan logistik yang setara dengan nilai ambang batas.' 
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Bagian 3: Ringkasan Total -->
                <div class="bg-muted/40 rounded-lg border border-border p-4 flex flex-col md:flex-row justify-between items-center gap-4 mt-6">
                    <div class="text-muted-foreground w-full md:w-auto">
                        <div class="flex justify-between md:justify-start md:gap-8 mb-1.5">
                            <span class="text-sm">Subtotal:</span>
                            <span class="font-semibold text-foreground">Rp {{ formSubtotal.toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex justify-between md:justify-start md:gap-8">
                            <span class="text-sm flex items-center gap-2">PPN (Otomatis {{ ppnLocker }}% dari Sistem):</span>
                            <span class="font-semibold text-rose-600 dark:text-rose-400">+ Rp {{ (formSubtotal * (ppnLocker/100)).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                    <div class="text-right w-full md:w-auto border-t border-border pt-3 md:border-0 md:pt-0">
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-0.5">Total Tagihan (Grand Total)</p>
                        <p class="text-2xl font-bold tracking-tight text-foreground">Rp {{ formGrandTotal.toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" @click="showModal=false" class="h-9 px-4 rounded-md text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-9 px-5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-semibold transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm">
                        <svg v-if="form.processing" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Simpan & Aktifkan Semua Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
    </Teleport>
</div>
</template>
