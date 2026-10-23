<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/Components/ui/toast/use-toast';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    invoice: {
        type: Object,
        default: () => null,
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    medicines: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);
const { toast } = useToast();

const isSubmitting = ref(false);
const currentFotoPath = ref(null);
const previewItemId = ref(null);
const fileInput = ref(null);
const selectedFileName = ref('');
const showPhotoPreview = ref(false);

const previewPhotoUrl = computed(() => {
    if (form.foto_faktur) {
        return URL.createObjectURL(form.foto_faktur);
    }
    if (previewItemId.value) {
        return route('control.admin.incoming-stocks.faktur-file', previewItemId.value);
    }
    return null;
});

const isPdf = computed(() => {
    if (form.foto_faktur) {
        return form.foto_faktur.type === 'application/pdf';
    }
    if (currentFotoPath.value) {
        return currentFotoPath.value.toLowerCase().endsWith('.pdf');
    }
    return false;
});

const form = reactive({
    original_faktur_no: '',
    faktur_no: '',
    supplier_id: '',
    tanggal_faktur: '',
    ppn_persen: 11,
    foto_faktur: null,
    items: [],
});

const close = () => {
    showPhotoPreview.value = false;
    emit('update:modelValue', false);
};

const getFilteredMedicines = (item) => {
    const q = (item.medicine_search || '').toLowerCase().trim();
    if (!q) return (props.medicines || []).slice(0, 20);
    return (props.medicines || []).filter(m => 
        m.nama_obat && m.nama_obat.toLowerCase().includes(q)
    ).slice(0, 25);
};

const selectMedicineForItem = (item, med) => {
    item.is_new_medicine = false;
    item.new_medicine_name = '';
    item.medicine_id = med.id;
    item.medicine_name = med.nama_obat;
    item.satuan_terkecil = med.satuan_stok || item.satuan_terkecil;
    item.show_medicine_selector = false;
    item.medicine_search = '';
};

const createNewMedicineForItem = (item) => {
    const name = (item.medicine_search || '').trim();
    if (!name) return;
    item.is_new_medicine = true;
    item.new_medicine_name = name;
    item.medicine_name = name;
    item.medicine_id = null;
    item.show_medicine_selector = false;
    item.medicine_search = '';
};

const resetMedicineForItem = (item) => {
    item.is_new_medicine = false;
    item.new_medicine_name = '';
    item.medicine_id = item.original_medicine_id;
    item.medicine_name = item.original_medicine_name;
    item.show_medicine_selector = false;
    item.medicine_search = '';
};

const initForm = (inv) => {
    if (!inv) return;

    form.original_faktur_no = inv.faktur_no || '';
    form.faktur_no = inv.faktur_no || '';
    form.supplier_id = inv.supplier?.id || (inv.items?.[0]?.supplier_id || '');
    form.tanggal_faktur = inv.tanggal_faktur ? String(inv.tanggal_faktur).substring(0, 10) : '';
    form.ppn_persen = Number(inv.ppn_persen ?? 11);
    form.foto_faktur = null;
    selectedFileName.value = '';
    currentFotoPath.value = inv.foto_faktur_path || (inv.items?.[0]?.foto_faktur_path || null);
    previewItemId.value = inv.items?.[0]?.id || null;

    form.items = (inv.items || []).map(it => {
        const inventories = it.warehouse_inventories || [];
        const kasirStock = inventories
            .filter(i => i.location_type === 'kasir')
            .reduce((sum, i) => sum + Number(i.stock_on_hand || 0), 0);

        const gudangInv = inventories.find(i => i.location_type === 'gudang');
        const gudangStock = gudangInv ? Number(gudangInv.stock_on_hand || 0) : 0;

        // Stok masih utuh jika belum ada pergerakan ke kasir, tidak ada retur, dan stok gudang >= qty_converted
        const isIntact = !it.return_form && kasirStock <= 0 && gudangStock >= Number(it.qty_converted || 0);

        return {
            id: it.id,
            is_new_medicine: false,
            new_medicine_name: '',
            medicine_id: it.medicine_id,
            medicine_name: it.medicine?.nama_obat || 'Obat',
            original_medicine_id: it.medicine_id,
            original_medicine_name: it.medicine?.nama_obat || 'Obat',
            satuan_input: it.satuan_input || 'Box',
            satuan_terkecil: it.medicine?.satuan_stok || 'Pcs',
            unit_conversion_factor: it.unit_conversion_factor || 1,
            batch_no: it.batch_no || '',
            expired_date: it.expired_date ? String(it.expired_date).substring(0, 10) : '',
            qty_masuk: Number(it.qty_masuk || 1),
            harga_beli_satuan: Number(it.harga_beli_satuan || 0),
            diskon_persen: Number(it.diskon_persen || 0),
            is_intact: isIntact,
            gudang_stock: gudangStock,
            kasir_stock: kasirStock,
            show_medicine_selector: false,
            medicine_search: '',
        };
    });
};

