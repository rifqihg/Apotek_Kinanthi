<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/Components/ui/toast/use-toast';
import KfaSearchModal from '@/Components/KfaSearchModal.vue';
import PastInvoicesModal from './PastInvoicesModal.vue';
import { midUnitCategories, unitCategories, singleUnitCategories, tertiaryUnits } from './unitCategories';
import { 
    calculateItemSubtotal, 
    calculateFormSubtotal, 
    calculateFormGrandTotal, 
    getPrimaryUnit, 
    getItemThresholdPreview, 
    normalizeItemUnits 
} from './useStockCalculations';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    medicines: {
        type: Array,
        default: () => [],
    },
    defaultPpn: {
        type: [Number, String],
        default: 11,
    },
    pastInvoices: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);
const { toast } = useToast();

const showKfaModal = ref(false);
const showPastInvoicesModal = ref(false);
const activeDropdown = ref(null);

const allMedicines = ref(props.medicines || []);
watch(() => props.medicines, (val) => {
    if (val && val.length) {
        allMedicines.value = val;
    }
});

const getEmptyItem = () => ({
    medicine_id: '',
    search_query: '',
    filter_text: '',
    barcode: '',
    kfa_code: '',
    nie: '',
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
    _status: 'ready',
    _source: null,
    _requires_unit_confirmation: false,
    _scanned_code: '',
});

const form = useForm({
    faktur_no: '',
    supplier_id: '',
    tanggal_faktur: new Date().toISOString().substr(0, 10),
    foto_faktur: null,
    items: [getEmptyItem()],
});

const ppnLocker = computed(() => Number(props.defaultPpn || 11));
const formSubtotal = computed(() => calculateFormSubtotal(form.items));
const formGrandTotal = computed(() => calculateFormGrandTotal(form.items, ppnLocker.value));

const close = () => {
    emit('update:modelValue', false);
};

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.items = [getEmptyItem()];
        form.clearErrors();
        if (!allMedicines.value.length) {
            fetchMedicines('');
        }
    }
});

const toggleDropdown = (name, index) => {
    const key = name + '_' + index;
    activeDropdown.value = activeDropdown.value === key ? null : key;
};

const handleClickOutside = () => {
    activeDropdown.value = null;
};

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
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    } else {
        form.items[0] = getEmptyItem();
    }
};

const clearItemBarcode = (item) => {
    item.barcode = '';
    item._scanned_code = '';
    item._source = null;
    toast({
        title: 'Ikatan Barcode Dihapus',
        description: 'Barcode pada baris ini telah dibersihkan.',
    });
};

const fetchMedicines = async (query = '') => {
    try {
        const response = await axios.get(route('control.admin.incoming-stocks.search-medicines'), { params: { query } });
        if (response.data?.medicines) {
            allMedicines.value = response.data.medicines;
        }
    } catch (e) {
        console.error('Gagal memperbarui katalog obat:', e);
    }
};

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
        focusNextField(`input-batch-${index + 1}`);
    }
};

const selectMedicine = (index, m) => {
    const item = form.items[index];
    item.medicine_id = m.id;
    item.search_query = m.nama_obat;
    item.filter_text = '';
    item.new_medicine_kategori = m.kategori || '';
    activeDropdown.value = null;

    if (m.latest_harga_beli !== undefined && m.latest_harga_beli > 0) {
        item.harga_beli_satuan = m.latest_harga_beli;
    }
    if (m.latest_diskon !== undefined) {
        item.diskon_persen = m.latest_diskon;
    }

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

    setTimeout(() => {
        const qtyEl = document.getElementById(`input-qty-${index}`);
        if (qtyEl) {
            qtyEl.focus();
            if (qtyEl.select) qtyEl.select();
        }
    }, 50);
};

