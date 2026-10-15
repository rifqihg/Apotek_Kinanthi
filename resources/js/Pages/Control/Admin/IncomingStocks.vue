<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/Components/ui/toast/use-toast';

defineOptions({ layout: ControlLayout });
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { formatInventoryThreshold } from '@/lib/utils';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableFooter } from '@/Components/ui/table';

const props = defineProps({ stocks: Object, settings: Object, filters: Object, suppliers: Array, medicines: Array, past_invoices: Array });
const { toast } = useToast();
const showModal = ref(false);
const searchInvoiceQuery = ref(props.filters?.search || '');
const isSearching = ref(false);

const allMedicines = ref(props.medicines || []);
watch(() => props.medicines, (val) => {
    if (val && val.length) {
        allMedicines.value = val;
    }
});

// State untuk Salin dari Faktur Lampau
const showPastInvoicesModal = ref(false);
const allPastInvoices = ref(props.past_invoices || []);
watch(() => props.past_invoices, (val) => {
    if (val && val.length) {
        allPastInvoices.value = val;
    }
});
const isLoadingPastInvoices = ref(false);
const pastInvoiceSearch = ref('');
const pastInvoiceSupplierFilter = ref('');

watch(() => props.filters?.search, (newSearch) => {
    if (newSearch !== undefined && newSearch !== searchInvoiceQuery.value) {
        searchInvoiceQuery.value = newSearch || '';
    }
});