watch(() => [props.modelValue, props.invoice], ([open, inv]) => {
    if (open && inv) {
        initForm(inv);
    }
}, { immediate: true });

const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    if (file) {
        form.foto_faktur = file;
        selectedFileName.value = file.name;
    }
};

const subtotal = computed(() => {
    return form.items.reduce((acc, it) => {
        const lineTotal = (Number(it.harga_beli_satuan || 0) * (1 - (Number(it.diskon_persen || 0) / 100))) * Number(it.qty_masuk || 0);
        return acc + lineTotal;
    }, 0);
});

const ppnAmount = computed(() => {
    return subtotal.value * (Number(form.ppn_persen || 0) / 100);
});

const grandTotal = computed(() => {
    return subtotal.value + ppnAmount.value;
});

const submit = () => {
    if (!form.faktur_no.trim()) {
        toast({ title: 'Gagal', description: 'Nomor faktur wajib diisi.', variant: 'destructive' });
        return;
    }
    if (!form.supplier_id) {
        toast({ title: 'Gagal', description: 'PBF / Supplier wajib dipilih.', variant: 'destructive' });
        return;
    }
    if (!form.tanggal_faktur) {
        toast({ title: 'Gagal', description: 'Tanggal faktur wajib diisi.', variant: 'destructive' });
        return;
    }

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('original_faktur_no', form.original_faktur_no);
    formData.append('faktur_no', form.faktur_no.trim());
    formData.append('supplier_id', form.supplier_id);
    formData.append('tanggal_faktur', form.tanggal_faktur);
    formData.append('ppn_persen', form.ppn_persen);

    if (form.foto_faktur) {
        formData.append('foto_faktur', form.foto_faktur);
    }

    form.items.forEach((item, index) => {
        formData.append(`items[${index}][id]`, item.id);
        formData.append(`items[${index}][is_new_medicine]`, item.is_new_medicine ? '1' : '0');
        if (item.is_new_medicine) {
            formData.append(`items[${index}][new_medicine_name]`, item.new_medicine_name || item.medicine_name);
        } else {
            formData.append(`items[${index}][medicine_id]`, item.medicine_id || item.original_medicine_id);
        }
        formData.append(`items[${index}][batch_no]`, item.batch_no);
        formData.append(`items[${index}][expired_date]`, item.expired_date);
        formData.append(`items[${index}][qty_masuk]`, item.qty_masuk);
        formData.append(`items[${index}][harga_beli_satuan]`, item.harga_beli_satuan);
        formData.append(`items[${index}][diskon_persen]`, item.diskon_persen || 0);
    });

    router.post(route('control.admin.incoming-stocks.update-invoice'), formData, {
        preserveScroll: true,
        only: ['stocks', 'flash', 'medicines'],
        onSuccess: () => {
            isSubmitting.value = false;
            close();
            emit('saved');
            toast({ title: 'Berhasil', description: 'Data faktur berhasil diperbarui.' });
        },
        onError: (errors) => {
            isSubmitting.value = false;
            const msg = typeof errors === 'object' ? Object.values(errors)[0] : 'Gagal memperbarui faktur.';
            toast({ title: 'Gagal', description: msg || 'Periksa kembali inputan Anda.', variant: 'destructive' });
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};
</script>

<template>
<Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-3 sm:p-5">
        <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- ═══ HEADER MODAL ═══ -->
            <div class="p-4 sm:p-4.5 border-b border-border flex items-center justify-between shrink-0 bg-muted/20">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-bold text-foreground">Koreksi Faktur Masuk</h3>
                            <span class="px-2 py-0.5 rounded-md bg-muted text-muted-foreground font-mono text-xs font-semibold">
                                {{ form.original_faktur_no }}
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Perbaiki typo nomor faktur, tanggal, PBF, harga beli, nomor batch, atau kuantitas yang masih utuh.
                        </p>
                    </div>
                </div>
                <button type="button" @click="close" class="p-1.5 text-muted-foreground hover:text-foreground rounded-md hover:bg-muted transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- ═══ SCROLL BODY ═══ -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 custom-scrollbar">
                
                <!-- BAGIAN 1: METADATA UTAMA FAKTUR -->
                <div class="bg-muted/10 border border-border/70 rounded-lg p-3.5 space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Informasi Nota &amp; Distributor (PBF)</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Nomor Faktur -->
                        <div>
                            <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                Nomor Faktur <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.faktur_no" 
                                required
                                placeholder="Contoh: INV-2026/01..."
                                class="w-full h-8.5 px-3 bg-background border border-input rounded-md text-xs font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                            />
                        </div>

                        <!-- Supplier / PBF -->
                        <div>
                            <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                Distributor / PBF <span class="text-rose-500">*</span>
                            </label>
                            <select 
                                v-model="form.supplier_id" 
                                required
                                class="w-full h-8.5 px-2.5 bg-background border border-input rounded-md text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                            >
                                <option value="" disabled>-- Pilih PBF --</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.nama_pbf }}</option>
                            </select>
                        </div>

                        <!-- Tanggal Faktur -->
                        <div>
                            <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                Tanggal Faktur <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                v-model="form.tanggal_faktur" 
                                required
                                class="w-full h-8.5 px-3 bg-background border border-input rounded-md text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                            />
                        </div>

                        <!-- PPN (%) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                PPN (%) <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                v-model.number="form.ppn_persen" 
                                min="0" 
                                max="100" 
                                step="0.1"
                                class="w-full h-8.5 px-3 bg-background border border-input rounded-md text-xs font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                            />
                        </div>
                    </div>

                    <!-- Foto Berkas Fisik Faktur -->
                    <div class="pt-1 border-t border-border/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Berkas Fisik Faktur:</span>
                            <span 
                                v-if="currentFotoPath" 
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20"
                            >
                                ✓ Sudah Ada Foto
                            </span>
                            <span 
                                v-else 
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 border border-amber-500/20"
                            >
                                ⚠ Belum Ada Foto
                            </span>
                            <button 
                                v-if="(currentFotoPath || form.foto_faktur) && (previewItemId || form.foto_faktur)" 
                                type="button" 
                                @click="showPhotoPreview = true" 
                                class="text-xs text-primary hover:underline font-semibold inline-flex items-center gap-1 ml-1 cursor-pointer"
                                title="Pratinjau foto tanpa buka tab baru"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                <span>Lihat Foto Nota</span>
                            </button>
                        </div>

                        <div>
                            <input 
                                type="file" 
                                ref="fileInput" 
                                class="hidden" 
                                accept="image/jpeg,image/png,image/jpg,application/pdf"
                                @change="handleFileChange" 
                            />
                            <div class="flex items-center gap-2">
                                <span v-if="selectedFileName" class="text-xs font-semibold text-foreground truncate max-w-[180px]">
                                    {{ selectedFileName }}
                                </span>
                                <button 
                                    type="button" 
                                    @click="fileInput?.click()" 
                                    class="h-9 px-4 rounded-lg bg-slate-100 dark:bg-muted text-slate-800 dark:text-foreground hover:bg-slate-200 dark:hover:bg-muted/80 border border-slate-200/80 dark:border-border text-xs font-semibold transition-all cursor-pointer flex items-center gap-2 shadow-2xs"
                                >
                                    <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span>{{ currentFotoPath ? 'Ganti Foto Nota' : 'Upload Foto Nota' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: DAFTAR ITEM OBAT DALAM FAKTUR (FORMULIR PER BARIS / CARD) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Item Sediaan Farmasi ({{ form.items.length }} Item)
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            * Kuantitas hanya dapat diedit jika stok belum terdistribusi ke kasir atau belum terjual.
                        </span>
                    </div>

                    <!-- List Formulir Item Berbentuk Card Berisi & Terstruktur -->
                    <div class="space-y-3">
                        <div 
                            v-for="(item, index) in form.items" 
                            :key="item.id" 
                            class="bg-slate-50/70 dark:bg-muted/20 border border-slate-200/80 dark:border-border rounded-xl p-3.5 sm:p-4 space-y-3.5 shadow-2xs transition-all"
                        >
                            <!-- Header Baris Item: Nomor Item, Nama Obat, Badge, & Subtotal -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-border/60">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-muted text-foreground text-xs font-bold font-mono">
                                        #{{ index + 1 }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-foreground">{{ item.medicine_name }}</span>
                                        <span 
                                            v-if="item.is_new_medicine" 
                                            class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/25"
                                        >
                                            + Sediaan Baru
                                        </span>
                                        <span 
                                            v-else-if="item.medicine_id !== item.original_medicine_id" 
                                            class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/25"
                                        >
                                            Dialihkan
                                        </span>
                                    </div>
                                    
                                    <!-- Kemasan info -->
                                    <span class="text-xs text-muted-foreground font-mono">
                                        (1 {{ item.satuan_input }} = {{ item.unit_conversion_factor }} {{ item.satuan_terkecil }})
                                    </span>

                                    <!-- Tombol Ganti Obat / Kembalikan -->
                                    <button 
                                        v-if="item.is_intact && !item.show_medicine_selector" 
                                        type="button" 
                                        @click="item.show_medicine_selector = true; item.medicine_search = ''"
                                        class="h-7 px-2.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 border border-blue-200 dark:border-blue-800 text-xs font-semibold transition-all inline-flex items-center gap-1 cursor-pointer shadow-2xs ml-1"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        <span>Ganti Obat</span>
                                    </button>

                                    <button 
                                        v-if="item.is_new_medicine || item.medicine_id !== item.original_medicine_id" 
                                        type="button" 
                                        @click="resetMedicineForItem(item)"
                                        class="text-xs font-medium text-rose-500 hover:underline cursor-pointer ml-1"
                                    >
                                        Batal Ganti
                                    </button>
                                </div>

                                <!-- Subtotal Item -->
                                <div class="text-left sm:text-right">
                                    <span class="text-[11px] text-muted-foreground mr-1.5 uppercase font-medium">Subtotal Item:</span>
                                    <strong class="text-sm font-bold text-foreground tabular-nums">
                                        Rp {{ Math.round((Number(item.harga_beli_satuan || 0) * (1 - (Number(item.diskon_persen || 0) / 100))) * Number(item.qty_masuk || 0)).toLocaleString('id-ID') }}
                                    </strong>
                                </div>
                            </div>

                            <!-- Dropdown / Search Input jika user klik Ganti Obat -->
                            <div v-if="item.show_medicine_selector" class="p-3 bg-card border border-primary/40 rounded-lg space-y-2 animate-in fade-in duration-150">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <input 
                                            type="text" 
                                            v-model="item.medicine_search" 
                                            placeholder="Ketik nama obat pengganti (misal: Amodipin)..." 
                                            class="w-full h-9 pl-8 pr-3 bg-background border border-primary rounded-md text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs" 
                                            autofocus
                                        />
                                    </div>
                                    <button 
                                        type="button" 
                                        @click="item.show_medicine_selector = false" 
                                        class="h-9 px-3 rounded-md border border-input bg-muted/60 hover:bg-muted text-xs font-medium text-muted-foreground cursor-pointer"
                                    >
                                        Tutup
                                    </button>
                                </div>

                                <!-- List Hasil Pencarian -->
                                <div class="max-h-48 overflow-y-auto border border-border rounded-md bg-background shadow-xs divide-y divide-border/60">
                                    <!-- Opsi Jika Ingin Membuat Obat Baru (Misal ketik 'Amodipin' dan tidak ada di katalog) -->
                                    <div 
                                        v-if="item.medicine_search && item.medicine_search.trim().length > 1" 
                                        @click="createNewMedicineForItem(item)"
                                        class="p-2.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-800 dark:text-emerald-300 border-b border-emerald-500/20 cursor-pointer flex items-center justify-between transition-colors"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">+</span>
                                            <span class="text-xs font-bold">Daftarkan Sediaan Baru: "{{ item.medicine_search.trim() }}"</span>
                                        </div>
                                        <span class="text-[10px] font-semibold uppercase bg-emerald-600 text-white px-2.5 py-1 rounded">Pilih Ini</span>
                                    </div>

                                    <!-- Opsi Obat yang Ada di Katalog -->
                                    <div 
                                        v-for="med in getFilteredMedicines(item)" 
                                        :key="med.id" 
                                        @click="selectMedicineForItem(item, med)"
                                        class="p-2.5 hover:bg-muted/70 cursor-pointer flex items-center justify-between transition-colors text-left"
                                        :class="item.medicine_id === med.id ? 'bg-primary/10 font-bold' : ''"
                                    >
                                        <div>
                                            <div class="text-xs font-semibold text-foreground">{{ med.nama_obat }}</div>
                                            <div class="text-[10px] text-muted-foreground mt-0.5">Satuan: {{ med.satuan_stok }} &bull; Rasio: {{ med.rasio_konversi || 1 }}</div>
                                        </div>
                                        <span class="text-[11px] text-primary font-semibold">Pilih &rarr;</span>
                                    </div>

                                    <div v-if="getFilteredMedicines(item).length === 0 && (!item.medicine_search || item.medicine_search.trim().length <= 1)" class="p-3 text-center text-xs text-muted-foreground italic">
                                        Ketik nama obat untuk mencari atau mendaftarkan baru.
                                    </div>
                                </div>
                            </div>

                            <!-- Grid Field Input Data Barang (Spacious, No Truncation!) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-1">
                                <!-- Nomor Batch -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                        No. Batch <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        v-model="item.batch_no" 
                                        required 
                                        placeholder="Contoh: AMD21390"
                                        class="w-full h-9 px-3 bg-background border border-input rounded-md text-xs font-mono font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                                    />
                                </div>

                                <!-- Exp Date -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                        Exp Date <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="date" 
                                        v-model="item.expired_date" 
                                        required 
                                        class="w-full h-9 px-3 bg-background border border-input rounded-md text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                                    />
                                </div>

                                <!-- Jumlah Masuk (Qty) -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                        Jumlah ({{ item.satuan_input }}) <span class="text-rose-500">*</span>
                                    </label>
                                    <div v-if="item.is_intact">
                                        <input 
                                            type="number" 
                                            v-model.number="item.qty_masuk" 
                                            min="1" 
                                            required 
                                            class="w-full h-9 px-3 bg-background border border-input rounded-md text-xs font-bold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                                        />
                                        <span class="text-[10px] text-muted-foreground mt-0.5 block">
                                            = {{ (Number(item.qty_masuk || 0) * Number(item.unit_conversion_factor || 1)) }} {{ item.satuan_terkecil }}
                                        </span>
                                    </div>
                                    <div v-else>
                                        <input 
                                            type="number" 
                                            :value="item.qty_masuk" 
                                            disabled 
                                            class="w-full h-9 px-3 bg-muted/60 text-muted-foreground border border-input rounded-md text-xs font-bold cursor-not-allowed shadow-2xs"
                                        />
                                        <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold mt-0.5 block">
                                            Terkunci (Stok sudah di kasir)
                                        </span>
                                    </div>
                                </div>

                                <!-- Harga Beli Satuan -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                        Harga Beli Satuan (Rp) <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="number" 
                                        v-model.number="item.harga_beli_satuan" 
                                        min="0" 
                                        step="100" 
                                        required 
                                        placeholder="0"
                                        class="w-full h-9 px-3 bg-background border border-input rounded-md text-xs font-semibold text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                                    />
                                </div>

                                <!-- Diskon (%) -->
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                        Diskon (%)
                                    </label>
                                    <input 
                                        type="number" 
                                        v-model.number="item.diskon_persen" 
                                        min="0" 
                                        max="100" 
                                        step="0.5" 
                                        placeholder="0"
                                        class="w-full h-9 px-3 bg-background border border-input rounded-md text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary shadow-2xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ FOOTER & SUMMARY ═══ -->
            <div class="p-3.5 sm:p-4 border-t border-border bg-card flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-4 text-xs font-medium w-full sm:w-auto justify-between sm:justify-start">
                    <div>
                        <span class="text-muted-foreground mr-1.5">Sub Nota:</span>
                        <strong class="text-foreground">Rp {{ Math.round(subtotal).toLocaleString('id-ID') }}</strong>
                    </div>
                    <div>
                        <span class="text-muted-foreground mr-1.5">PPN ({{ form.ppn_persen }}%):</span>
                        <strong class="text-rose-600 dark:text-rose-400">+ Rp {{ Math.round(ppnAmount).toLocaleString('id-ID') }}</strong>
                    </div>
                    <div>
                        <span class="text-muted-foreground mr-1.5">Total Akhir:</span>
                        <strong class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ Math.round(grandTotal).toLocaleString('id-ID') }}</strong>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                    <button 
                        type="button" 
                        @click="close" 
                        class="h-9 px-4 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-muted dark:hover:bg-muted/80 text-foreground border border-slate-200/80 dark:border-border text-xs font-semibold transition-all cursor-pointer shadow-xs"
                    >
                        Batal
                    </button>
                    <button 
                        type="button" 
                        @click="submit" 
                        :disabled="isSubmitting"
                        class="h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold shadow-xs disabled:opacity-50 transition-all cursor-pointer flex items-center justify-center gap-2"
                    >
                        <svg v-if="isSubmitting" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</Teleport>

    <!-- ═══ LIGHTBOX PRATINJAU FOTO FAKTUR (ZERO-LOAD) ═══ -->
    <Teleport to="body">
        <div 
            v-if="showPhotoPreview" 
            class="fixed inset-0 bg-black/80 backdrop-blur-xs z-[9999] flex flex-col items-center justify-center p-3 sm:p-6 animate-in fade-in duration-150"
            @click.self="showPhotoPreview = false"
        >
            <div class="relative max-w-4xl w-full bg-card border border-border rounded-xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden" @click.stop>
                <!-- Lightbox Header -->
                <div class="p-3.5 px-4 border-b border-border flex items-center justify-between bg-muted/30">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h4 class="text-xs sm:text-sm font-bold text-foreground">
                            Pratinjau Fisik Faktur: {{ form.faktur_no }}
                        </h4>
                    </div>
                    <div class="flex items-center gap-2">
                        <a 
                            v-if="previewPhotoUrl" 
                            :href="previewPhotoUrl" 
                            target="_blank" 
                            class="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-input bg-background hover:bg-muted transition-colors font-medium"
                            title="Buka file asli di tab baru"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            <span>Tab Baru</span>
                        </a>
                        <button 
                            type="button" 
                            @click="showPhotoPreview = false" 
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Lightbox Body -->
                <div class="p-4 flex-1 flex items-center justify-center bg-slate-950/20 overflow-auto min-h-[350px] max-h-[75vh]">
                    <iframe 
                        v-if="isPdf && previewPhotoUrl" 
                        :src="previewPhotoUrl" 
                        class="w-full h-[70vh] border-0 rounded-lg shadow-inner bg-white"
                    ></iframe>
                    <img 
                        v-else-if="previewPhotoUrl" 
                        :src="previewPhotoUrl" 
                        alt="Foto Nota Faktur" 
                        class="max-w-full max-h-[72vh] object-contain rounded-lg shadow-md"
                    />
                    <div v-else class="text-xs text-muted-foreground py-10">
                        Tidak ada pratinjau yang tersedia.
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