const handleKfaSelected = async (d) => {
    let targetIndex = form.items.findIndex(it => 
        !it.medicine_id && 
        !it.search_query?.trim() && 
        !it.new_medicine_name?.trim() && 
        !it.is_new_medicine
    );

    if (targetIndex === -1) {
        form.items.push(getEmptyItem());
        targetIndex = form.items.length - 1;
    }

    const targetItem = form.items[targetIndex];

    if (d.medicine_id) {
        targetItem.medicine_id = d.medicine_id;
        targetItem.search_query = d.nama_obat;
        targetItem.filter_text = '';
        targetItem.is_new_medicine = false;
        targetItem.barcode = d.barcode || targetItem.barcode || '';
        targetItem.kfa_code = d.kfa_code || '';
        targetItem.nie = d.nie || '';
        targetItem.new_medicine_kategori = d.kategori || targetItem.new_medicine_kategori || '';
        targetItem.qty_masuk = 1;

        targetItem.satuan_input = d.satuan_input || 'Box';
        targetItem.mid_unit = d.mid_unit || null;
        targetItem.new_medicine_satuan_kecil = d.satuan_stok || 'Tablet';
        targetItem.mid_to_big_ratio = d.mid_to_big_ratio || 1;
        targetItem.small_to_mid_ratio = d.small_to_mid_ratio || 1;
        targetItem.unit_conversion_factor = d.unit_conversion_factor || 1;
        targetItem.small_unit_skipped = Boolean(d.small_unit_skipped);
        targetItem.no_multi_unit = Boolean(d.no_multi_unit);
        targetItem.harga_beli_satuan = d.harga_beli_satuan || 0;
        targetItem.diskon_persen = d.diskon_persen || 0;
        targetItem.min_stock_threshold = d.min_stock_threshold || 10;
        targetItem._source = 'local';
        targetItem._status = 'ready';
        targetItem._just_scanned = true;
        setTimeout(() => { targetItem._just_scanned = false; }, 1500);

        toast({
            title: 'Obat Terdaftar Ditemukan',
            description: `${d.nama_obat} berhasil dimuat dari katalog lokal ke Baris #${targetIndex + 1}.`,
        });
    } else {
        targetItem.medicine_id = null;
        targetItem.is_new_medicine = true;
        targetItem.new_medicine_name = d.nama_obat;
        targetItem.search_query = d.nama_obat;
        targetItem.filter_text = '';
        targetItem.barcode = d.barcode || targetItem.barcode || '';
        targetItem.kfa_code = d.kfa_code || '';
        targetItem.nie = d.nie || '';
        targetItem.new_medicine_kategori = d.kategori || '';
        targetItem.qty_masuk = 1;

        targetItem.satuan_input = d.satuan_input || 'Box';
        targetItem.mid_unit = d.mid_unit || null;
        targetItem.new_medicine_satuan_kecil = d.satuan_stok || 'Tablet';
        targetItem.mid_to_big_ratio = d.mid_to_big_ratio || 1;
        targetItem.small_to_mid_ratio = d.small_to_mid_ratio || 1;
        targetItem.unit_conversion_factor = d.unit_conversion_factor || 1;
        targetItem.small_unit_skipped = Boolean(d.small_unit_skipped);
        targetItem.no_multi_unit = Boolean(d.no_multi_unit);
        targetItem.harga_beli_satuan = 0;
        targetItem.diskon_persen = 0;
        targetItem.min_stock_threshold = 10;
        targetItem._requires_unit_confirmation = Boolean(d.requires_unit_confirmation);
        targetItem._source = 'kfa';
        targetItem._status = 'ready';
        targetItem._just_scanned = true;
        setTimeout(() => { targetItem._just_scanned = false; }, 1500);

        toast({
            title: 'Data KFA SATUSEHAT Terhubung',
            description: `${d.nama_obat} (NIE: ${d.nie || '-'}) berhasil disuntikkan ke Baris #${targetIndex + 1}.`,
        });
    }

    await nextTick();
    const rowEl = document.getElementById(`item-row-${targetIndex}`);
    if (rowEl) rowEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    const qtyEl = document.getElementById(`input-qty-${targetIndex}`);
    if (qtyEl) {
        qtyEl.focus();
        if (qtyEl.select) qtyEl.select();
    }
};

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
            new_medicine_kategori: it.kategori || '',
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

