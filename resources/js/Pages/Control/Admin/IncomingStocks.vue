<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({ stocks: Object, suppliers: Array, medicines: Array, settings: Object, filters: Object });
const showModal = ref(false);
const activeTab = ref('all');
const page = usePage();
const searchInvoiceQuery = ref(props.filters?.search || '');

const handleSearch = () => {
    router.get(
        route('control.admin.incoming-stocks'),
        { search: searchInvoiceQuery.value },
        { preserveState: true, preserveScroll: true }
    );
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
    let rawStocks = props.stocks?.data || [];
    
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
    small_unit_skipped: false,
    unit_conversion_factor: 1,
    satuan_input: 'Box',
    mid_unit: 'Strip',
    mid_to_big_ratio: 1,
    small_to_mid_ratio: 1,
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
    form.items[index].medicine_id = m.id;
    form.items[index].search_query = m.nama_obat;
    form.items[index].active_dropdown = false;
    form.items[index].unit_conversion_factor = m.rasio_konversi || 1;
    form.items[index].new_medicine_satuan_kecil = m.satuan_stok || 'Unit';
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

const getSmallestUnitLabel = (item) => {
    if (item.is_new_medicine) return item.new_medicine_satuan_kecil || 'Unit';
    if (item.medicine_id) {
        const m = props.medicines?.find(x => x.id === item.medicine_id);
        return m ? m.satuan_stok : 'Unit';
    }
    return 'Unit';
};

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
        if (item.small_unit_skipped) {
            item.small_to_mid_ratio = item.unit_conversion_factor;
            item.mid_unit = null; // Clear mid unit so backend knows it's a 2-level hierarchy
            item.mid_to_big_ratio = 1;
        } else {
            item.unit_conversion_factor = item.mid_to_big_ratio * item.small_to_mid_ratio;
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
<ControlLayout>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
        <div>
            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Faktur Masuk</h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Sistem Pencatatan Pembelian Obat (Multi-Item)</p>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <button @click="showModal=true; form.reset(); form.items=[getEmptyItem()]; form.clearErrors()" class="whitespace-nowrap inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[42px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all w-full sm:w-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Input Faktur Baru
            </button>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="mt-6 bg-white p-4 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex items-center gap-3 w-full">
            <div class="flex-1 relative group">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input 
                    type="text" 
                    v-model="searchInvoiceQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Cari No Faktur atau Nama Supplier..." 
                    class="w-full h-[48px] pl-11 pr-4 bg-slate-50 border-[#E8EEF6] focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200/50 rounded-[16px] text-sm font-medium transition-all outline-none shadow-[0_8px_24px_rgba(15,23,42,0.04)]"
                />
            </div>
            <button 
                @click="handleSearch"
                class="h-[48px] px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-[16px] transition-colors flex items-center justify-center shadow-[0_8px_24px_rgba(37,99,235,0.2)]"
            >
                Cari
            </button>
        </div>
    </div>

    <!-- Accordion List -->
    <div class="mt-6 space-y-4">
        <div v-if="groupedInvoices.length === 0" class="text-center py-12 bg-white rounded-[24px] border border-dashed border-slate-300">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
            <p class="text-slate-500 font-medium">Belum ada faktur yang tercatat.</p>
        </div>

        <div v-for="group in groupedInvoices" :key="group.faktur_no" class="bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden transition-all duration-300">
            <!-- Accordion Header -->
            <button @click="toggleAccordion(group.faktur_no)" class="w-full flex flex-col md:flex-row md:items-center justify-between p-5 md:px-6 hover:bg-slate-50 transition-colors gap-4 text-left">
                <div class="flex items-center gap-4 md:gap-6">
                    <div class="w-12 h-12 rounded-[16px] bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-slate-900 font-black tracking-tight text-lg">{{ group.faktur_no }}</span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-md text-[10px] font-bold uppercase">{{ group.items.length }} Item</span>
                        </div>
                        <div class="text-sm font-semibold text-slate-500">{{ group.supplier?.nama_pbf }} &bull; {{ new Date(group.tanggal_faktur).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between md:justify-end gap-6 md:w-auto w-full border-t md:border-t-0 pt-4 md:pt-0 border-slate-100">
                    <div class="text-left md:text-right">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Tagihan (Inc PPN {{ group.ppn_persen }}%)</p>
                        <p class="text-emerald-600 font-black text-xl">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 transition-transform duration-300" :class="{'rotate-180': openAccordions[group.faktur_no]}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>
            </button>

            <!-- Accordion Body (Table) -->
            <div v-show="openAccordions[group.faktur_no]" class="border-t border-[#E8EEF6] bg-slate-50/50">
                <div class="w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100/50 border-b border-slate-200">
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Nama Barang</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Batch</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Exp Date</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Jumlah Barang (Qty)</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Harga Beli</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Disc (%)</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Jumlah Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in group.items" :key="item.id" class="hover:bg-white transition-colors">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-bold text-slate-900">{{ item.medicine?.nama_obat }}</p>
                                    <p class="text-[11px] text-slate-500">{{ item.satuan_input }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-slate-600">{{ item.batch_no }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-slate-600">
                                    {{ new Date(item.expired_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) }}
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-slate-900">{{ item.qty_masuk }}</td>
                                <td class="px-6 py-4 text-right font-mono font-semibold text-slate-600">Rp {{ Number(item.harga_beli_satuan).toLocaleString('id-ID') }}</td>
                                <td class="px-6 py-4 text-center font-bold text-rose-500">{{ Number(item.diskon_persen) }}%</td>
                                <td class="px-6 py-4 text-right font-mono font-black text-emerald-600">Rp {{ Number(item.jumlah_harga).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-white border-t border-slate-200">
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Sub Nota</td>
                                <td class="px-6 py-3 text-right font-mono font-bold text-slate-900">Rp {{ group.subtotal.toLocaleString('id-ID') }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">PPN ({{ group.ppn_persen }}%)</td>
                                <td class="px-6 py-3 text-right font-mono font-bold text-rose-500">+ Rp {{ (group.subtotal * (group.ppn_persen/100)).toLocaleString('id-ID') }}</td>
                            </tr>
                            <tr class="bg-slate-50">
                                <td colspan="6" class="px-6 py-4 text-right text-sm font-black text-slate-900 uppercase">Total</td>
                                <td class="px-6 py-4 text-right font-mono font-black text-emerald-600 text-lg">Rp {{ invoiceGrandTotal(group).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Faktur Baru -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-[32px] shadow-2xl w-full max-w-3xl max-h-full flex flex-col overflow-hidden animate-in zoom-in-95 duration-200 border border-slate-100" @click.stop>
            <!-- Modal Header -->
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-[16px] flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900 tracking-tight">Input Faktur Pembelian</h3>
                        <p class="text-sm font-semibold text-slate-500 mt-0.5">Tambahkan satu atau banyak obat dalam satu faktur.</p>
                    </div>
                </div>
                <button @click="showModal=false" class="w-10 h-10 rounded-[14px] bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-500 flex items-center justify-center transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="flex-1 overflow-y-auto bg-slate-50 custom-scrollbar p-6 space-y-6">
                <!-- Bagian 1: Data Faktur -->
                <div class="bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-sm">
                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">1</span>
                        Informasi Faktur Utama
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-2">No. Faktur <span class="text-red-500">*</span></label>
                            <input v-model="form.faktur_no" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-[16px] px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-mono font-bold" required />
                            <p v-if="form.errors.faktur_no" class="mt-1 text-xs text-red-500">{{ form.errors.faktur_no }}</p>
                        </div>
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-2">Supplier <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <div 
                                    @click.stop="toggleDropdown('supplier', 0)"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 transition-all cursor-pointer flex justify-between items-center"
                                    :class="{'border-blue-500 ring-2 ring-blue-500/20': activeDropdown === 'supplier_0'}"
                                >
                                    <span class="truncate" :class="{'text-slate-400': !form.supplier_id}">
                                        {{ suppliers?.find(s => s.id === form.supplier_id)?.nama_pbf || 'Pilih Supplier...' }}
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'supplier_0'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                
                                <div v-if="activeDropdown === 'supplier_0'" class="absolute z-30 w-full mt-2 bg-white border border-slate-200 rounded-[16px] shadow-xl overflow-hidden animate-in fade-in">
                                    <div class="max-h-56 overflow-y-auto custom-scrollbar p-2 grid grid-cols-1 gap-1">
                                        <div 
                                            v-for="sup in suppliers" 
                                            :key="sup.id" 
                                            @click.stop="form.supplier_id = sup.id; activeDropdown = null"
                                            class="px-4 py-2.5 text-sm hover:bg-blue-50 rounded-[12px] cursor-pointer font-bold transition-colors"
                                            :class="{'bg-blue-50 text-blue-700': form.supplier_id === sup.id, 'text-slate-700': form.supplier_id !== sup.id}"
                                        >
                                            {{ sup.nama_pbf }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="form.errors.supplier_id" class="mt-1 text-xs text-red-500">{{ form.errors.supplier_id }}</p>
                        </div>
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-2">Tanggal Faktur <span class="text-red-500">*</span></label>
                            <input v-model="form.tanggal_faktur" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-[16px] px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all" required />
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-slate-700 text-sm font-semibold mb-2">Upload Foto Faktur (Opsional)</label>
                            <input type="file" @change="e => form.foto_faktur = e.target.files[0]" class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-[12px] file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer bg-slate-50 border border-slate-200 rounded-[16px] p-1.5 transition-all" accept="image/*,.pdf" />
                            <p v-if="form.errors.foto_faktur" class="mt-1 text-xs text-red-500">{{ form.errors.foto_faktur }}</p>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Daftar Obat (Multi-Item) -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">2</span>
                            Daftar Obat (Items) <span class="text-red-500">*</span>
                        </h4>
                        <button type="button" @click="addItem" class="text-xs font-bold bg-emerald-50 text-emerald-600 px-4 py-2 rounded-[12px] hover:bg-emerald-100 transition-colors uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Tambah Baris Obat
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in form.items" :key="index" class="p-6 bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_4px_12px_rgba(15,23,42,0.03)] relative group transition-all duration-300">
                            <!-- Hapus Baris -->
                            <button v-if="form.items.length > 1" type="button" @click="removeItem(index)" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-colors" title="Hapus Baris">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>

                            <!-- Checkbox Obat Baru -->
                            <div class="mb-4 flex items-center justify-between pr-10">
                                <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer uppercase tracking-wide">
                                    <input type="checkbox" v-model="item.is_new_medicine" class="rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4">
                                    Input Obat Baru (Belum ada di katalog)
                                </label>
                            </div>

                            <div class="grid grid-cols-12 gap-5">
                                <!-- PILIH OBAT (col-span-12 or 4) -->
                                <div class="col-span-12 md:col-span-4 relative">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">{{ item.is_new_medicine ? 'Nama Obat Baru' : 'Pilih Obat' }} <span class="text-rose-500">*</span></label>
                                    
                                    <div v-if="!item.is_new_medicine" class="relative">
                                        <input type="text" v-model="item.search_query" @focus="item.active_dropdown = true" @blur="hideDropdown(item)" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-semibold focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" placeholder="Ketik nama obat..." required />
                                        <div v-if="item.active_dropdown" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-[12px] shadow-xl max-h-48 overflow-y-auto">
                                            <div v-for="m in filteredMedicines(item.search_query)" :key="m.id" @click.prevent="selectMedicine(index, m)" class="px-4 py-2 hover:bg-blue-50 cursor-pointer text-sm flex justify-between items-center group">
                                                <span class="font-medium text-slate-700 group-hover:text-blue-700">{{ m.nama_obat }}</span>
                                                <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ m.satuan_stok }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input v-else v-model="item.new_medicine_name" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-semibold focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" placeholder="Masukkan nama obat baru..." required />
                                </div>
                                
                                <!-- QTY (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Qty <span class="text-rose-500">*</span></label>
                                    <input v-model="item.qty_masuk" type="number" min="1" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" required />
                                </div>

                                <!-- HARGA BELI (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Harga Beli <span class="text-rose-500">*</span></label>
                                    <input v-model="item.harga_beli_satuan" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-mono font-semibold focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" required />
                                </div>

                                <!-- DISC (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Disc (%) <span class="text-rose-500">*</span></label>
                                    <input v-model="item.diskon_persen" type="number" step="0.01" min="0" max="100" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-bold text-rose-500 focus:bg-white focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 outline-none transition-all" required />
                                </div>

                                <!-- JUMLAH NETTO (col-span-2) -->
                                <div class="col-span-6 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Total</label>
                                    <input :value="calculateItemSubtotal(item).toLocaleString('id-ID')" type="text" class="w-full bg-slate-100/50 border-transparent rounded-[12px] px-4 py-2.5 text-sm text-right font-mono font-bold text-emerald-600" disabled />
                                </div>
                            </div>

                            <!-- BATCH & EXP DATE & KATEGORI (for new med) -->
                            <div class="grid grid-cols-12 gap-5 mt-5">
                                <div class="col-span-6 md:col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Batch No <span class="text-rose-500">*</span></label>
                                    <input v-model="item.batch_no" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm font-mono focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all" required />
                                </div>
                                <div class="col-span-6 md:col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Exp Date <span class="text-rose-500">*</span></label>
                                    <input v-model="item.expired_date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all" required />
                                </div>
                                <div v-if="item.is_new_medicine" class="col-span-12 md:col-span-6">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Kategori Obat</label>
                                    <input v-model="item.new_medicine_kategori" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-4 py-2.5 text-sm focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all" placeholder="Contoh: Analgesik, Antibiotik..."/>
                                </div>
                            </div>

                            <!-- UNIT CONVERSION SECTION -->
                            <div class="mt-6 p-3 md:p-4 bg-slate-50 border border-slate-100 rounded-[20px]">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="text-[11px] font-black text-slate-700 uppercase tracking-widest">Konversi Multi-Satuan (Opsional)</h5>
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer bg-white px-3 py-1.5 rounded-lg shadow-sm border border-slate-200 hover:border-blue-300 transition-colors">
                                        <input type="checkbox" v-model="item.small_unit_skipped" class="rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-3.5 h-3.5">
                                        Abaikan Satuan Menengah
                                    </label>
                                </div>
                                
                                <div class="flex flex-col md:flex-row gap-3">
                                    <!-- LEVEL 1 -->
                                    <div class="p-3 bg-white border border-slate-200 rounded-[16px] space-y-2 flex-none w-full transition-all" 
                                         :class="item.small_unit_skipped ? 'md:flex-1' : 'md:w-[140px]'">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-wider">Tersier (Terbesar)</label>
                                        </div>
                                        <div class="relative">
                                            <div 
                                                @click.stop="toggleDropdown('unit1', index)"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-[12px] px-3 py-2 text-sm font-bold flex justify-between items-center cursor-pointer transition-all hover:bg-white"
                                                :class="{'border-blue-500 ring-2 ring-blue-500/20': activeDropdown === 'unit1_' + index}"
                                            >
                                                <span class="truncate text-slate-700">{{ item.satuan_input || 'Pilih Satuan' }}</span>
                                                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{'rotate-180': activeDropdown === 'unit1_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </div>
                                            <div v-if="activeDropdown === 'unit1_' + index" class="absolute z-30 w-full mt-1 bg-white border border-slate-200 rounded-[12px] shadow-xl overflow-hidden animate-in fade-in">
                                                <div class="max-h-32 overflow-y-auto custom-scrollbar p-1 grid grid-cols-1 gap-0.5">
                                                    <div 
                                                        v-for="u in ['Box', 'Karton', 'Dus', 'Kaleng', 'Botol', 'Tube', 'Pcs']" 
                                                        :key="u" @click.stop="item.satuan_input = u; activeDropdown = null"
                                                        class="px-3 py-1.5 text-xs hover:bg-blue-50 rounded-[8px] cursor-pointer font-bold text-slate-600"
                                                    >{{ u }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 2 (Menengah) -->
                                    <div v-if="!item.small_unit_skipped" class="p-3 bg-white border border-slate-200 rounded-[16px] space-y-2 flex-none w-full md:w-auto">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-wider">Sekunder (Menengah)</label>
                                        </div>
                                        <div class="flex gap-1.5 relative">
                                            <div class="flex items-center gap-1 shrink-0 bg-slate-100 border border-slate-200 rounded-[10px] px-1.5 py-1">
                                                <span class="text-[10px] font-bold text-slate-500 truncate max-w-[60px]">1 {{ item.satuan_input || 'Box' }} =</span>
                                                <input v-model="item.mid_to_big_ratio" type="number" min="1" class="w-14 bg-white border border-slate-300 rounded-[8px] px-1.5 py-1 text-sm font-black text-center text-blue-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all shadow-sm" required />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div 
                                                    @click.stop="toggleDropdown('unit2', index)"
                                                    class="w-full h-full bg-slate-50 border border-slate-200 rounded-[12px] px-2.5 py-2 text-sm font-bold flex justify-between items-center cursor-pointer transition-all hover:bg-white"
                                                    :class="{'border-blue-500 ring-2 ring-blue-500/20': activeDropdown === 'unit2_' + index}"
                                                >
                                                    <span class="truncate text-slate-700">{{ item.mid_unit || 'Pilih' }}</span>
                                                    <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit2_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <div v-if="activeDropdown === 'unit2_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-white border border-slate-200 rounded-[12px] shadow-xl overflow-hidden animate-in fade-in">
                                                <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in midUnitCategories" :key="category.name" class="border-b border-slate-100 last:border-0">
                                                        <div class="px-3 py-2 bg-slate-50 sticky top-0 z-10 text-[10px] font-black text-slate-500 uppercase tracking-wider">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.mid_unit = u; activeDropdown = null"
                                                                class="px-3 py-1.5 text-xs hover:bg-blue-50 rounded-[8px] cursor-pointer font-bold text-slate-600 pl-4"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- LEVEL 3 (Terkecil) -->
                                    <div class="p-3 bg-white border border-slate-200 rounded-[16px] space-y-2 flex-1 min-w-0">
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-wider">{{ item.small_unit_skipped ? 'Sekunder' : 'Primer' }} (Terkecil)</label>
                                        </div>
                                        <div class="flex gap-1.5 relative">
                                            <div class="flex items-center gap-1 shrink-0 bg-slate-100 border border-slate-200 rounded-[10px] px-1.5 py-1">
                                                <span class="text-[10px] font-bold text-slate-500 truncate max-w-[60px]">1 {{ !item.small_unit_skipped ? (item.mid_unit || 'Strip') : (item.satuan_input || 'Box') }} =</span>
                                                
                                                <input v-if="!item.small_unit_skipped" v-model="item.small_to_mid_ratio" type="number" min="1" class="w-14 bg-white border border-slate-300 rounded-[8px] px-1.5 py-1 text-sm font-black text-center text-blue-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all shadow-sm" required />
                                                <input v-else v-model="item.unit_conversion_factor" type="number" min="1" class="w-14 bg-white border border-slate-300 rounded-[8px] px-1.5 py-1 text-sm font-black text-center text-blue-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all shadow-sm" required />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div 
                                                    @click.stop="toggleDropdown('unit3', index)"
                                                    class="w-full h-full bg-slate-50 border border-slate-200 rounded-[12px] px-2.5 py-2 text-sm font-bold flex justify-between items-center cursor-pointer transition-all hover:bg-white"
                                                    :class="{'border-blue-500 ring-2 ring-blue-500/20': activeDropdown === 'unit3_' + index}"
                                                >
                                                    <span class="truncate text-slate-700">{{ item.new_medicine_satuan_kecil || 'Pilih' }}</span>
                                                    <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="{'rotate-180': activeDropdown === 'unit3_' + index}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </div>
                                            </div>
                                            
                                            <div v-if="activeDropdown === 'unit3_' + index" class="absolute z-30 w-full left-0 top-full mt-1 bg-white border border-slate-200 rounded-[12px] shadow-xl overflow-hidden animate-in fade-in">
                                                <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                                    <div v-for="category in unitCategories" :key="category.name" class="border-b border-slate-100 last:border-0">
                                                        <div class="px-3 py-2 bg-slate-50 sticky top-0 z-10 text-[10px] font-black text-slate-500 uppercase tracking-wider">{{ category.name }}</div>
                                                        <div class="p-1 grid grid-cols-1 gap-0.5">
                                                            <div 
                                                                v-for="u in category.units" 
                                                                :key="u" @click.stop="item.new_medicine_satuan_kecil = u; activeDropdown = null"
                                                                class="px-3 py-1.5 text-xs hover:bg-blue-50 rounded-[8px] cursor-pointer font-bold text-slate-600 pl-4"
                                                            >{{ u }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Bagian 3: Ringkasan Total -->
                <div class="bg-slate-900 rounded-[24px] p-6 shadow-xl flex flex-col md:flex-row justify-between items-center gap-6 mt-8">
                    <div class="text-slate-300 w-full md:w-auto">
                        <div class="flex justify-between md:justify-start md:gap-12 mb-2">
                            <span class="text-sm">Subtotal:</span>
                            <span class="font-mono font-bold text-white">Rp {{ formSubtotal.toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex justify-between md:justify-start md:gap-12">
                            <span class="text-sm flex items-center gap-2">PPN (Otomatis {{ ppnLocker }}% dari Sistem):</span>
                            <span class="font-mono font-bold text-rose-400">+ Rp {{ (formSubtotal * (ppnLocker/100)).toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                    <div class="text-right w-full md:w-auto border-t border-slate-700 pt-4 md:border-0 md:pt-0">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Tagihan (Grand Total)</p>
                        <p class="text-3xl font-black text-emerald-400 font-mono">Rp {{ formGrandTotal.toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showModal=false" class="px-6 py-3 rounded-[16px] text-sm font-bold text-slate-500 hover:bg-slate-200 transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-[16px] text-sm font-bold shadow-lg shadow-blue-600/30 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2">
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        Simpan & Aktifkan Semua Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</ControlLayout>
</template>
