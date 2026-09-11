<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';
import { formatInventoryThreshold } from '@/lib/utils';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    stocks: Object,
    suppliers: Array,
    medicines: Array,
    settings: Object,
    filters: Object
});

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
    result.sort((a, b) => new Date(b.tanggal_faktur) - new Date(a.tanggal_faktur));
    return result;
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
    is_single_unit: false,
    small_unit_skipped: false,
    unit_conversion_factor: 1,
    satuan_input: 'Box',
    mid_unit: 'Strip',
    mid_to_big_ratio: 1,
    small_to_mid_ratio: 1,
    box_to_strip_ratio: 1,
    strip_to_tablet_ratio: 1,
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
    }
};

const getPrimaryUnit = (item) => {
    return item.new_medicine_satuan_kecil || 'Tablet';
};

const onSingleUnitChange = (item) => {
    if (item.is_single_unit) {
        item.small_unit_skipped = false;
        item.mid_unit = null;
        item.mid_to_big_ratio = 1;
        item.small_to_mid_ratio = 1;
        item.box_to_strip_ratio = 1;
        item.strip_to_tablet_ratio = 1;
        item.unit_conversion_factor = 1;
        item.satuan_input = item.new_medicine_satuan_kecil;
    } else {
        if (!item.satuan_input || item.satuan_input === item.new_medicine_satuan_kecil) {
            item.satuan_input = 'Box';
        }
        if (!item.mid_unit) {
            item.mid_unit = 'Strip';
        }
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
        if (item.is_single_unit) {
            item.unit_conversion_factor = 1;
            item.mid_to_big_ratio = 1;
            item.small_to_mid_ratio = 1;
            item.box_to_strip_ratio = 1;
            item.strip_to_tablet_ratio = 1;
            item.mid_unit = null;
            item.satuan_input = item.new_medicine_satuan_kecil;
        } else if (item.small_unit_skipped) {
            const factor = Number(item.unit_conversion_factor) || 1;
            item.small_to_mid_ratio = factor;
            item.strip_to_tablet_ratio = factor;
            item.mid_unit = null;
            item.mid_to_big_ratio = 1;
            item.box_to_strip_ratio = 1;
        } else {
            const m2b = Number(item.mid_to_big_ratio) || 1;
            const s2m = Number(item.small_to_mid_ratio) || 1;
            item.mid_to_big_ratio = m2b;
            item.small_to_mid_ratio = s2m;
            item.box_to_strip_ratio = m2b;
            item.strip_to_tablet_ratio = s2m;
            item.unit_conversion_factor = m2b * s2m;
        }
        item.min_stock_threshold = Number(item.min_stock_threshold) || 10;
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
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-5 rounded-lg border border-border shadow-xs">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-foreground">Faktur Masuk</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Sistem pencatatan pengadaan obat dan verifikasi stok (multi-item).</p>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <button @click="showModal=true; form.reset(); form.items=[getEmptyItem()]; form.clearErrors()" class="h-10 px-5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold inline-flex items-center justify-center gap-2 transition-colors w-full sm:w-auto shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Input Faktur Baru
            </button>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="mt-4 bg-card p-3.5 rounded-lg border border-border flex flex-col md:flex-row gap-3 justify-between items-center shadow-xs">
        <div class="flex items-center gap-2 w-full">
            <div class="flex-1 relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input 
                    type="text" 
                    v-model="searchInvoiceQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Cari nomor faktur atau nama supplier..." 
                    class="w-full h-10 pl-10 pr-3.5 bg-background border border-input rounded-md text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors"
                />
            </div>
            <button 
                @click="handleSearch"
                :disabled="isFetching"
                class="h-10 px-5 bg-primary hover:bg-primary/90 disabled:opacity-50 text-primary-foreground text-sm font-semibold rounded-md transition-colors flex items-center justify-center gap-2 shrink-0 shadow-sm"
            >
                <svg v-if="isFetching" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Cari</span>
            </button>
        </div>
    </div>

    <!-- Accordion List Faktur -->
    <div class="mt-4 space-y-3">
        <div v-if="groupedInvoices.length === 0" class="text-center py-12 bg-card rounded-lg border border-border text-sm text-muted-foreground">
            <p>Belum ada faktur yang tercatat.</p>
        </div>

        <div v-for="group in groupedInvoices" :key="group.faktur_no" class="bg-card rounded-lg border border-border overflow-hidden transition-colors shadow-xs">
            <!-- Accordion Header -->
            <button @click="toggleAccordion(group.faktur_no)" class="w-full flex flex-col md:flex-row md:items-center justify-between p-4 md:px-5 hover:bg-muted/50 transition-colors gap-3 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-md bg-muted text-foreground flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-base text-foreground tracking-tight">{{ group.faktur_no }}</span>
                            <span class="px-2.5 py-0.5 bg-muted text-muted-foreground rounded-md text-xs font-semibold">{{ group.items.length }} Item</span>
                        </div>
                        <div class="text-sm text-muted-foreground mt-0.5">{{ group.supplier?.nama_pbf }} &bull; {{ new Date(group.tanggal_faktur).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between md:justify-end gap-5 md:w-auto w-full border-t md:border-t-0 pt-3 md:pt-0 border-border">
                    <div class="text-left md:text-right">
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Total Tagihan (Inc PPN {{ group.ppn_persen }}%)</p>
                        <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-md bg-muted/60 flex items-center justify-center text-muted-foreground shrink-0 transition-transform duration-200" :class="{'rotate-180': openAccordions[group.faktur_no]}">
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
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Barang</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Batch</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Exp Date</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jumlah Barang (Qty)</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Harga Beli</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Disc (%)</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Jumlah Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in group.items" :key="item.id" class="hover:bg-muted/30 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-foreground">{{ item.medicine?.nama_obat }}</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ item.satuan_input }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center text-xs px-2.5 py-1 rounded border border-border bg-muted/40 text-muted-foreground tracking-wide font-medium">
                                        {{ item.batch_no }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) }}
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-sm text-foreground">{{ item.qty_masuk }}</td>
                                <td class="px-4 py-3 text-right text-sm text-muted-foreground">Rp {{ Number(item.harga_beli_satuan).toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-3 text-center text-sm text-muted-foreground">{{ Number(item.diskon_persen) }}%</td>
                                <td class="px-4 py-3 text-right font-bold text-sm text-emerald-600 dark:text-emerald-400">Rp {{ Number(item.jumlah_harga).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-card border-t border-border">
                            <tr>
                                <td colspan="6" class="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sub Nota</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-sm text-foreground">Rp {{ group.subtotal.toLocaleString('id-ID') }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">PPN ({{ group.ppn_persen }}%)</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-sm text-rose-600 dark:text-rose-400">+ Rp {{ (group.subtotal * (group.ppn_persen/100)).toLocaleString('id-ID') }}</td>
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

    <!-- MODAL INPUT FAKTUR BARU (Teleport Body, z-[100] Overlay Fullscreen, max-w-4xl) -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 sm:p-6">
            <div class="bg-card rounded-xl border border-border shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200" @click.stop>
            <!-- Modal Header (Persis Gambar 2) -->
            <div class="px-6 py-4 border-b border-border flex items-center justify-between bg-card shrink-0 z-20">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-muted/40 text-foreground rounded-lg border border-border flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground tracking-tight">Input Faktur Pembelian</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Tambahkan satu atau banyak obat dalam satu faktur.</p>
                    </div>
                </div>
                <button @click="showModal=false" class="w-8 h-8 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form @submit.prevent="submit" class="flex-1 overflow-y-auto bg-card/30 custom-scrollbar p-6 space-y-6">
                <!-- Bagian 1: Data Faktur -->
                <div class="bg-card p-5 rounded-xl border border-border space-y-4 shadow-xs">
                    <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                        <span class="w-5 h-5 rounded bg-muted text-foreground flex items-center justify-center text-xs font-bold border border-border">1</span>
                        Informasi Faktur Utama
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">No. Faktur <span class="text-destructive">*</span></label>
                            <input v-model="form.faktur_no" type="text" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Nomor faktur dari supplier" required />
                            <p v-if="form.errors.faktur_no" class="mt-1 text-xs text-destructive">{{ form.errors.faktur_no }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Supplier <span class="text-destructive">*</span></label>
                            <div class="relative">
                                <div 
                                    @click.stop="toggleDropdown('supplier', 0)"
                                    class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm font-medium text-foreground transition-colors cursor-pointer flex justify-between items-center shadow-xs hover:bg-muted/40"
                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'supplier_0'}"
                                >
                                    <span class="truncate" :class="{'text-muted-foreground': !form.supplier_id}">
                                        {{ suppliers?.find(s => s.id === form.supplier_id)?.nama_pbf || 'Pilih Supplier...' }}
                                    </span>
                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'supplier_0'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                
                                <div v-if="activeDropdown === 'supplier_0'" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-lg overflow-hidden text-popover-foreground">
                                    <div class="max-h-56 overflow-y-auto custom-scrollbar p-1.5 grid grid-cols-1 gap-1">
                                        <div 
                                            v-for="sup in suppliers" 
                                            :key="sup.id" 
                                            @click.stop="form.supplier_id = sup.id; activeDropdown = null"
                                            class="px-3.5 py-2 text-sm hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer transition-colors"
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
                            <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Tanggal Faktur <span class="text-destructive">*</span></label>
                            <input v-model="form.tanggal_faktur" type="date" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Upload Foto Faktur (Opsional)</label>
                            <input type="file" @change="e => form.foto_faktur = e.target.files[0]" class="block w-full text-xs text-muted-foreground file:mr-3.5 file:py-2 file:px-3.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-muted file:text-foreground hover:file:bg-muted/80 cursor-pointer bg-background border border-input rounded-md p-1.5 transition-colors" accept="image/*,.pdf" />
                            <p v-if="form.errors.foto_faktur" class="mt-1 text-xs text-destructive">{{ form.errors.foto_faktur }}</p>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Daftar Obat (Multi-Item) -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded bg-muted text-foreground flex items-center justify-center text-xs font-bold border border-border">2</span>
                            Daftar Obat (Items) <span class="text-destructive">*</span>
                        </h4>
                        <button type="button" @click="addItem" class="text-xs font-semibold bg-card hover:bg-muted text-foreground px-3.5 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 border border-border shadow-xs">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Tambah Baris Obat
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- ITEM WRAPPER CARD (PERSIS GAMBAR 2) -->
                        <div v-for="(item, index) in form.items" :key="index" class="p-5 bg-card border border-border/80 rounded-xl relative space-y-4 shadow-xs">
                            <!-- Hapus Baris -->
                            <button v-if="form.items.length > 1" type="button" @click="removeItem(index)" class="absolute top-4 right-4 w-7 h-7 rounded-md bg-destructive/10 text-destructive flex items-center justify-center hover:bg-destructive/20 transition-colors z-10" title="Hapus Baris">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>

                            <!-- Checkbox Obat Baru -->
                            <div class="flex items-center justify-between pr-10">
                                <label class="flex items-center gap-2 text-xs font-bold text-foreground cursor-pointer uppercase tracking-wider">
                                    <input type="checkbox" v-model="item.is_new_medicine" class="rounded border-input text-primary focus:ring-ring w-4 h-4">
                                    Input Obat Baru (Belum ada di katalog)
                                </label>
                            </div>

                            <!-- ROW 1: Pilihan Obat, Qty, Harga Beli, Disc, Total -->
                            <div class="grid grid-cols-12 gap-3.5">
                                <!-- PILIH OBAT -->
                                <div class="col-span-12 sm:col-span-4 relative">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">{{ item.is_new_medicine ? 'Nama Obat Baru' : 'PILIH OBAT' }} <span class="text-destructive">*</span></label>
                                    
                                    <div v-if="!item.is_new_medicine" class="relative">
                                        <input type="text" v-model="item.search_query" @focus="item.active_dropdown = true" @blur="hideDropdown(item)" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" placeholder="Ketik nama obat..." required />
                                        <div v-if="item.active_dropdown" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-lg max-h-56 overflow-y-auto p-1.5 text-popover-foreground">
                                            <div v-for="m in filteredMedicines(item.search_query)" :key="m.id" @click.prevent="selectMedicine(index, m)" class="px-3.5 py-2 hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer text-sm flex justify-between items-center transition-colors">
                                                <span class="font-medium text-foreground">{{ m.nama_obat }}</span>
                                                <span class="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded font-semibold">{{ m.satuan_stok }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input v-else v-model="item.new_medicine_name" type="text" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" placeholder="Masukkan nama obat baru..." required />
                                </div>
                                
                                <!-- QTY -->
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">QTY <span class="text-destructive">*</span></label>
                                    <input v-model.number="item.qty_masuk" type="number" min="1" class="w-full h-10 bg-background border border-input rounded-md px-3 text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none transition-colors shadow-xs" required />
                                </div>

                                <!-- HARGA BELI -->
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">HARGA BELI <span class="text-destructive">*</span></label>
                                    <input v-model.number="item.harga_beli_satuan" type="number" step="0.01" min="0" class="w-full h-10 bg-background border border-input rounded-md px-3 text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" required />
                                </div>

                                <!-- DISC (%) -->
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">DISC (%) <span class="text-destructive">*</span></label>
                                    <input v-model.number="item.diskon_persen" type="number" step="0.01" min="0" max="100" class="w-full h-10 bg-background border border-input rounded-md px-3 text-sm font-bold text-center text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" required />
                                </div>

                                <!-- TOTAL -->
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">TOTAL</label>
                                    <input :value="calculateItemSubtotal(item).toLocaleString('id-ID')" type="text" class="w-full h-10 bg-muted/40 border border-border rounded-md px-3 text-sm font-bold text-center text-foreground shadow-xs cursor-default" readonly />
                                </div>
                            </div>

                            <!-- ROW 2: Batch, Exp Date, Kategori Obat (jika baru) -->
                            <div class="grid grid-cols-12 gap-3.5">
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">BATCH NO <span class="text-destructive">*</span></label>
                                    <input v-model="item.batch_no" type="text" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" placeholder="Contoh: BATCH-01" required />
                                </div>
                                <div class="col-span-12 sm:col-span-3">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">EXP DATE <span class="text-destructive">*</span></label>
                                    <input v-model="item.expired_date" type="date" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" required />
                                </div>
                                <div v-if="item.is_new_medicine" class="col-span-12 sm:col-span-5">
                                    <label class="block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Kategori Obat</label>
                                    <input v-model="item.new_medicine_kategori" type="text" class="w-full h-10 bg-background border border-input rounded-md px-3.5 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors shadow-xs" placeholder="Contoh: Analgesik"/>
                                </div>
                            </div>

                            <!-- ROW 3: KONVERSI MULTI-SATUAN CONTAINER -->
                            <div class="mt-4 p-4 bg-muted/20 border border-border/70 rounded-lg space-y-3.5">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-1 border-b border-border/50">
                                    <div class="flex items-center gap-2 text-foreground font-bold text-xs uppercase tracking-wider whitespace-nowrap shrink-0">
                                        <span>KONVERSI MULTI - SATUAN</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Checkbox 1: Abaikan Satuan Menengah -->
                                        <label v-if="!item.is_single_unit" 
                                               class="flex items-center gap-1.5 text-xs font-semibold cursor-pointer bg-card px-2.5 py-1 rounded-md border border-border hover:bg-muted transition-colors shadow-xs"
                                               :class="item.small_unit_skipped ? 'border-primary/50 text-primary bg-primary/5' : 'text-muted-foreground'">
                                            <input type="checkbox" v-model="item.small_unit_skipped" class="rounded border-input text-primary focus:ring-ring w-3.5 h-3.5 shrink-0">
                                            <span class="whitespace-nowrap">Abaikan Satuan Menengah</span>
                                        </label>

                                        <!-- Checkbox 2: Satuan Tunggal (Tanpa Konversi) -->
                                        <label class="flex items-center gap-1.5 text-xs font-semibold cursor-pointer bg-card px-2.5 py-1 rounded-md border border-border hover:bg-muted transition-colors shadow-xs"
                                               :class="item.is_single_unit ? 'border-primary/50 text-primary bg-primary/5' : 'text-muted-foreground'">
                                            <input type="checkbox" v-model="item.is_single_unit" @change="onSingleUnitChange(item)" class="rounded border-input text-primary focus:ring-ring w-3.5 h-3.5 shrink-0">
                                            <span class="whitespace-nowrap">Satuan Tunggal (Tanpa Konversi)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- BLOK 1: SATUAN TUNGGAL AKTIF (v-if) -->
                                <div v-if="item.is_single_unit" class="p-4 bg-card border border-border rounded-md shadow-xs">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-md border border-emerald-200 dark:border-emerald-800">
                                                Satuan Tunggal (Rasio 1:1)
                                            </span>
                                        </div>
                                        <div class="w-full md:w-72 space-y-1.5">
                                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Satuan Barang <span class="text-destructive">*</span></label>
                                            <div class="relative">
                                                <div 
                                                    @click.stop="toggleDropdown('unit_single', index)"
                                                    class="w-full h-10 bg-background border border-input rounded-md px-3 text-sm font-medium flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit_single_' + index}"
                                                >
                                                    <span class="truncate text-foreground font-semibold">{{ item.new_medicine_satuan_kecil || 'Pilih Satuan' }}</span>
                                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit_single_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                                
                                                <div v-if="activeDropdown === 'unit_single_' + index" class="absolute z-30 w-full left-0 right-0 top-full mt-1 bg-popover border border-border rounded-md shadow-xl overflow-hidden text-popover-foreground">
                                                    <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                        <div v-for="category in unitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                            <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{{ category.name }}</div>
                                                            <div class="p-1 grid grid-cols-1 gap-0.5">
                                                                <div 
                                                                    v-for="u in category.units" 
                                                                    :key="u" @click.stop="item.new_medicine_satuan_kecil = u; item.satuan_input = u; activeDropdown = null"
                                                                    class="px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer font-medium text-foreground"
                                                                >{{ u }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BLOK 2: GRID KEMASAN MULTI-SATUAN (v-else) -->
                                <div v-else 
                                     class="gap-3.5 transition-all duration-200"
                                     :class="item.small_unit_skipped ? 'grid grid-cols-1 md:grid-cols-2' : 'flex flex-col md:flex-row'">
                                    
                                    <!-- LEVEL 1: TERSIER (Terbesar) -->
                                    <div class="p-3 bg-card border border-border rounded-md space-y-2 shadow-xs transition-all duration-200"
                                         :class="item.small_unit_skipped ? 'w-full' : 'w-full md:w-36 shrink-0'">
                                        <label class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider block whitespace-nowrap">TERSIER (TERBESAR)</label>
                                        <div class="relative">
                                            <div 
                                                @click.stop="toggleDropdown('unit1', index)"
                                                class="w-full h-10 bg-background border border-input rounded-md px-3 text-sm font-semibold flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit1_' + index}"
                                            >
                                                <span class="truncate text-foreground font-semibold">{{ item.satuan_input || 'Box' }}</span>
                                                <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit1_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </div>
                                            <div v-if="activeDropdown === 'unit1_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-popover border border-border rounded-md shadow-xl overflow-hidden text-popover-foreground">
                                                <div class="max-h-48 overflow-y-auto custom-scrollbar p-1 grid grid-cols-1 gap-0.5">
                                                    <div 
                                                        v-for="u in ['Box', 'Karton', 'Dus', 'Kaleng', 'Botol', 'Tube', 'Pcs', 'Pack']" 
                                                        :key="u" @click.stop="item.satuan_input = u; activeDropdown = null"
                                                        class="px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer font-medium text-foreground"
                                                    >{{ u }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 2: SEKUNDER (Menengah) - Dropdown Sejajar Pas dari Kotak Stok Kiri -->
                                    <div v-if="!item.small_unit_skipped" class="flex-1 min-w-0 p-3 bg-card border border-border rounded-md space-y-2 shadow-xs">
                                        <label class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider block whitespace-nowrap">SEKUNDER (MENENGAH)</label>
                                        <div class="relative">
                                            <div class="flex items-center gap-2">
                                                <div class="flex items-center gap-1.5 shrink-0 bg-muted/40 border border-border rounded-md px-2.5 h-10">
                                                    <span class="text-xs font-semibold text-muted-foreground whitespace-nowrap">1 {{ item.satuan_input || 'Box' }} =</span>
                                                    <input v-model.number="item.mid_to_big_ratio" type="number" min="1" class="w-12 h-7 bg-background border border-input/60 rounded px-1 text-sm font-bold text-center text-foreground [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                                                </div>
                                                
                                                <div 
                                                    @click.stop="toggleDropdown('unit2', index)"
                                                    class="flex-1 min-w-0 h-10 bg-background border border-input rounded-md px-3 text-sm font-semibold flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit2_' + index}"
                                                >
                                                    <span class="truncate text-foreground font-semibold">{{ item.mid_unit || 'Strip' }}</span>
                                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit2_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <!-- Popup Sejajar dari Kotak Stok Kiri hingga Kanan Tombol -->
                                            <div v-if="activeDropdown === 'unit2_' + index" class="absolute z-30 w-full left-0 right-0 top-full mt-1 bg-popover border border-border rounded-md shadow-xl overflow-hidden text-popover-foreground">
                                                <div class="max-h-60 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in midUnitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                        <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.mid_unit = u; activeDropdown = null"
                                                                class="px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer font-medium text-foreground"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 3: PRIMER (Terkecil) - Dropdown Sejajar Pas dari Kotak Stok Kiri Kemasan 3 -->
                                    <div class="p-3 bg-card border border-border rounded-md space-y-2 shadow-xs transition-all duration-200"
                                         :class="item.small_unit_skipped ? 'w-full' : 'flex-1 min-w-0'">
                                        <label class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider block whitespace-nowrap">
                                            {{ item.small_unit_skipped ? 'SEKUNDER' : 'PRIMER' }} (TERKECIL)
                                        </label>
                                        <div class="relative">
                                            <div class="flex items-center gap-2">
                                                <div class="flex items-center gap-1.5 shrink-0 bg-muted/40 border border-border rounded-md px-2.5 h-10">
                                                    <span class="text-xs font-semibold text-muted-foreground whitespace-nowrap">
                                                        1 {{ !item.small_unit_skipped ? (item.mid_unit || 'Strip') : (item.satuan_input || 'Box') }} =
                                                    </span>
                                                    
                                                    <input v-if="!item.small_unit_skipped" v-model.number="item.small_to_mid_ratio" type="number" min="1" class="w-12 h-7 bg-background border border-input/60 rounded px-1 text-sm font-bold text-center text-foreground [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                                                    <input v-else v-model.number="item.unit_conversion_factor" type="number" min="1" class="w-12 h-7 bg-background border border-input/60 rounded px-1 text-sm font-bold text-center text-foreground [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                                                </div>
                                                
                                                <div 
                                                    @click.stop="toggleDropdown('unit3', index)"
                                                    class="flex-1 min-w-0 h-10 bg-background border border-input rounded-md px-3 text-sm font-semibold flex justify-between items-center cursor-pointer transition-colors hover:bg-muted/50"
                                                    :class="{'ring-1 ring-ring border-input': activeDropdown === 'unit3_' + index}"
                                                >
                                                    <span class="truncate text-foreground font-semibold">{{ item.new_medicine_satuan_kecil || 'Tablet' }}</span>
                                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit3_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <!-- Popup Sejajar dari Kotak Stok Kiri Kemasan 3 hingga Kanan Tombol -->
                                            <div v-if="activeDropdown === 'unit3_' + index" class="absolute z-30 w-full left-0 right-0 top-full mt-1 bg-popover border border-border rounded-md shadow-xl overflow-hidden text-popover-foreground">
                                                <div class="max-h-60 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in unitCategories" :key="category.name" class="border-b border-border last:border-0">
                                                        <div class="px-3 py-1.5 bg-muted sticky top-0 z-10 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.new_medicine_satuan_kecil = u; activeDropdown = null"
                                                                class="px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground rounded-md cursor-pointer font-medium text-foreground"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Baris Ambang Batas Stok Kritis & Visualisasi ERP (Persis Gambar 2) -->
                                <div class="p-3.5 bg-card border border-border rounded-lg shadow-xs">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                        <!-- Kolom Kiri: Input Ambang Batas -->
                                        <div class="space-y-1">
                                            <label class="block text-xs font-bold text-foreground uppercase tracking-wider">
                                                AMBANG BATAS STOK KRITIS <span class="text-destructive">*</span>
                                            </label>
                                            <div class="relative flex items-center">
                                                <input 
                                                    v-model.number="item.min_stock_threshold" 
                                                    type="number" 
                                                    min="0" 
                                                    class="w-full h-10 bg-background border border-input rounded-md pl-3.5 pr-24 text-sm font-bold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none transition-colors" 
                                                    placeholder="10" 
                                                    required 
                                                />
                                                <div class="absolute right-1.5 px-3 py-1 bg-muted rounded text-xs font-bold text-foreground pointer-events-none uppercase tracking-wider border border-border">
                                                    {{ getPrimaryUnit(item) }}
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-muted-foreground">
                                                Angka dihitung berdasarkan Satuan Terkecil (Primer: <strong>{{ getPrimaryUnit(item) }}</strong> yang dipilih diatas).
                                            </p>
                                        </div>

                                        <!-- Kolom Kanan: Visualisasi Konversi & ERP Formatter -->
                                        <div class="p-3 bg-muted/20 border border-border/70 rounded-md flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-4">
                                            <div class="space-y-0.5 min-w-0">
                                                <p class="text-[10px] sm:text-[11px] font-bold text-muted-foreground uppercase tracking-wider sm:whitespace-nowrap">
                                                    VISUALISASI KONVERSI MULTI-SATUAN:
                                                </p>
                                                <p class="text-sm font-extrabold text-foreground break-words">
                                                    {{ formatInventoryThreshold(item.min_stock_threshold, item.is_single_unit ? 1 : item.small_to_mid_ratio, item.is_single_unit ? 1 : (item.small_unit_skipped ? 1 : item.mid_to_big_ratio), { tersier: item.satuan_input, sekunder: item.mid_unit, primer: getPrimaryUnit(item) }) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 3: Ringkasan Total & Submit (Persis Gambar 2) -->
                <div class="bg-muted/40 rounded-xl border border-border p-5 flex flex-col md:flex-row justify-between items-center gap-4 mt-6 shadow-xs">
                    <div class="text-muted-foreground w-full md:w-auto space-y-1.5">
                        <div class="flex items-center gap-6">
                            <span class="text-sm">Subtotal:</span>
                            <span class="font-bold text-foreground">Rp {{ formSubtotal.toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex items-center gap-6">
                            <span class="text-sm flex items-center gap-2">PPN (Otomatis {{ ppnLocker }}% dari Sistem):</span>
                            <span class="font-bold text-rose-600 dark:text-rose-400">+ Rp {{ (formSubtotal * (ppnLocker/100)).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                    <div class="text-right w-full md:w-auto border-t border-border pt-3 md:border-0 md:pt-0">
                        <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-0.5">TOTAL TAGIHAN (GRAND TOTAL)</p>
                        <p class="text-2xl lg:text-3xl font-extrabold tracking-tight text-foreground">Rp {{ formGrandTotal.toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="flex justify-end items-center gap-3 pt-3">
                    <button type="button" @click="showModal=false" class="h-10 px-5 rounded-md text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-10 px-6 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-bold transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm">
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Simpan & Aktifkan Semua Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
    </Teleport>
</div>
</template>
