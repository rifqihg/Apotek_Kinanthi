<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';
import { toast } from '@/Components/ui/toast';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogFooter, 
    DialogHeader, 
    DialogTitle 
} from '@/Components/ui/dialog';
import { 
    AlertDialog, 
    AlertDialogAction, 
    AlertDialogCancel, 
    AlertDialogContent, 
    AlertDialogDescription, 
    AlertDialogFooter, 
    AlertDialogHeader, 
    AlertDialogTitle 
} from '@/Components/ui/alert-dialog';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    kasirs: {
        type: Array,
        default: () => [],
    },
    selectedKasirId: [String, Number],
    medicines: {
        type: [Array, Object],
        default: () => [],
    },
});

const dropdownOpen = ref(false);
const isLoadingKasir = ref(false);

const selectedKasirId = ref(props.selectedKasirId || (props.kasirs?.[0]?.id || ''));
const activeFilterTab = ref('all');
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 16;

const rawMedicines = computed(() => {
    if (Array.isArray(props.medicines)) {
        return props.medicines;
    }
    return props.medicines?.data || [];
});

const selectKasir = (kasirId) => {
    if (selectedKasirId.value === kasirId) {
        dropdownOpen.value = false;
        return;
    }
    selectedKasirId.value = kasirId;
    dropdownOpen.value = false;
    cart.value = [];
    activeFilterTab.value = 'all';
    searchQuery.value = '';
    currentPage.value = 1;

    isLoadingKasir.value = true;
    router.get(route('control.admin.inventory.distribution'), {
        kasir_id: kasirId || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        showProgress: false,
        onFinish: () => {
            isLoadingKasir.value = false;
        },
    });
};

watch(() => props.selectedKasirId, (newVal) => {
    if (newVal && newVal !== selectedKasirId.value) {
        selectedKasirId.value = newVal;
    }
});

const selectedKasir = computed(() => {
    return props.kasirs?.find(k => k.id === Number(selectedKasirId.value) || k.id === selectedKasirId.value);
});

const classificationLabels = {
    'obat_keras': 'Obat Keras',
    'bebas_terbatas': 'Bebas Terbatas',
    'bebas': 'Obat Bebas',
    'psikotropika': 'Psikotropika',
    'narkotika': 'Narkotika',
    'prekursor': 'Prekursor Farmasi',
    'jamu': 'Jamu / Herbal',
    'suplemen': 'Suplemen',
    'lainnya': 'Lainnya',
};

// Filter tabs computed purely from in-memory collection (0ms delay)
const filterTabs = computed(() => {
    const list = rawMedicines.value;
    const totalAll = list.length;
    
    // Urgent restocking: kasir has 0 stock, but warehouse has stock ready to transfer
    const kasirKosongCount = list.filter(m => Number(m.stok_kasir || 0) <= 0 && Number(m.stok_gudang || 0) > 0).length;
    
    const classCounts = {};
    for (const m of list) {
        const cls = (m.classification || 'lainnya').toLowerCase().trim();
        classCounts[cls] = (classCounts[cls] || 0) + 1;
    }
    
    const tabs = [
        { key: 'all', label: 'Semua Sediaan', count: totalAll, isSpecial: false }
    ];
    
    if (kasirKosongCount > 0) {
        tabs.push({
            key: 'kasir_kosong',
            label: 'Kasir Kosong (Perlu Restock)',
            count: kasirKosongCount,
            isSpecial: true,
        });
    }
    
    const priorityOrder = ['obat_keras', 'bebas_terbatas', 'bebas', 'psikotropika', 'narkotika', 'prekursor', 'jamu', 'suplemen', 'lainnya'];
    
    for (const key of priorityOrder) {
        if (classCounts[key] > 0) {
            tabs.push({
                key: key,
                label: classificationLabels[key] || key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()),
                count: classCounts[key],
                isSpecial: false,
            });
        }
    }
    
    for (const key in classCounts) {
        if (!priorityOrder.includes(key) && classCounts[key] > 0) {
            tabs.push({
                key: key,
                label: key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()),
                count: classCounts[key],
                isSpecial: false,
            });
        }
    }
    
    return tabs;
});