const handleLiveSearch = useDebounceFn((val) => {
    isSearching.value = true;
    router.get(route('control.admin.incoming-stocks'), {
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

watch(searchInvoiceQuery, (newVal) => {
    handleLiveSearch(newVal);
});

const clearSearch = () => {
    searchInvoiceQuery.value = '';
};

const activeStocks = computed(() => props.stocks || { data: [], total: 0 });

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
    filter_text: '',
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

const getFilteredMedicines = (filterText) => {
    if (!filterText) return allMedicines.value;
    const q = filterText.toLowerCase().trim();
    return allMedicines.value.filter(m => (m.nama_obat || '').toLowerCase().includes(q));
};

const toggleMedicineDropdown = (index) => {
    const key = 'medicine_' + index;
    activeDropdown.value = activeDropdown.value === key ? null : key;
    if (activeDropdown.value === key) {
        setTimeout(() => {
            const searchEl = document.getElementById(`input-search-${index}`);
            if (searchEl) {
                searchEl.focus();
                if (searchEl.select) searchEl.select();
            }
        }, 50);
    }
};

const handleInternalSearchEnter = (index) => {
    const item = form.items[index];
    const filtered = getFilteredMedicines(item.filter_text);
    if (filtered.length > 0) {
        selectMedicine(index, filtered[0]);
    }
};

const addItem = () => {
    form.items.push(getEmptyItem());
    const nextIdx = form.items.length - 1;
    setTimeout(() => {
        toggleMedicineDropdown(nextIdx);
    }, 50);
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const fetchMedicines = async (query = '') => {
    try {
        const response = await axios.get(route('control.admin.incoming-stocks.search-medicines'), { params: { query } });
        if (response.data?.medicines) {
            allMedicines.value = response.data.medicines;
        }
    } catch (e) {
        console.error('Gagal memperbarui katalog obat', e);
    }
};

const openInputFakturModal = () => {
    showModal.value = true;
    form.reset();
    form.items = [getEmptyItem()];
    form.clearErrors();
    if (!allMedicines.value.length) {
        fetchMedicines('');
    }
};

const selectMedicine = (index, m) => {
    const item = form.items[index];
    item.medicine_id = m.id;
    item.search_query = m.nama_obat;
    item.filter_text = '';
    activeDropdown.value = null;
    
    // Smart Memory Autofill: Harga Beli & Diskon Terakhir
    if (m.latest_harga_beli !== undefined && m.latest_harga_beli > 0) {
        item.harga_beli_satuan = m.latest_harga_beli;
    }
    if (m.latest_diskon !== undefined) {
        item.diskon_persen = m.latest_diskon;
    }

    // Smart Memory Autofill: Konversi Kemasan Terakhir
    item.new_medicine_satuan_kecil = m.satuan_stok || 'Tablet';
    if (m.threshold_min !== undefined && m.threshold_min !== null) {
        item.min_stock_threshold = m.threshold_min;
    }

    const sInput = m.latest_satuan_input || m.satuan_besar || 'Box';
    const sMid = m.latest_mid_unit || m.satuan_menengah || null;
    const sBase = m.satuan_stok || 'Tablet';
    const rMidToBig = Number(m.latest_mid_to_big) || 1;
    const rSmallToMid = Number(m.latest_small_to_mid) || Number(m.rasio_konversi) || 1;
    const rConversion = Number(m.latest_unit_conversion) || Number(m.rasio_konversi) || 1;

    item.satuan_input = sInput;
    item.unit_conversion_factor = rConversion;

    if (sMid && sMid.toLowerCase() !== sBase.toLowerCase()) {
        item.mid_unit = sMid;
        item.small_unit_skipped = false;
        item.no_multi_unit = false;
        item.mid_to_big_ratio = rMidToBig;
        item.small_to_mid_ratio = rSmallToMid;
    } else if (sInput.toLowerCase() === sBase.toLowerCase() && rConversion <= 1) {
        item.mid_unit = null;
        item.no_multi_unit = true;
        item.small_unit_skipped = false;
        item.mid_to_big_ratio = 1;
        item.small_to_mid_ratio = 1;
    } else {
        item.mid_unit = null;
        item.no_multi_unit = false;
        item.small_unit_skipped = true;
        item.mid_to_big_ratio = 1;
        item.small_to_mid_ratio = rConversion;
    }

    // Excel Flow: Auto-focus to Qty input on medicine selection
    setTimeout(() => {
        const qtyEl = document.getElementById(`input-qty-${index}`);
        if (qtyEl) {
            qtyEl.focus();
            if (qtyEl.select) qtyEl.select();
        }
    }, 50);
};

// Keyboard Excel-like Navigations
const focusNextField = (id) => {
    const el = document.getElementById(id);
    if (el) {
        el.focus();
        if (el.select) el.select();
    }
};

const handleExpDateEnter = (index) => {
    if (index === form.items.length - 1) {
        addItem();
    } else {
        setTimeout(() => {
            toggleMedicineDropdown(index + 1);
        }, 50);
    }
};

// Past Invoices (Faktur Rutin) Management
const filteredPastInvoices = computed(() => {
    let list = allPastInvoices.value || [];
    if (pastInvoiceSupplierFilter.value) {
        list = list.filter(inv => Number(inv.supplier_id) === Number(pastInvoiceSupplierFilter.value));
    }
    if (pastInvoiceSearch.value) {
        const q = pastInvoiceSearch.value.toLowerCase().trim();
        list = list.filter(inv => 
            (inv.faktur_no || '').toLowerCase().includes(q) ||
            (inv.supplier_name || '').toLowerCase().includes(q) ||
            (inv.items || []).some(it => (it.medicine_name || '').toLowerCase().includes(q))
        );
    }
    return list;
});

const openPastInvoicesModal = () => {
    pastInvoiceSupplierFilter.value = form.supplier_id ? String(form.supplier_id) : '';
    pastInvoiceSearch.value = '';
    isLoadingPastInvoices.value = false;
    showPastInvoicesModal.value = true;
};

const fetchPastInvoices = async () => {
    isLoadingPastInvoices.value = true;
    try {
        const res = await axios.get(route('control.admin.incoming-stocks.past-invoices'), {
            params: {
                supplier_id: pastInvoiceSupplierFilter.value || undefined,
                search: pastInvoiceSearch.value || undefined,
            }
        });
        if (res.data?.invoices) {
            const existingNos = new Set((allPastInvoices.value || []).map(i => i.faktur_no));
            const newItems = res.data.invoices.filter(i => !existingNos.has(i.faktur_no));
            allPastInvoices.value = [...allPastInvoices.value, ...newItems];
        }
    } catch (err) {
        console.error('Gagal mengambil riwayat faktur lampau', err);
    } finally {
        isLoadingPastInvoices.value = false;
    }
};

const handlePastInvoiceSearchInput = useDebounceFn(() => {
    if (filteredPastInvoices.value.length === 0 && pastInvoiceSearch.value.trim().length >= 2) {
        fetchPastInvoices();
    }
}, 300);

const applyPastInvoice = (inv) => {
    if (!form.supplier_id && inv.supplier_id) {
        form.supplier_id = inv.supplier_id;
    }

    form.items = inv.items.map(it => {
        const sInput = it.satuan_input || 'Box';
        const sMid = it.mid_unit || null;
        const sBase = it.satuan_stok || 'Tablet';
        const rConversion = it.unit_conversion_factor || 1;
        const isSingle = !sMid && (sInput.toLowerCase() === sBase.toLowerCase() && rConversion <= 1);
        const isSkipMid = !sMid && (sInput.toLowerCase() !== sBase.toLowerCase() || rConversion > 1);

        return {
            medicine_id: it.medicine_id,
            search_query: it.medicine_name,
            filter_text: '',
            is_new_medicine: false,
            new_medicine_name: '',
            new_medicine_kategori: '',
            new_medicine_satuan_kecil: sBase,
            new_medicine_rasio_konversi: it.small_to_mid_ratio || rConversion,
            no_multi_unit: isSingle,
            small_unit_skipped: isSkipMid,
            unit_conversion_factor: rConversion,
            satuan_input: sInput,
            mid_unit: sMid,
            mid_to_big_ratio: it.mid_to_big_ratio || 1,
            small_to_mid_ratio: it.small_to_mid_ratio || 1,
            min_stock_threshold: it.min_stock_threshold || 10,
            batch_no: '',
            expired_date: '',
            qty_masuk: it.qty_masuk || 1,
            harga_beli_satuan: it.harga_beli_satuan || 0,
            diskon_persen: it.diskon_persen || 0,
            active_dropdown: false,
        };
    });

    showPastInvoicesModal.value = false;
    toast({
        title: 'Faktur Berhasil Disalin',
        description: `${inv.items.length} item obat dari faktur ${inv.faktur_no} berhasil dimuat. Silakan isi Qty, Batch No, dan Exp Date baru.`,
    });

    setTimeout(() => {
        const firstBatch = document.getElementById('input-batch-0');
        if (firstBatch) {
            firstBatch.focus();
        }
    }, 100);
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
    // Validasi awal di client agar user tidak bingung jika ada baris yang belum dipilih
    for (let i = 0; i < form.items.length; i++) {
        const item = form.items[i];
        if (!item.is_new_medicine && !item.medicine_id) {
            alert(`Obat baris ke-${i + 1} belum dipilih dari katalog. Silakan ketik dan klik obat dari daftar yang muncul, atau centang 'Input Obat Baru' jika sediaan baru.`);
            return;
        }
        if (item.is_new_medicine && !item.new_medicine_name?.trim()) {
            alert(`Nama obat baru pada baris ke-${i + 1} wajib diisi.`);
            return;
        }
    }

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
            item.unit_conversion_factor = (Number(item.mid_to_big_ratio) || 1) * (Number(item.small_to_mid_ratio) || 1);
        }
    });

    form.post(route('control.admin.incoming-stocks.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { 
            showModal.value = false; 
            form.reset(); 
            form.items = [getEmptyItem()];
            form.clearErrors(); 
            medicines.value = [];
        },
        onError: (errors) => {
            console.error('Terjadi kesalahan validasi faktur masuk:', errors);
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
                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 mb-1.5">
                    PURCHASING · VERIFIKASI FAKTUR
                </div>
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                    Penerimaan Faktur Masuk
                </h1>
                <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                    Verifikasi berkas pembelian sediaan farmasi dari distributor, nomor batch, dan tanggal kedaluwarsa.
                </p>
            </div>

            <!-- Right Integrated Control Panel -->
            <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="searchInvoiceQuery" type="text" placeholder="Cari faktur, supplier..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                        <svg v-if="isSearching" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <button 
                            v-else-if="searchInvoiceQuery" 
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
                <button @click="openInputFakturModal" class="w-full sm:w-auto h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs flex items-center justify-center gap-1.5 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    <span>Input Faktur</span>
                </button>
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
                            <a v-if="group.foto_faktur_path && group.items && group.items.length" :href="route('control.admin.incoming-stocks.faktur-file', group.items[0].id)" target="_blank" @click.stop class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                Lihat Faktur
                            </a>
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
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll 
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
                </template>
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

            <form @submit.prevent="submit" class="flex-1 overflow-y-auto bg-card custom-scrollbar p-6 space-y-5 relative">

                <!-- Alert Validasi Global jika ada eror -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-3.5 bg-destructive/10 border border-destructive/30 rounded-lg text-destructive text-xs space-y-1.5 animate-in fade-in-50 duration-200">
                    <div class="font-semibold flex items-center gap-1.5">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Terdapat kesalahan pada formulir, mohon periksa:
                    </div>
                    <ul class="list-disc list-inside pl-1 space-y-0.5 text-muted-foreground dark:text-destructive-foreground">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

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
                                        {{ props.suppliers?.find(s => s.id === form.supplier_id)?.nama_pbf || 'Pilih Supplier...' }}
                                    </span>
                                    <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'supplier_0'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                
                                <div v-if="activeDropdown === 'supplier_0'" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground">
                                    <div class="max-h-56 overflow-y-auto custom-scrollbar p-1 grid grid-cols-1 gap-0.5">
                                        <div 
                                            v-for="sup in props.suppliers" 
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
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-muted text-foreground flex items-center justify-center text-xs font-bold">2</span>
                            Daftar Obat (Items) <span class="text-destructive">*</span>
                        </h4>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openPastInvoicesModal" class="text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 px-3 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 shadow-2xs">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                <span>Salin Faktur Lampau</span>
                            </button>
                            <button type="button" @click="addItem" class="text-xs font-medium bg-muted hover:bg-muted/80 text-foreground px-3 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 border border-border">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Tambah Baris Obat
                            </button>
                        </div>
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
                                        <!-- Trigger Box Dropdown -->
                                        <div 
                                            @click.stop="toggleMedicineDropdown(index)" 
                                            class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-medium transition-colors cursor-pointer flex justify-between items-center hover:bg-muted/30"
                                            :class="{'ring-1 ring-ring border-input': activeDropdown === 'medicine_' + index}"
                                        >
                                            <div class="flex items-center gap-2 truncate">
                                                <span v-if="item.medicine_id" class="font-semibold text-foreground truncate">
                                                    {{ item.search_query }}
                                                </span>
                                                <span v-else class="text-muted-foreground truncate">
                                                    Pilih Obat dari Katalog...
                                                </span>
                                                <span v-if="item.medicine_id && item.new_medicine_satuan_kecil" class="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground uppercase shrink-0 font-bold border border-border">
                                                    {{ item.new_medicine_satuan_kecil }}
                                                </span>
                                            </div>
                                            <svg class="w-4 h-4 text-muted-foreground transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'medicine_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>

                                        <!-- Dropdown Menu with Search -->
                                        <div v-if="activeDropdown === 'medicine_' + index" class="absolute z-30 w-full mt-1 bg-popover border border-border rounded-md shadow-md overflow-hidden text-popover-foreground animate-in fade-in zoom-in-95 duration-100">
                                            <!-- Internal Search Bar -->
                                            <div class="p-2 border-b border-border bg-muted/30 flex items-center gap-1.5" @click.stop>
                                                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                                </svg>
                                                <input 
                                                    :id="'input-search-' + index"
                                                    type="text" 
                                                    v-model="item.filter_text" 
                                                    @keydown.enter.prevent="handleInternalSearchEnter(index)"
                                                    placeholder="Ketik cari obat, Enter..." 
                                                    class="w-full h-7 bg-background border border-input rounded px-2 text-xs font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors"
                                                />
                                                <button 
                                                    v-if="item.filter_text" 
                                                    type="button" 
                                                    @click.stop="item.filter_text = ''" 
                                                    class="p-0.5 text-muted-foreground hover:text-foreground rounded"
                                                >
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>

                                            <!-- Filtered List of Medicines -->
                                            <div class="max-h-56 overflow-y-auto custom-scrollbar p-1">
                                                <div v-if="getFilteredMedicines(item.filter_text).length === 0" class="px-3 py-3 text-xs text-muted-foreground text-center">
                                                    Obat "{{ item.filter_text }}" tidak ditemukan di katalog.
                                                </div>
                                                <div 
                                                    v-else 
                                                    v-for="m in getFilteredMedicines(item.filter_text)" 
                                                    :key="m.id" 
                                                    @mousedown.prevent="selectMedicine(index, m)"
                                                    class="px-3 py-2 text-xs hover:bg-accent hover:text-accent-foreground rounded-sm cursor-pointer transition-colors flex justify-between items-center"
                                                    :class="{'bg-accent/60 font-semibold text-accent-foreground': item.medicine_id === m.id}"
                                                >
                                                    <div class="flex flex-col gap-0.5">
                                                        <span class="font-medium text-foreground text-xs">{{ m.nama_obat }}</span>
                                                        <span v-if="m.latest_harga_beli" class="text-[10px] text-muted-foreground font-mono">
                                                            Beli Lalu: Rp {{ Number(m.latest_harga_beli).toLocaleString('id-ID') }}
                                                        </span>
                                                    </div>
                                                    <span class="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded font-mono border border-border/60">
                                                        {{ m.satuan_stok }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input v-else v-model="item.new_medicine_name" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Masukkan nama obat baru..." required />
                                    <p v-if="form.errors[`items.${index}.medicine_id`]" class="mt-1 text-xs text-destructive">
                                        {{ form.errors[`items.${index}.medicine_id`] }}
                                    </p>
                                    <p v-if="form.errors[`items.${index}.new_medicine_name`]" class="mt-1 text-xs text-destructive">
                                        {{ form.errors[`items.${index}.new_medicine_name`] }}
                                    </p>
                                </div>
                                
                                <!-- QTY (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Qty <span class="text-destructive">*</span></label>
                                    <input :id="'input-qty-' + index" v-model="item.qty_masuk" @keydown.enter.prevent="focusNextField('input-batch-' + index)" type="number" min="1" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                    <p v-if="form.errors[`items.${index}.qty_masuk`]" class="mt-1 text-xs text-destructive">
                                        {{ form.errors[`items.${index}.qty_masuk`] }}
                                    </p>
                                </div>

                                <!-- HARGA BELI (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Harga Beli <span class="text-destructive">*</span></label>
                                    <input :id="'input-price-' + index" v-model="item.harga_beli_satuan" type="number" step="0.01" min="0" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                    <p v-if="form.errors[`items.${index}.harga_beli_satuan`]" class="mt-1 text-xs text-destructive">
                                        {{ form.errors[`items.${index}.harga_beli_satuan`] }}
                                    </p>
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
                                    <input :id="'input-batch-' + index" v-model="item.batch_no" @keydown.enter.prevent="focusNextField('input-exp-' + index)" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>
                                <div class="col-span-6 md:col-span-3">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Exp Date <span class="text-destructive">*</span></label>
                                    <input :id="'input-exp-' + index" v-model="item.expired_date" @keydown.enter.prevent="handleExpDateEnter(index)" type="date" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
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

    <!-- Modal Salin Faktur Lampau / Rutin -->
    <Teleport to="body">
    <div v-if="showPastInvoicesModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-border flex items-center justify-between shrink-0 bg-muted/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">Salin Faktur Lampau / Rutin</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Pilih faktur sebelumnya untuk memuat otomatis daftar obat, satuan, dan riwayat harga beli.
                        </p>
                    </div>
                </div>
                <button type="button" @click="showPastInvoicesModal = false" class="p-1.5 text-muted-foreground hover:text-foreground rounded-md hover:bg-muted transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Toolbar Filter & Search -->
            <div class="p-3.5 sm:p-4 border-b border-border bg-card/60 flex flex-col sm:flex-row gap-2.5 shrink-0">
                <div class="w-full sm:w-1/3">
                    <select v-model="pastInvoiceSupplierFilter" class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors">
                        <option value="">Semua Supplier</option>
                        <option v-for="s in props.suppliers" :key="s.id" :value="s.id">{{ s.nama_pbf }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-2/3 relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-model="pastInvoiceSearch" 
                        @input="handlePastInvoiceSearchInput" 
                        type="text" 
                        placeholder="Cari nomor faktur atau nama obat..." 
                        class="w-full h-9 pl-9 pr-8 bg-background border border-input rounded-md text-xs font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors" 
                    />
                    <button 
                        v-if="pastInvoiceSearch" 
                        type="button" 
                        @click="pastInvoiceSearch = ''" 
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <!-- List Body -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                <div v-if="isLoadingPastInvoices" class="py-12 text-center text-muted-foreground text-xs flex flex-col items-center justify-center gap-2">
                    <svg class="animate-spin w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat arsip faktur...</span>
                </div>

                <div v-else-if="filteredPastInvoices.length === 0" class="py-12 text-center text-muted-foreground text-xs space-y-1">
                    <p class="font-medium text-foreground">Tidak ada riwayat faktur yang ditemukan.</p>
                    <p>Coba gunakan kata kunci pencarian lain atau pilih supplier yang berbeda.</p>
                </div>

                <div 
                    v-else 
                    v-for="inv in filteredPastInvoices" 
                    :key="inv.faktur_no" 
                    class="p-3.5 bg-background hover:bg-muted/30 border border-border rounded-lg transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
                >
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-sm text-foreground font-mono">{{ inv.faktur_no }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded bg-muted text-muted-foreground font-medium">
                                {{ inv.supplier_name }}
                            </span>
                            <span v-if="inv.tanggal_faktur" class="text-[11px] text-muted-foreground">
                                {{ inv.tanggal_faktur }}
                            </span>
                            <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-semibold border border-emerald-500/20">
                                {{ inv.items_count }} Item Obat
                            </span>
                        </div>
                        <!-- Preview Obat-obat dalam faktur -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span 
                                v-for="(it, idx) in inv.items.slice(0, 4)" 
                                :key="idx" 
                                class="text-[11px] bg-muted/60 text-foreground px-2 py-0.5 rounded border border-border/60"
                            >
                                {{ it.medicine_name }}
                            </span>
                            <span v-if="inv.items.length > 4" class="text-[10px] text-muted-foreground font-medium">
                                +{{ inv.items.length - 4 }} lainnya
                            </span>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        @click="applyPastInvoice(inv)" 
                        class="h-8 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-md transition-all shrink-0 flex items-center justify-center gap-1.5 shadow-2xs"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                        <span>Salin Faktur Ini</span>
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 sm:px-5 border-t border-border bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-2 shrink-0">
                <span class="text-[11px] text-muted-foreground text-center sm:text-left">
                    * Nomor Batch & Expired Date dikosongkan untuk diisi data batch fisik yang baru datang.
                </span>
                <button 
                    type="button" 
                    @click="showPastInvoicesModal = false" 
                    class="h-8 px-4 rounded-md text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
    </Teleport>
</div>
</template>
