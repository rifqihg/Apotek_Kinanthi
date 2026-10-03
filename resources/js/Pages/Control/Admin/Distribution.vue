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
    kasirs: Array,
    selectedKasirId: [String, Number],
    medicines: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const dropdownOpen = ref(false);
const isSearching = ref(false);

const form = reactive({
    kasir_id: props.selectedKasirId || '',
    search: props.filters?.search || '',
});

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        kasir_id: form.kasir_id || undefined,
        search: form.search || undefined,
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

watch(() => form.search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

// Watch kasir select
watch(() => form.kasir_id, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        cart.value = [];
        applyFilters();
    }
});

const clearSearch = () => {
    form.search = '';
    applyFilters();
};

const activeMedicines = computed(() => props.medicines || { data: [], links: [] });

// --- CART & DIALOG LOGIC ---
const cart = ref([]);

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
        // Update quantity
        cart.value[existingIndex].quantity = quantity;
    } else {
        // Check if there is an OPPOSITE action in cart and remove it (cannot transfer and return same item at once safely without confusion)
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
        kasir_id: form.kasir_id,
        items: cart.value.map(item => ({
            medicine_id: item.medicine_id,
            action: item.action,
            quantity: item.quantity
        }))
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = []; // empty cart on success
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

const formatNumber = (num) => parseFloat(num).toLocaleString('id-ID');

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

    <div class="h-[calc(100vh-65px)] flex flex-col bg-muted/20 overflow-hidden">
            <!-- Header Filter -->
            <div class="bg-card border-b border-border px-6 py-4 flex items-center justify-between shrink-0 shadow-sm z-10">
                <div class="flex items-center gap-4 w-full">
                    <div class="w-1/2">
                        <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Pilih Kasir Tujuan/Sumber</label>
                        <div class="relative">
                            <button type="button" @click="dropdownOpen = !dropdownOpen" @blur="setTimeout(() => dropdownOpen = false, 200)" class="h-10 w-full bg-background border border-input rounded-md px-3.5 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring cursor-pointer outline-none transition-all flex items-center justify-between gap-3 hover:bg-muted/50 shadow-2xs">
                                <span>{{ form.kasir_id ? kasirs.find(k => k.id === form.kasir_id)?.name : '-- Pilih User Kasir --' }}</span>
                                <svg class="w-4 h-4 text-muted-foreground transition-transform duration-200" :class="{'rotate-180': dropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div v-show="dropdownOpen" class="absolute left-0 top-full mt-1.5 w-full bg-popover border border-border rounded-md shadow-md overflow-hidden z-20 origin-top-left transition-all">
                                <div class="p-1 space-y-0.5 max-h-60 overflow-y-auto">
                                    <button type="button" @click="form.kasir_id = ''; dropdownOpen = false" class="w-full text-left px-3 py-2 text-xs font-medium rounded-sm uppercase tracking-wider flex items-center justify-between group transition-colors" :class="form.kasir_id === '' ? 'bg-accent text-accent-foreground font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                                        -- Pilih User Kasir --
                                    </button>
                                    <div class="h-px bg-border my-1"></div>
                                    <button type="button" v-for="kasir in kasirs" :key="kasir.id" @click="form.kasir_id = kasir.id; dropdownOpen = false" class="w-full text-left px-3 py-2 text-xs font-medium rounded-sm uppercase tracking-wider flex items-center justify-between group transition-colors" :class="form.kasir_id === kasir.id ? 'bg-accent text-accent-foreground font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                                        {{ kasir.name }}
                                        <div v-if="form.kasir_id === kasir.id" class="w-2 h-2 rounded-full bg-primary"></div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="w-1/2" v-if="form.kasir_id">
                        <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Cari Obat</label>
                        <div class="relative rounded-md shadow-2xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg v-if="!isSearching" class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <svg v-else class="animate-spin h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <input type="text" v-model="form.search" placeholder="Ketik nama, kode, nomor batch, atau lokasi rak..." class="h-10 pl-9 pr-8 block w-full rounded-md border border-input bg-background focus:ring-1 focus:ring-ring sm:text-sm font-medium">
                            <button 
                                v-if="form.search" 
                                type="button" 
                                @click="clearSearch" 
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1 rounded-full hover:bg-muted transition-colors cursor-pointer"
                                title="Hapus pencarian"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State Kasir Not Selected -->
            <div v-if="!form.kasir_id" class="flex-1 flex items-center justify-center p-6">
                <div class="text-center bg-card p-8 rounded-lg shadow-sm border border-border max-w-md">
                    <div class="bg-primary/10 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                    <h3 class="text-base font-semibold text-foreground mb-1.5">Pilih User Kasir</h3>
                    <p class="text-sm text-muted-foreground">Silakan pilih user Kasir terlebih dahulu di bagian atas untuk mulai mengelola dan memindahkan stok.</p>
                </div>
            </div>

            <!-- Split Layout (Catalog & Cart) -->
            <div v-else class="flex-1 flex overflow-hidden">
                
                <!-- Left Panel: Product Catalog Grid -->
                <div class="flex-1 overflow-y-auto p-6 bg-muted/10">
                    <div v-if="activeMedicines.data.length === 0" class="text-center py-20 text-muted-foreground text-sm">
                        Tidak ada obat yang ditemukan.
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        <div v-for="medicine in activeMedicines.data" :key="medicine.id" class="bg-card rounded-lg shadow-sm border border-border hover:shadow-md transition-shadow overflow-hidden flex flex-col">
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
                                        <span class="font-bold text-emerald-600">{{ formatNumber(medicine.stok_kasir) }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="grid grid-cols-2 border-t border-border divide-x divide-border">
                                <button @click="openQtyPrompt(medicine, 'transfer')" 
                                        :disabled="medicine.stok_gudang <= 0"
                                        class="py-2 text-center text-primary font-semibold bg-primary/5 hover:bg-primary/10 disabled:opacity-40 disabled:bg-muted disabled:text-muted-foreground transition-colors flex items-center justify-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    <span class="text-xs">Ke Kasir</span>
                                </button>
                                <button @click="openQtyPrompt(medicine, 'return')" 
                                        :disabled="medicine.stok_kasir <= 0"
                                        class="py-2 text-center text-destructive font-semibold bg-destructive/10 hover:bg-destructive/20 disabled:opacity-40 disabled:bg-muted disabled:text-muted-foreground transition-colors flex items-center justify-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                    <span class="text-xs">Ke Gudang</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="activeMedicines.links && activeMedicines.data.length > 0" class="mt-8 pb-8 flex justify-center">
                        <div class="flex gap-1 bg-card p-1 rounded-md shadow-2xs border border-border">
                            <template v-for="(link, idx) in activeMedicines.links" :key="idx">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-state 
                                    preserve-scroll 
                                    v-html="link.label"
                                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                                    :class="link.active ? 'bg-primary text-primary-foreground font-semibold' : 'text-foreground hover:bg-muted'"
                                />
                                <span 
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1.5 rounded-md text-sm font-medium text-muted-foreground opacity-50 cursor-not-allowed"
                                />
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Cart Sidebar -->
                <div class="w-[380px] bg-card border-l border-border shadow-md flex flex-col z-20 shrink-0">
                    <div class="p-4 border-b border-border bg-slate-900 text-white flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                            <h3 class="font-semibold text-base">Keranjang Distribusi</h3>
                        </div>
                        <span class="bg-emerald-600 text-white text-xs font-semibold px-2 py-0.5 rounded-md">{{ cart.length }}</span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-3 bg-muted/20">
                        <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground p-4">
                            <svg class="w-12 h-12 mb-3 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            <p class="text-sm font-medium text-foreground">Keranjang masih kosong.</p>
                            <p class="text-xs text-muted-foreground mt-1 text-center">Pilih obat dari katalog di sebelah kiri.</p>
                        </div>
                        
                        <div class="space-y-2.5" v-else>
                            <div v-for="(item, index) in cart" :key="index" 
                                 class="bg-card rounded-md border p-3 shadow-2xs relative group"
                                 :class="item.action === 'transfer' ? 'border-blue-200/80' : 'border-destructive/30'">
                                 
                                <button @click="removeFromCart(index)" class="absolute -top-1.5 -right-1.5 bg-card rounded-full border border-border shadow-xs p-1 text-muted-foreground hover:text-destructive hover:border-destructive transition-colors opacity-0 group-hover:opacity-100">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                                 
                                <div class="flex gap-2.5">
                                    <div class="w-9 h-9 rounded-md flex items-center justify-center flex-shrink-0" :class="item.action === 'transfer' ? 'bg-primary/10 text-primary' : 'bg-destructive/10 text-destructive'">
                                        <svg v-if="item.action === 'transfer'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h5 class="text-sm font-semibold text-foreground truncate">{{ item.nama_obat }}</h5>
                                        <p class="text-xs font-semibold tracking-wider uppercase mb-1" :class="item.action === 'transfer' ? 'text-primary' : 'text-destructive'">
                                            {{ item.action === 'transfer' ? 'Gudang ➝ Kasir' : 'Kasir ➝ Gudang' }}
                                        </p>
                                        
                                        <div class="flex items-center gap-2 mt-2">
                                            <input type="number" v-model.number="item.quantity" class="h-8 w-20 text-center rounded-md border border-input bg-background shadow-2xs focus:ring-1 focus:ring-ring text-sm font-bold" min="1" :max="item.maxQty">
                                            <span class="text-xs text-muted-foreground font-medium">{{ item.satuan_terkecil }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 border-t border-border bg-card">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-semibold text-muted-foreground">Total Item:</span>
                            <span class="text-xl font-semibold tracking-tight text-foreground">{{ cart.length }}</span>
                        </div>
                        <button @click="submitCart" 
                                :disabled="cart.length === 0"
                                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-md shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-[0.99] flex justify-center items-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Proses Semua Distribusi
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- HIDDEN FILE INPUT FOR PHOTO UPLOAD -->
        <input 
            type="file" 
            ref="photoFileInput" 
            class="hidden" 
            accept="image/*" 
            @change="handlePhotoUpload" 
        />

        <!-- MODAL INPUT KUANTITAS DISTRIBUSI -->
        <Dialog v-model:open="isQtyDialogOpen">
            <DialogContent class="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle>{{ qtyDialogAction === 'transfer' ? 'Transfer ke Kasir' : 'Return ke Gudang' }}</DialogTitle>
                    <DialogDescription>
                        Berapa banyak kuantitas <strong>{{ selectedMedicine?.nama_obat }}</strong> yang ingin dipindahkan?
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-4 py-2">
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
                            class="h-10 text-sm"
                            @keyup.enter="confirmAddQty"
                            autofocus
                        />
                        <p v-if="qtyValidationError" class="text-xs text-destructive font-medium">
                            {{ qtyValidationError }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="isQtyDialogOpen = false">Batal</Button>
                    <Button @click="confirmAddQty">Tambahkan ke Keranjang</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ALERT DIALOG KONFIRMASI DISTRIBUSI -->
        <AlertDialog v-model:open="isSubmitDialogOpen">
            <AlertDialogContent class="sm:max-w-[420px]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Proses Distribusi?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Anda akan memproses {{ cart.length }} item mutasi stok. Lanjutkan proses ini secara otomatis (FEFO)?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel @click="isSubmitDialogOpen = false">Batal</AlertDialogCancel>
                    <AlertDialogAction 
                        class="bg-emerald-600 hover:bg-emerald-700 text-white"
                        :disabled="isProcessingSubmit"
                        @click="confirmSubmitCart"
                    >
                        {{ isProcessingSubmit ? 'Memproses...' : 'Ya, Proses Sekarang' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

</template>