const selectFilterTab = (tabKey) => {
    activeFilterTab.value = tabKey;
    currentPage.value = 1;
};

const clearSearch = () => {
    searchQuery.value = '';
    currentPage.value = 1;
};

// Real-time reactive filtering in RAM (0ms delay)
const filteredMedicines = computed(() => {
    let list = rawMedicines.value;
    
    // 1. Tab filter
    if (activeFilterTab.value === 'kasir_kosong') {
        list = list.filter(m => Number(m.stok_kasir || 0) <= 0 && Number(m.stok_gudang || 0) > 0);
    } else if (activeFilterTab.value !== 'all') {
        const targetCls = activeFilterTab.value.toLowerCase();
        list = list.filter(m => ((m.classification || 'lainnya').toLowerCase() === targetCls));
    }
    
    // 2. Search filter
    if (searchQuery.value && searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(m => {
            const nameMatch = m.nama_obat && m.nama_obat.toLowerCase().includes(q);
            const codeMatch = m.kode_obat && m.kode_obat.toLowerCase().includes(q);
            const barcodeMatch = m.barcode && m.barcode.toLowerCase().includes(q);
            return nameMatch || codeMatch || barcodeMatch;
        });
    }
    
    return list;
});

// Client-side pagination (0ms delay)
const totalPages = computed(() => Math.max(1, Math.ceil(filteredMedicines.value.length / itemsPerPage)));

watch([filteredMedicines, totalPages], () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = 1;
    }
});

const paginatedMedicines = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredMedicines.value.slice(start, start + itemsPerPage);
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// --- CART & DIALOG LOGIC ---
const cart = ref([]);

const cartStats = computed(() => {
    const transferCount = cart.value.filter(i => i.action === 'transfer').length;
    const returnCount = cart.value.filter(i => i.action === 'return').length;
    return {
        total: cart.value.length,
        transfer: transferCount,
        return: returnCount
    };
});

const clearCart = () => {
    cart.value = [];
};

const updateCartQty = (index, delta) => {
    const item = cart.value[index];
    if (!item) return;
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
    } else if (newQty > item.maxQty) {
        toast.warning('Batas Maksimal', {
            description: `Maksimal kuantitas adalah ${formatNumber(item.maxQty)} ${item.satuan_terkecil}`
        });
    } else {
        item.quantity = newQty;
    }
};

const isQtyDialogOpen = ref(false);
const selectedMedicine = ref(null);
const qtyDialogAction = ref('transfer');
const inputQty = ref(1);
const qtyValidationError = ref('');

const selectedUnit = computed(() => {
    if (!selectedMedicine.value) return 'Item';
    return selectedMedicine.value.satuan_terkecil || selectedMedicine.value.satuan_stok || 'Item';
});

const maxAllowedQty = computed(() => {
    if (!selectedMedicine.value) return 0;
    return qtyDialogAction.value === 'transfer' 
        ? (selectedMedicine.value.stok_gudang || 0) 
        : (selectedMedicine.value.stok_kasir || 0);
});

const openQtyPrompt = (medicine, action) => {
    let actionText = action === 'transfer' ? 'Transfer ke Kasir' : 'Return ke Gudang';
    let maxQty = action === 'transfer' ? medicine.stok_gudang : medicine.stok_kasir;
    
    if (maxQty <= 0) {
        toast.warning('Stok Kosong', {
            description: `Tidak ada stok yang tersedia untuk di-${actionText.toLowerCase()}.`
        });
        return;
    }

    selectedMedicine.value = medicine;
    qtyDialogAction.value = action;
    inputQty.value = 1;
    qtyValidationError.value = '';
    isQtyDialogOpen.value = true;
};

const confirmAddQty = () => {
    const qty = parseFloat(inputQty.value);
    if (!qty || qty <= 0) {
        qtyValidationError.value = 'Kuantitas harus lebih dari 0.';
        return;
    }
    if (qty > maxAllowedQty.value) {
        qtyValidationError.value = `Kuantitas melebihi stok maksimal (${formatNumber(maxAllowedQty.value)} ${selectedUnit.value}).`;
        return;
    }

    addToCart(selectedMedicine.value, qtyDialogAction.value, qty);
    isQtyDialogOpen.value = false;
};