const handleNoMultiUnitChange = (item) => {
    if (item.no_multi_unit) {
        item.small_unit_skipped = false;
    }
};

const handleGlobalKeydown = (e) => {
    if (e.key === 'F2' && props.modelValue) {
        e.preventDefault();
        showKfaModal.value = true;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('keydown', handleGlobalKeydown);
});

function submit() {
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
        normalizeItemUnits(item);
    });

    form.post(route('control.admin.incoming-stocks.store'), {
        forceFormData: true,
        preserveScroll: true,
        only: ['stocks', 'flash'],
        onSuccess: () => {
            close();
            form.reset();
            form.items = [getEmptyItem()];
            form.clearErrors();
            // Refresh katalog obat di background agar data terbaru tersedia untuk input berikutnya
            fetchMedicines('');
        },
        onError: (errors) => {
            console.error('Terjadi kesalahan validasi faktur masuk:', errors);
        },
    });
}
</script>

<template>
<Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
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
                <button @click="close" class="w-8 h-8 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="flex-1 overflow-y-auto bg-card custom-scrollbar p-6 space-y-5 relative">
                <!-- Alert Validasi Global jika ada error -->
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
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-muted text-foreground flex items-center justify-center text-xs font-bold">2</span>
                            Daftar Obat (Items) <span class="text-destructive">*</span>
                        </h4>
                        <div class="flex items-center gap-2">
                            <!-- Tombol Tarik Data KFA BPOM (F2) -->
                            <button 
                                type="button" 
                                @click="showKfaModal = true" 
                                class="text-xs font-semibold border border-emerald-600/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 px-3 py-1.5 rounded-md transition-all uppercase tracking-wider flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Tarik Data Obat Resmi KFA SATUSEHAT via NIE BPOM / Kode KFA (Shortcut: F2)"
                            >
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <span>Tarik KFA BPOM</span>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-600 text-white font-bold leading-tight">F2</span>
                            </button>

                            <button type="button" @click="showPastInvoicesModal = true" class="text-xs font-semibold border border-input bg-background hover:bg-accent text-foreground px-3 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 shadow-2xs cursor-pointer">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                <span>Salin Faktur Lampau</span>
                            </button>
                            <button type="button" @click="addItem" class="text-xs font-medium bg-muted hover:bg-muted/80 text-foreground px-3 py-1.5 rounded-md transition-colors uppercase tracking-wider flex items-center gap-1.5 border border-border cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Tambah Baris Obat
                            </button>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div 
                            v-for="(item, index) in form.items" 
                            :key="index" 
                            :id="'item-row-' + index"
                            class="p-4 bg-background border border-border rounded-lg relative space-y-3.5 transition-all duration-300" 
                            :class="{
                                'ring-2 ring-emerald-500/80 bg-emerald-500/5': item._just_scanned
                            }"
                        >
                            <!-- Hapus / Reset Baris -->
                            <button 
                                type="button" 
                                @click="removeItem(index)" 
                                class="absolute top-4 right-4 w-7 h-7 rounded-md bg-destructive/10 text-destructive flex items-center justify-center hover:bg-destructive/20 transition-colors cursor-pointer" 
                                :title="form.items.length > 1 ? 'Hapus Baris Ini' : 'Reset / Bersihkan Baris'"
                            >
                                <svg v-if="form.items.length > 1" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>

                            <!-- Header Baris: Checkbox Obat Baru & Badges KFA -->
                            <div class="flex items-center justify-between pr-10 flex-wrap gap-2">
                                <label class="flex items-center gap-2 text-xs font-semibold text-foreground cursor-pointer uppercase tracking-wider">
                                    <input type="checkbox" v-model="item.is_new_medicine" class="rounded border-input text-primary focus:ring-ring w-4 h-4">
                                    Input Obat Baru (Belum ada di katalog)
                                </label>

                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <!-- Badge Barcode Terikat -->
                                    <span v-if="item.barcode" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono bg-muted text-foreground border border-border" :title="'Barcode terikat: ' + item.barcode">
                                        <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                        </svg>
                                        <span>{{ item.barcode }}</span>
                                        <button type="button" @click.stop="clearItemBarcode(item)" class="text-muted-foreground hover:text-destructive font-bold text-xs ml-0.5 cursor-pointer leading-none" title="Lepas ikatan barcode ini">×</button>
                                    </span>

                                    <!-- Badge KFA Kemenkes -->
                                    <span v-if="item._source === 'kfa'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30" :title="'Kode KFA: ' + (item.kfa_code || '-') + ' | NIE: ' + (item.nie || '-')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        KFA Kemenkes
                                        <span v-if="item.kfa_code" class="font-mono text-[10px] opacity-80">({{ item.kfa_code }})</span>
                                    </span>

                                    <!-- Badge Katalog Lokal -->
                                    <span v-else-if="item._source === 'local'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        Katalog Lokal
                                    </span>

                                    <!-- Badge Peringatan Verifikasi Rasio Kemasan -->
                                    <span v-if="item._requires_unit_confirmation" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 animate-pulse" title="Kemasan KFA perlu dikonfirmasi rasio isinya">
                                        <svg class="w-3 h-3 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        Verifikasi Rasio Kemasan
                                    </span>
                                </div>
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
                                                    class="p-0.5 text-muted-foreground hover:text-foreground rounded cursor-pointer"
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
                                                        <span v-if="m.latest_harga_beli" class="text-[10px] text-muted-foreground font-sans font-medium">
                                                            Beli Lalu: Rp {{ Number(m.latest_harga_beli).toLocaleString('id-ID') }}
                                                        </span>
                                                    </div>
                                                    <span class="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded font-sans font-medium border border-border/60">
                                                        {{ m.satuan_stok }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input 
                                        :id="'input-new-name-' + index"
                                        v-else 
                                        v-model="item.new_medicine_name" 
                                        @keydown.enter.prevent="focusNextField('input-qty-' + index)"
                                        type="text" 
                                        class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" 
                                        placeholder="Masukkan nama obat baru..." 
                                        required 
                                    />
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

                            <!-- BATCH, EXP DATE, & KATEGORI -->
                            <div class="grid grid-cols-12 gap-3">
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Batch No <span class="text-destructive">*</span></label>
                                    <input :id="'input-batch-' + index" v-model="item.batch_no" @keydown.enter.prevent="focusNextField('input-exp-' + index)" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Exp Date <span class="text-destructive">*</span></label>
                                    <input :id="'input-exp-' + index" v-model="item.expired_date" @keydown.enter.prevent="focusNextField('input-kategori-' + index)" type="date" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                </div>
                                <div class="col-span-12 sm:col-span-4">
                                    <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Kategori Obat <span class="text-[10px] font-normal text-muted-foreground lowercase">(opsional)</span></label>
                                    <input :id="'input-kategori-' + index" v-model="item.new_medicine_kategori" @keydown.enter.prevent="handleExpDateEnter(index)" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 py-1 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: Bebas, Terbatas, Keras..." />
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
                                                        v-for="u in tertiaryUnits" 
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
                    <button type="button" @click="close" class="h-9 px-4 rounded-md text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-9 px-5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-semibold transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm cursor-pointer">
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
<PastInvoicesModal 
    v-model="showPastInvoicesModal" 
    :suppliers="suppliers" 
    :initial-supplier-id="form.supplier_id" 
    :initial-invoices="pastInvoices"
    @select-invoice="applyPastInvoice"
/>

<!-- Modal Tarik Data KFA SATUSEHAT Kemenkes (Shortcut: F2) -->
<KfaSearchModal 
    v-model="showKfaModal" 
    @select="handleKfaSelected" 
/>
</template>