const addToCart = (medicine, action, quantity) => {
    // Check if already in cart with SAME action
    const existingIndex = cart.value.findIndex(item => item.medicine_id === medicine.id && item.action === action);
    
    if (existingIndex !== -1) {
        cart.value[existingIndex].quantity = quantity;
    } else {
        // Remove opposite action to prevent conflicting mutations
        const oppositeAction = action === 'transfer' ? 'return' : 'transfer';
        const oppIndex = cart.value.findIndex(item => item.medicine_id === medicine.id && item.action === oppositeAction);
        if (oppIndex !== -1) {
            cart.value.splice(oppIndex, 1);
        }
        
        const unit = medicine.satuan_terkecil || medicine.satuan_stok || 'Item';
        cart.value.push({
            medicine_id: medicine.id,
            nama_obat: medicine.nama_obat,
            satuan_terkecil: unit,
            action: action,
            quantity: quantity,
            maxQty: action === 'transfer' ? medicine.stok_gudang : medicine.stok_kasir
        });
    }
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const isSubmitDialogOpen = ref(false);
const isProcessingSubmit = ref(false);

const submitCart = () => {
    if (cart.value.length === 0) return;
    isSubmitDialogOpen.value = true;
};

const confirmSubmitCart = () => {
    isSubmitDialogOpen.value = false;
    isProcessingSubmit.value = true;

    router.post(route('control.admin.inventory.distribution.process'), {
        kasir_id: selectedKasirId.value,
        items: cart.value.map(item => ({
            medicine_id: item.medicine_id,
            action: item.action,
            quantity: item.quantity
        }))
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            isProcessingSubmit.value = false;
        },
        onError: () => {
            isProcessingSubmit.value = false;
        },
        onFinish: () => {
            isProcessingSubmit.value = false;
        }
    });
};

const formatNumber = (num) => parseFloat(num || 0).toLocaleString('id-ID');

const photoFileInput = ref(null);
const selectedMedicineForPhoto = ref(null);

const uploadPhoto = (medicine) => {
    selectedMedicineForPhoto.value = medicine;
    if (photoFileInput.value) {
        photoFileInput.value.value = '';
        photoFileInput.value.click();
    }
};

const handlePhotoUpload = (e) => {
    const file = e.target.files?.[0];
    if (!file || !selectedMedicineForPhoto.value) return;

    router.post(route('control.admin.medicines.updatePhoto', selectedMedicineForPhoto.value.id), {
        _method: 'post',
        foto_obat: file
    }, {
        preserveScroll: true,
        onError: (errors) => {
            const msg = typeof errors === 'object' ? Object.values(errors)[0] : 'Gagal mengupload foto';
            toast.error('Gagal', { description: msg || 'Gagal mengupload foto' });
        }
    });
};
</script>

<template>
    <Head title="Distribusi Stok (Cart)" />

    <div class="space-y-3.5">
        <!-- ═══ BENTO HEADER COCKPIT (CLEAN ENTERPRISE DENSITY) ═══ -->
        <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                
                <!-- SISI KIRI: PEMILIHAN KASIR & STATUS AKTIF -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                    <div class="w-full sm:w-64 lg:w-72">
                        <label class="block text-[11px] font-bold text-muted-foreground uppercase tracking-wider mb-1">
                            Kasir Tujuan / Sumber
                        </label>
                        <div class="relative">
                            <button 
                                type="button" 
                                @click="dropdownOpen = !dropdownOpen" 
                                @blur="setTimeout(() => dropdownOpen = false, 200)" 
                                class="h-9 w-full bg-slate-50 dark:bg-muted/50 border border-slate-200 dark:border-border rounded-lg px-3 text-xs font-semibold text-foreground focus:ring-1 focus:ring-ring cursor-pointer outline-none transition-all flex items-center justify-between gap-2 hover:bg-slate-100 dark:hover:bg-muted/80 shadow-2xs"
                            >
                                <span class="truncate">{{ selectedKasir?.name || '-- Pilih User Kasir --' }}</span>
                                <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200 shrink-0" :class="{'rotate-180': dropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>

                            <div v-show="dropdownOpen" class="absolute left-0 top-full mt-1 w-full bg-popover border border-border rounded-lg shadow-lg overflow-hidden z-30 origin-top-left transition-all">
                                <div class="p-1 space-y-0.5 max-h-60 overflow-y-auto custom-scrollbar">
                                    <button 
                                        type="button" 
                                        @click="selectKasir(''); dropdownOpen = false" 
                                        class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md uppercase tracking-wider flex items-center justify-between group transition-colors cursor-pointer" 
                                        :class="!selectedKasirId ? 'bg-accent text-accent-foreground font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                                    >
                                        -- Pilih User Kasir --
                                    </button>
                                    <div class="h-px bg-border my-1"></div>
                                    <button 
                                        type="button" 
                                        v-for="kasir in kasirs" 
                                        :key="kasir.id" 
                                        @click="selectKasir(kasir.id); dropdownOpen = false" 
                                        class="w-full text-left px-2.5 py-1.5 text-xs font-medium rounded-md uppercase tracking-wider flex items-center justify-between group transition-colors cursor-pointer" 
                                        :class="selectedKasirId === kasir.id ? 'bg-accent text-accent-foreground font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                                    >
                                        <span class="truncate">{{ kasir.name }}</span>
                                        <div v-if="selectedKasirId === kasir.id" class="w-2 h-2 rounded-full bg-primary shrink-0"></div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedKasir" class="hidden sm:flex items-center gap-2 pl-3 sm:border-l border-slate-200 dark:border-border text-xs text-muted-foreground pt-3 sm:pt-4">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Kasir aktif: <strong class="text-foreground font-semibold">{{ selectedKasir.name }}</strong> ({{ rawMedicines.length }} sediaan)</span>
                    </div>
                </div>

                <!-- SISI KANAN: PENCARIAN OBAT -->
                <div class="w-full sm:w-72 md:w-80" v-if="selectedKasirId">
                    <label class="block text-[11px] font-bold text-muted-foreground uppercase tracking-wider mb-1">
                        Cari Obat &amp; Sediaan
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <svg class="h-3.5 w-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            placeholder="Ketik nama, barcode, atau kode obat..." 
                            class="h-9 pl-8 pr-7 block w-full rounded-lg border border-slate-200 dark:border-border bg-slate-50 dark:bg-muted/40 focus:ring-1 focus:ring-ring text-xs font-medium text-foreground placeholder-muted-foreground transition-all shadow-2xs"
                        />
                        <button 
                            v-if="searchQuery" 
                            type="button" 
                            @click="clearSearch" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- ═══ EMPTY STATE KASIR BELUM DIPILIH ═══ -->
        <div v-if="!selectedKasirId" class="min-h-[460px] bg-white dark:bg-card rounded-xl border border-slate-200/80 dark:border-border shadow-2xs flex items-center justify-center p-6 sm:p-12">
            <div class="text-center max-w-md">
                <div class="bg-primary/10 w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-3.5 border border-primary/20">
                    <svg class="h-7 w-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </div>
                <h3 class="text-base font-bold text-foreground mb-1">Pilih User Kasir</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                    Silakan tentukan akun Kasir pada opsi di atas untuk mulai mendistribusikan atau mengembalikan stok sediaan farmasi.
                </p>
            </div>
        </div>

        <!-- ═══ SPLIT WORKSPACE: KATALOG (KIRI) & KERANJANG (KANAN) ═══ -->
        <div v-else class="h-[calc(100vh-220px)] min-h-[580px] flex flex-col lg:flex-row gap-3.5 overflow-hidden">
            
            <!-- ─── KOLOM KIRI: KATALOG PRODUK OBAT ─── -->
            <div class="flex-1 flex flex-col bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl shadow-2xs overflow-hidden">
                
                <!-- SUB-HEADER: OPERATIONAL & BPOM CLASSIFICATION PILLS -->
                <div class="p-2.5 sm:p-3 border-b border-slate-200/80 dark:border-border bg-slate-50/50 dark:bg-muted/20 flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5 w-full">
                        <button 
                            v-for="tab in filterTabs" 
                            :key="tab.key"
                            type="button"
                            @click="selectFilterTab(tab.key)"
                            :class="[
                                activeFilterTab === tab.key
                                    ? (tab.isSpecial 
                                        ? 'bg-rose-600 text-white shadow-xs font-semibold' 
                                        : 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs font-semibold')
                                    : (tab.isSpecial
                                        ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-900/50 font-medium'
                                        : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80 font-medium')
                            ]"
                            class="h-9 px-3 rounded-lg text-xs font-semibold flex items-center justify-center gap-2 shrink-0 whitespace-nowrap cursor-pointer transition-all"
                        >
                            <span>{{ tab.label }}</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeFilterTab === tab.key 
                                    ? 'bg-white/20 text-white' 
                                    : (tab.isSpecial 
                                        ? 'bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200' 
                                        : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground')"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- SCROLL BODY: AUTO-FILL GRID OBAT (KARTU TIDAK AKAN GEPENG KETIKA SIDEBAR DITUTUP) -->
                <div class="flex-1 overflow-y-auto p-3.5 sm:p-4 bg-slate-50/40 dark:bg-muted/10 custom-scrollbar transition-opacity duration-200" :class="{'opacity-60 pointer-events-none': isLoadingKasir}">
                    <div v-if="filteredMedicines.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-12 h-12 rounded-xl bg-muted/60 flex items-center justify-center text-xl mb-3 border border-border">
                            💊
                        </div>
                        <h4 class="font-bold text-foreground text-sm">Tidak Ada Obat Ditemukan</h4>
                        <p class="text-xs text-muted-foreground max-w-sm mt-1">
                            Tidak ditemukan stok sediaan pada filter ini. Coba kata kunci lain atau pilih filter berbeda.
                        </p>
                    </div>

                    <!-- GRID RESPONSIVE BERBASIS AUTO-FILL (OTOMATIS 4 KARTU SAAT SIDEBAR BUKA, 5 KARTU SAAT SIDEBAR TUTUP) -->
                    <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(170px,1fr))] gap-3 sm:gap-3.5">
                        <div v-for="medicine in paginatedMedicines" :key="medicine.id" class="bg-card rounded-lg shadow-sm border border-border hover:shadow-md transition-shadow overflow-hidden flex flex-col">
                            <!-- Image -->
                            <div class="h-32 bg-muted/40 flex items-center justify-center relative group">
                                <img v-if="medicine.foto_obat" :src="`/storage/medicines/${medicine.foto_obat}`" class="w-full h-full object-cover cursor-pointer" @click="uploadPhoto(medicine)" title="Klik untuk mengganti foto" />
                                <div v-else @click="uploadPhoto(medicine)" class="w-full h-full flex flex-col items-center justify-center text-muted-foreground hover:bg-muted/60 transition cursor-pointer" title="Klik untuk upload foto obat">
                                    <svg class="h-8 w-8 text-muted-foreground mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tambah Foto</span>
                                </div>
                                
                                <span class="absolute top-2 right-2 bg-card/95 backdrop-blur-sm px-2 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider text-primary border border-border shadow-2xs pointer-events-none">
                                    {{ medicine.satuan_terkecil || medicine.satuan_stok || 'Item' }}
                                </span>
                            </div>
                            
                            <!-- Info -->
                            <div class="p-3 flex-1 flex flex-col">
                                <h4 class="text-sm font-semibold text-foreground leading-tight mb-1 line-clamp-2" :title="medicine.nama_obat">{{ medicine.nama_obat }}</h4>
                                <p class="text-xs text-muted-foreground tracking-wide mb-2">{{ medicine.kode_obat || '-' }}</p>
                                
                                <div class="mt-auto space-y-1">
                                    <div class="flex justify-between items-center text-xs font-medium">
                                        <span class="text-muted-foreground">Stok Gudang:</span>
                                        <span class="font-bold text-primary">{{ formatNumber(medicine.stok_gudang) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs font-medium">
                                        <span class="text-muted-foreground">Stok Kasir:</span>
                                        <span class="font-bold" :class="medicine.stok_kasir <= 0 ? 'text-rose-600 font-extrabold' : 'text-emerald-600'">
                                            {{ formatNumber(medicine.stok_kasir) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions (Card Obat Final Preserved) -->
                            <div class="grid grid-cols-2 border-t border-border divide-x divide-border">
                                <button @click="openQtyPrompt(medicine, 'transfer')" 
                                        :disabled="medicine.stok_gudang <= 0"
                                        class="py-2 text-center text-primary font-semibold bg-primary/5 hover:bg-primary/10 disabled:opacity-40 disabled:bg-muted disabled:text-muted-foreground transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    <span class="text-xs">Ke Kasir</span>
                                </button>
                                <button @click="openQtyPrompt(medicine, 'return')" 
                                        :disabled="medicine.stok_kasir <= 0"
                                        class="py-2 text-center text-destructive font-semibold bg-destructive/10 hover:bg-destructive/20 disabled:opacity-40 disabled:bg-muted disabled:text-muted-foreground transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                    <span class="text-xs">Ke Gudang</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INSTANT CLIENT-SIDE PAGINATION -->
                <div v-if="filteredMedicines.length > 0" class="p-2.5 sm:p-3 border-t border-slate-200/80 dark:border-border bg-white dark:bg-card flex flex-col sm:flex-row items-center justify-between gap-2 shrink-0">
                    <div class="text-[11px] text-muted-foreground">
                        Menampilkan <strong class="text-foreground">{{ (currentPage - 1) * itemsPerPage + 1 }}</strong> - <strong class="text-foreground">{{ Math.min(currentPage * itemsPerPage, filteredMedicines.length) }}</strong> dari <strong class="text-foreground">{{ filteredMedicines.length }}</strong> sediaan
                    </div>
                    <div v-if="totalPages > 1" class="flex items-center gap-1">
                        <button 
                            type="button" 
                            @click="goToPage(currentPage - 1)" 
                            :disabled="currentPage === 1"
                            class="h-7 px-2 rounded-md text-xs font-semibold border border-slate-200 dark:border-border disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-100 dark:hover:bg-muted transition-colors cursor-pointer"
                        >
                            &larr; Prev
                        </button>
                        <template v-for="p in totalPages" :key="p">
                            <button 
                                v-if="p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)"
                                type="button" 
                                @click="goToPage(p)" 
                                class="h-7 min-w-7 px-2 rounded-md text-xs font-semibold transition-colors cursor-pointer"
                                :class="currentPage === p ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' : 'border border-slate-200 dark:border-border hover:bg-slate-100 dark:hover:bg-muted text-foreground'"
                            >
                                {{ p }}
                            </button>
                            <span v-else-if="p === 2 && currentPage > 3" class="px-1 text-xs text-muted-foreground">...</span>
                            <span v-else-if="p === totalPages - 1 && currentPage < totalPages - 2" class="px-1 text-xs text-muted-foreground">...</span>
                        </template>
                        <button 
                            type="button" 
                            @click="goToPage(currentPage + 1)" 
                            :disabled="currentPage === totalPages"
                            class="h-7 px-2 rounded-md text-xs font-semibold border border-slate-200 dark:border-border disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-100 dark:hover:bg-muted transition-colors cursor-pointer"
                        >
                            Next &rarr;
                        </button>
                    </div>
                </div>

            </div>

            <!-- ─── KOLOM KANAN: PANEL KERANJANG DISTRIBUSI ─── -->
            <div class="w-full lg:w-[350px] xl:w-[370px] bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl shadow-2xs flex flex-col overflow-hidden shrink-0">
                
                <!-- HEADER KERANJANG -->
                <div class="p-3 sm:p-3.5 border-b border-slate-200/80 dark:border-border bg-slate-50/60 dark:bg-muted/20 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-md bg-emerald-500/10 text-emerald-600 flex items-center justify-center border border-emerald-500/20">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-xs sm:text-sm text-foreground leading-tight">Keranjang Distribusi</h3>
                            <p class="text-[10px] text-muted-foreground">Antrean Mutasi FEFO</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <Link 
                            :href="route('control.admin.stock-logs')" 
                            class="h-8 px-2.5 rounded-lg border border-slate-200 dark:border-border bg-white dark:bg-card text-xs font-semibold text-slate-700 dark:text-muted-foreground hover:bg-slate-100 dark:hover:bg-muted hover:text-foreground flex items-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                            title="Buka Log Mutasi & Riwayat Distribusi"
                        >
                            <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Riwayat</span>
                        </Link>

                        <div class="h-8 px-2.5 rounded-lg bg-emerald-600 text-white text-xs font-bold flex items-center justify-center shadow-2xs">
                            {{ cart.length }}
                        </div>
                        <button 
                            v-if="cart.length > 0" 
                            type="button" 
                            @click="clearCart" 
                            class="h-8 px-2.5 rounded-lg text-xs font-semibold text-destructive hover:text-destructive bg-destructive/10 hover:bg-destructive/20 transition-colors cursor-pointer flex items-center justify-center"
                            title="Kosongkan Keranjang"
                        >
                            Reset
                        </button>
                    </div>
                </div>
                
                <!-- LIST ITEM KERANJANG (SCROLLABLE) -->
                <div class="flex-1 overflow-y-auto p-2.5 sm:p-3 bg-slate-50/30 dark:bg-muted/10 space-y-2 custom-scrollbar">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground p-5 text-center">
                        <div class="w-11 h-11 rounded-xl bg-muted/60 flex items-center justify-center mb-2 border border-border">
                            <svg class="w-5 h-5 text-muted-foreground/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <p class="text-xs font-bold text-foreground">Keranjang Masih Kosong</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5 max-w-[200px] leading-relaxed">
                            Pilih obat pada katalog di sebelah kiri untuk menambah ke antrean mutasi stok.
                        </p>
                    </div>
                    
                    <div class="space-y-2" v-else>
                        <div 
                            v-for="(item, index) in cart" 
                            :key="index" 
                            class="bg-white dark:bg-card rounded-lg border p-2.5 shadow-2xs relative group transition-all"
                            :class="item.action === 'transfer' ? 'border-blue-200/90 dark:border-blue-900/40' : 'border-amber-200/90 dark:border-amber-900/40'"
                        >
                            <button 
                                @click="removeFromCart(index)" 
                                type="button"
                                class="absolute -top-1.5 -right-1.5 bg-white dark:bg-card rounded-full border border-border shadow-xs p-1 text-muted-foreground hover:text-destructive hover:border-destructive transition-colors opacity-0 group-hover:opacity-100 cursor-pointer z-10"
                                title="Hapus dari keranjang"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                                
                            <div class="flex gap-2 items-start">
                                <div 
                                    class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 mt-0.5" 
                                    :class="item.action === 'transfer' ? 'bg-primary/10 text-primary' : 'bg-amber-500/10 text-amber-600'"
                                >
                                    <svg v-if="item.action === 'transfer'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <h5 class="text-xs font-bold text-foreground truncate" :title="item.nama_obat">{{ item.nama_obat }}</h5>
                                    <div class="flex items-center gap-1.5 mt-0.5 mb-1.5">
                                        <span 
                                            class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.2 rounded-md"
                                            :class="item.action === 'transfer' ? 'bg-primary/10 text-primary' : 'bg-amber-500/10 text-amber-600'"
                                        >
                                            {{ item.action === 'transfer' ? 'Gudang ➝ Kasir' : 'Kasir ➝ Gudang' }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground">Maks: {{ formatNumber(item.maxQty) }}</span>
                                    </div>
                                    
                                    <!-- QTY CONTROLLER -->
                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center border border-slate-200 dark:border-border rounded-md bg-slate-50 dark:bg-muted/40 p-0.5">
                                            <button 
                                                type="button" 
                                                @click="updateCartQty(index, -1)" 
                                                class="w-5 h-5 rounded flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-white dark:hover:bg-card transition-colors cursor-pointer font-bold text-xs"
                                            >
                                                -
                                            </button>
                                            <input 
                                                type="number" 
                                                v-model.number="item.quantity" 
                                                class="h-5 w-12 text-center border-0 bg-transparent text-xs font-bold text-foreground focus:ring-0 p-0" 
                                                min="1" 
                                                :max="item.maxQty"
                                            />
                                            <button 
                                                type="button" 
                                                @click="updateCartQty(index, 1)" 
                                                class="w-5 h-5 rounded flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-white dark:hover:bg-card transition-colors cursor-pointer font-bold text-xs"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <span class="text-[11px] text-muted-foreground font-semibold">{{ item.satuan_terkecil }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STICKY FOOTER KERANJANG -->
                <div class="p-3 sm:p-3.5 border-t border-slate-200/80 dark:border-border bg-white dark:bg-card shrink-0 space-y-2.5">
                    <div class="flex items-center justify-between text-xs font-medium">
                        <span class="text-muted-foreground">Total Antrean:</span>
                        <div class="flex items-center gap-1.5">
                            <span v-if="cartStats.transfer > 0" class="text-[10px] font-bold text-primary bg-primary/10 px-1.5 py-0.2 rounded-md">
                                {{ cartStats.transfer }} Ke Kasir
                            </span>
                            <span v-if="cartStats.return > 0" class="text-[10px] font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.2 rounded-md">
                                {{ cartStats.return }} Ke Gudang
                            </span>
                            <span class="text-sm font-bold text-foreground">{{ cart.length }}</span>
                        </div>
                    </div>

                    <button 
                        @click="submitCart" 
                        type="button"
                        :disabled="cart.length === 0 || isProcessingSubmit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3.5 rounded-lg shadow-xs disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-[0.99] flex justify-center items-center gap-2 text-xs sm:text-sm cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>{{ isProcessingSubmit ? 'Memproses Distribusi...' : 'Proses Semua Distribusi' }}</span>
                    </button>
                </div>

            </div>

        </div>

        <!-- ═══ HIDDEN FILE INPUT FOR PHOTO UPLOAD ═══ -->
        <input 
            type="file" 
            ref="photoFileInput" 
            class="hidden" 
            accept="image/*" 
            @change="handlePhotoUpload" 
        />

        <!-- ═══ MODAL INPUT KUANTITAS DISTRIBUSI ═══ -->
        <Dialog v-model:open="isQtyDialogOpen">
            <DialogContent class="sm:max-w-[420px] rounded-xl">
                <DialogHeader>
                    <DialogTitle>{{ qtyDialogAction === 'transfer' ? 'Transfer ke Kasir' : 'Return ke Gudang' }}</DialogTitle>
                    <DialogDescription>
                        Berapa banyak kuantitas <strong>{{ selectedMedicine?.nama_obat }}</strong> yang ingin dipindahkan?
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-3.5 py-2">
                    <div v-if="selectedMedicine?.kemasan && selectedMedicine?.kemasan !== selectedUnit" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary/10 border border-primary/20 rounded-md text-primary text-xs font-semibold">
                        Kemasan: {{ selectedMedicine?.kemasan }}
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Jumlah Kuantitas ({{ selectedUnit }})</span>
                            <span>Maksimal: <strong class="text-foreground">{{ formatNumber(maxAllowedQty) }} {{ selectedUnit }}</strong></span>
                        </div>
                        <Input 
                            type="number" 
                            v-model.number="inputQty" 
                            min="1" 
                            :max="maxAllowedQty" 
                            step="1"
                            placeholder="Masukkan jumlah..."
                            class="h-9 text-sm rounded-lg"
                            @keyup.enter="confirmAddQty"
                            autofocus
                        />
                        <p v-if="qtyValidationError" class="text-xs text-destructive font-medium">
                            {{ qtyValidationError }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" class="rounded-lg" @click="isQtyDialogOpen = false">Batal</Button>
                    <Button class="rounded-lg" @click="confirmAddQty">Tambahkan ke Keranjang</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ═══ ALERT DIALOG KONFIRMASI DISTRIBUSI ═══ -->
        <AlertDialog v-model:open="isSubmitDialogOpen">
            <AlertDialogContent class="sm:max-w-[420px] rounded-xl">
                <AlertDialogHeader>
                    <AlertDialogTitle>Proses Distribusi?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Anda akan memproses {{ cart.length }} item mutasi stok. Lanjutkan proses ini secara otomatis (FEFO)?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel class="rounded-lg" @click="isSubmitDialogOpen = false">Batal</AlertDialogCancel>
                    <AlertDialogAction 
                        class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg"
                        :disabled="isProcessingSubmit"
                        @click="confirmSubmitCart"
                    >
                        {{ isProcessingSubmit ? 'Memproses...' : 'Ya, Proses Sekarang' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
