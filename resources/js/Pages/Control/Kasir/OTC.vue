<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { toast } from '@/Components/ui/toast';
import { useSmartSync } from '@/Composables/useSmartSync';
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
    medicines: Array,
    compoundedProducts: Array,
    categories: Array,
    filters: Object,
});

const activeTab = ref('obat_jadi'); // 'obat_jadi' atau 'racikan'
const searchQuery = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || 'all');

// In-memory local data
const localMedicines = ref(props.medicines || []);
const localCompounded = ref(props.compoundedProducts || []);
const sortBy = ref('name'); // 'name' (A-Z), 'stock_desc', 'price_asc'

watch(() => props.medicines, (newMeds) => {
    if (newMeds) localMedicines.value = newMeds;
});

watch(() => props.compoundedProducts, (newComp) => {
    if (newComp) localCompounded.value = newComp;
});

// BPOM Classification Config for Filter Pills
const classificationConfig = {
    'all': { label: 'Semua', isSpecial: false },
    'bebas': { label: 'Obat Bebas', dotColor: 'bg-emerald-500', isSpecial: false },
    'bebas_terbatas': { label: 'Bebas Terbatas', dotColor: 'bg-blue-500', isSpecial: false },
    'obat_keras': { label: 'Obat Keras (K)', dotColor: 'bg-rose-500', isSpecial: true },
    'prekursor': { label: 'Prekursor', dotColor: 'bg-amber-500', isSpecial: false },
    'psikotropika': { label: 'Psikotropika', dotColor: 'bg-purple-600', isSpecial: true },
    'narkotika': { label: 'Narkotika', dotColor: 'bg-red-600', isSpecial: true },
};

const classificationOrder = ['bebas', 'bebas_terbatas', 'obat_keras', 'prekursor', 'psikotropika', 'narkotika'];

// Filtered medicines matching search query directly in-memory (0ms)
const searchMatchingMedicines = computed(() => {
    const list = localMedicines.value || [];
    const query = (searchQuery.value || '').toLowerCase().trim();
    if (!query) return list;
    return list.filter(m => 
        (m.nama_obat && m.nama_obat.toLowerCase().includes(query)) ||
        (m.kode_obat && m.kode_obat.toLowerCase().includes(query))
    );
});

// If the active filter has 0 items after search, auto reset to 'all' so results are immediately visible
watch(searchQuery, () => {
    if (selectedCategory.value !== 'all') {
        const hasMatch = searchMatchingMedicines.value.some(
            m => (m.classification || 'bebas').toLowerCase() === selectedCategory.value.toLowerCase()
        );
        if (!hasMatch) {
            selectedCategory.value = 'all';
        }
    }
});

// Filter tabs dynamically synced with search results (0ms instant reactivity)
const bpomFilterTabs = computed(() => {
    const list = searchMatchingMedicines.value;
    const tabs = [
        { key: 'all', label: 'Semua', count: list.length, isSpecial: false }
    ];

    const counts = {};
    list.forEach(m => {
        const cls = m.classification || 'bebas';
        counts[cls] = (counts[cls] || 0) + 1;
    });

    // Add ordered known categories first if present
    classificationOrder.forEach(key => {
        if (counts[key] !== undefined) {
            const cfg = classificationConfig[key];
            tabs.push({
                key,
                label: cfg.label,
                count: counts[key],
                dotColor: cfg.dotColor || 'bg-slate-400',
                isSpecial: cfg.isSpecial || false,
            });
            delete counts[key];
        }
    });

    // Add any remaining categories
    Object.entries(counts).forEach(([key, count]) => {
        tabs.push({
            key,
            label: key.replace('_', ' ').toUpperCase(),
            count,
            dotColor: 'bg-slate-400',
            isSpecial: false,
        });
    });

    return tabs;
});

const toggleSort = () => {
    if (sortBy.value === 'name') sortBy.value = 'stock_desc';
    else if (sortBy.value === 'stock_desc') sortBy.value = 'price_asc';
    else sortBy.value = 'name';
};

// Final displayed medicines: search-matched + category-filtered + sorted (0ms instant)
const filteredMedicines = computed(() => {
    const list = [...searchMatchingMedicines.value];
    const filtered = list.filter(m => {
        const cls = m.classification || 'bebas';
        return selectedCategory.value === 'all' || cls.toLowerCase() === selectedCategory.value.toLowerCase();
    });

    if (sortBy.value === 'stock_desc') {
        return filtered.sort((a, b) => (b.stok_kasir || 0) - (a.stok_kasir || 0));
    }
    if (sortBy.value === 'price_asc') {
        return filtered.sort((a, b) => (a.selling_price || 0) - (b.selling_price || 0));
    }
    // Default: Alphabetical (A-Z) by medicine name
    return filtered.sort((a, b) => (a.nama_obat || '').localeCompare(b.nama_obat || ''));
});

const cart = ref([]);
const paymentMethod = ref('tunai');
const discountAmount = ref(0);
const paidAmount = ref(0);
const isPaymentModalOpen = ref(false);
const isProcessingPayment = ref(false);
const isClearCartDialogOpen = ref(false);
const isSyncing = ref(false);

const handleManualSync = () => {
    if (isSyncing.value) return;
    isSyncing.value = true;
    router.reload({
        only: ['medicines', 'compoundedProducts'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isSyncing.value = false;
            toast.success('Stok Diperbarui', {
                description: 'Katalog stok kasir telah diperbarui dengan data server terkini.',
                duration: 2000
            });
        }
    });
};

// Sinkronisasi otomatis aman: hanya aktif saat kasir standby (keranjang kosong & tidak sedang membuka modal pembayaran)
useSmartSync({
    only: ['medicines', 'compoundedProducts'],
    interval: 0, // Murni revalidasi saat kasir kembali ke tab, zero polling berulang
    throttle: 4000,
    enabled: () => cart.value.length === 0 && !isPaymentModalOpen.value && !isProcessingPayment.value
});

const page = usePage();

// Watch for flash error messages from server
watch(() => page.props.flash?.error, (errMsg) => {
    if (errMsg) {
        isProcessingPayment.value = false;
    }
});

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0);
};

const formatNumber = (num) => parseFloat(num || 0).toLocaleString('id-ID');

const setDiscountPercent = (pct) => {
    if (subtotal.value > 0) {
        discountAmount.value = Math.round((subtotal.value * pct) / 100);
    }
};

// Filtered compounded products
const filteredCompounded = computed(() => {
    const list = localCompounded.value || [];
    return list.filter(p => {
        const query = searchQuery.value.toLowerCase().trim();
        return !query || 
            (p.nama_produk && p.nama_produk.toLowerCase().includes(query)) ||
            (p.batch_no && p.batch_no.toLowerCase().includes(query));
    });
});

// --- CART COMPUTATIONS ---
const subtotal = computed(() => cart.value.reduce((sum, item) => sum + item.subtotal, 0));
const finalDiscount = computed(() => {
    const val = Number(discountAmount.value) || 0;
    return val > subtotal.value ? subtotal.value : val;
});
const grandTotal = computed(() => {
    const total = subtotal.value - finalDiscount.value;
    return total > 0 ? total : 0;
});
const kembalian = computed(() => Math.max(0, paidAmount.value - grandTotal.value));

const getCartItemQty = (id, itemType) => {
    const item = cart.value.find(c => c.id === id && c.item_type === itemType);
    return item ? item.quantity : 0;
};

// --- ADD TO CART (CARD CLICK) ---
const addToCart = (item, itemType) => {
    const maxStock = itemType === 'obat_jadi' ? (item.stok_kasir || 0) : 9999;
    
    if (itemType === 'obat_jadi' && maxStock <= 0) {
        toast.warning('Stok Kasir Kosong', {
            description: `Stok ${item.nama_obat} di kasir Anda saat ini adalah 0. Silakan minta Admin melakukan distribusi stok terlebih dahulu.`
        });
        return;
    }

    const existing = cart.value.find(c => c.id === item.id && c.item_type === itemType);
    if (existing) {
        if (existing.quantity >= maxStock) {
            toast.info('Batas Stok Kasir Tercapai', {
                description: `Maksimal stok yang tersedia di etalase kasir Anda adalah ${maxStock} ${existing.satuan}.`
            });
            return;
        }
        existing.quantity++;
        existing.subtotal = existing.quantity * existing.unit_price;
    } else {
        const unit = itemType === 'obat_jadi' ? (item.satuan || item.satuan_terkecil || item.satuan_stok || 'Pcs') : 'Paket';
        const price = Number(itemType === 'obat_jadi' ? item.selling_price : item.harga_jual) || 0;
        
        cart.value.push({
            id: item.id,
            item_type: itemType,
            nama: itemType === 'obat_jadi' ? item.nama_obat : item.nama_produk,
            satuan: unit,
            unit_price: price,
            quantity: 1,
            maxStock: maxStock,
            subtotal: price,
            foto_obat: item.foto_obat || null
        });
    }
};

const updateQuantity = (index, delta) => {
    const item = cart.value[index];
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
    } else if (newQty > item.maxStock) {
        toast.warning('Stok Terbatas', {
            description: `Maksimal kuantitas tersedia di kasir adalah ${item.maxStock} ${item.satuan}`
        });
    } else {
        item.quantity = newQty;
        item.subtotal = item.quantity * item.unit_price;
    }
};

const setQuantity = (index, val) => {
    const item = cart.value[index];
    let qty = parseInt(val) || 1;
    if (qty > item.maxStock) {
        qty = item.maxStock;
        toast.warning('Stok Terbatas', {
            description: `Maksimal kuantitas tersedia di kasir adalah ${item.maxStock} ${item.satuan}`
        });
    }
    item.quantity = Math.max(1, qty);
    item.subtotal = item.quantity * item.unit_price;
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    if (cart.value.length === 0) return;
    isClearCartDialogOpen.value = true;
};

const confirmClearCart = () => {
    cart.value = [];
    discountAmount.value = 0;
    paidAmount.value = 0;
    isClearCartDialogOpen.value = false;
};

// --- PAYMENT MODAL & CHECKOUT ---
const openPaymentModal = () => {
    if (cart.value.length === 0) {
        toast.warning('Keranjang Kosong', {
            description: 'Pilih minimal 1 obat dari katalog terlebih dahulu.'
        });
        return;
    }
    // Default paid amount to exact grandTotal
    paidAmount.value = grandTotal.value;
    isPaymentModalOpen.value = true;
};

const closePaymentModal = () => {
    isPaymentModalOpen.value = false;
};

const setQuickCash = (amount) => {
    if (amount === 'pas') {
        paidAmount.value = grandTotal.value;
    } else {
        paidAmount.value = amount;
    }
};

const submitPayment = () => {
    if (cart.value.length === 0 || isProcessingPayment.value) return;
    if (paymentMethod.value === 'tunai' && paidAmount.value < grandTotal.value) {
        toast.error('Uang Kurang', {
            description: `Uang yang diterima kurang dari total tagihan (${formatRupiah(grandTotal.value)}).`
        });
        return;
    }

    isProcessingPayment.value = true;

    router.post(route('control.kasir.otc.store'), {
        payment_method: paymentMethod.value,
        discount_amount: finalDiscount.value,
        paid_amount: paymentMethod.value === 'tunai' ? paidAmount.value : grandTotal.value,
        items: cart.value.map(item => ({
            item_type: item.item_type,
            id: item.id,
            quantity: item.quantity,
            unit_price: item.unit_price
        }))
    }, {
        onSuccess: () => {
            isProcessingPayment.value = false;
            isPaymentModalOpen.value = false;
            cart.value = [];
            paidAmount.value = 0;
            discountAmount.value = 0;
        },
        onError: (errors) => {
            isProcessingPayment.value = false;
            const msg = errors.error || errors.message || Object.values(errors)[0] || 'Terjadi kesalahan saat memproses transaksi OTC.';
            toast.error('Transaksi Ditolak', { description: msg });
        },
        onFinish: () => {
            isProcessingPayment.value = false;
        }
    });
};

// Keyboard shortcut: F8 to pay, Esc to close modal
const handleKeyDown = (e) => {
    if (e.key === 'F8') {
        e.preventDefault();
        if (!isPaymentModalOpen.value && cart.value.length > 0) {
            openPaymentModal();
        }
    } else if (e.key === 'Escape') {
        if (isPaymentModalOpen.value) {
            closePaymentModal();
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <Head title="Kasir POS - Penjualan OTC" />

    <div class="space-y-4">
        <!-- HEADER SECTION: BENTO COCKPIT (CLEAN ENTERPRISE DENSITY) -->
        <div class="p-3 sm:p-3.5 rounded-xl border border-slate-200/80 dark:border-border bg-white dark:bg-card shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Left Greeting -->
            <div>
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-foreground">
                    Kasir POS &amp; Transaksi Bebas
                </h1>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Layanan Penjualan Bebas (OTC) &amp; Peracikan Obat
                </p>
            </div>

            <!-- Right Search Bar & Quick Filter -->
            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <div class="relative w-full sm:w-80">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari obat atau scan barcode..." 
                        class="w-full h-9 pl-9 pr-8 bg-slate-50 dark:bg-muted/40 border border-slate-200 dark:border-border rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none transition-all shadow-2xs" 
                    />
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                        <button v-if="searchQuery" type="button" @click="searchQuery = ''" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <button 
                    type="button" 
                    @click="toggleSort"
                    class="h-9 px-3 rounded-lg border border-slate-200 dark:border-border bg-slate-50 dark:bg-muted/50 flex items-center gap-1.5 text-slate-700 dark:text-muted-foreground hover:bg-slate-100 dark:hover:bg-muted transition-colors cursor-pointer shadow-2xs text-xs font-semibold shrink-0"
                    :title="`Urutkan: ${sortBy === 'name' ? 'Nama (A-Z)' : (sortBy === 'stock_desc' ? 'Stok Terbanyak' : 'Harga Termurah')}`"
                >
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <span class="text-[11px] font-medium">
                        {{ sortBy === 'name' ? 'A-Z' : (sortBy === 'stock_desc' ? 'Stok' : 'Harga') }}
                    </span>
                </button>

                <!-- Tombol Segarkan Stok Etalase Kasir Cepat (1-Klik) -->
                <button 
                    type="button" 
                    @click="handleManualSync"
                    :disabled="isSyncing"
                    class="h-9 px-2.5 rounded-lg border border-slate-200 dark:border-border bg-slate-50 dark:bg-muted/50 flex items-center gap-1.5 text-slate-700 dark:text-muted-foreground hover:bg-slate-100 dark:hover:bg-muted transition-colors cursor-pointer shadow-2xs text-xs font-semibold shrink-0"
                    title="Segarkan data stok etalase kasir dari server"
                >
                    <svg class="w-3.5 h-3.5 text-slate-500" :class="{ 'animate-spin text-primary': isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="text-[11px] font-medium hidden sm:inline">
                        Segarkan
                    </span>
                </button>
            </div>
        </div>

        <div class="h-[calc(100vh-215px)] min-h-[560px] flex flex-col lg:flex-row gap-3.5 overflow-hidden">
            
            <!-- ========================================================= -->
            <!-- KOLOM KIRI: KATALOG PRODUK (CARD GRID MODERN POS)       -->
            <!-- ========================================================= -->
            <div class="flex-1 flex flex-col bg-white dark:bg-card rounded-xl border border-slate-200/80 dark:border-border shadow-2xs overflow-hidden">
                
                <!-- TOP BAR: TABS & CATEGORIES -->
                <div class="p-2.5 sm:p-3 border-b border-slate-200/80 dark:border-border bg-slate-50/50 dark:bg-muted/20 space-y-2.5 shrink-0">
                    <!-- TAB SWITCHER: OBAT JADI VS RACIKAN (Rounded-lg Pill Toggle) -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="inline-flex p-1 bg-slate-100 dark:bg-muted rounded-lg border border-slate-200/60 dark:border-border w-full sm:w-auto">
                            <button 
                                @click="activeTab = 'obat_jadi'"
                                :class="activeTab === 'obat_jadi' ? 'bg-white dark:bg-card text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground font-medium'"
                                class="px-3.5 py-1.5 rounded-md text-xs transition-all flex items-center justify-center gap-2 flex-1 sm:flex-none cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <span>Obat Jadi</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[10px] font-bold" :class="activeTab === 'obat_jadi' ? 'bg-primary/10 text-primary' : 'bg-slate-200 text-slate-600 dark:bg-card dark:text-muted-foreground'">
                                    {{ filteredMedicines.length }}
                                </span>
                            </button>
                            <button 
                                @click="activeTab = 'racikan'"
                                :class="activeTab === 'racikan' ? 'bg-white dark:bg-card text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground font-medium'"
                                class="px-3.5 py-1.5 rounded-md text-xs transition-all flex items-center justify-center gap-2 flex-1 sm:flex-none cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 text-fuchsia-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <span>Produk Racikan</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[10px] font-bold" :class="activeTab === 'racikan' ? 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300' : 'bg-slate-200 text-slate-600 dark:bg-card dark:text-muted-foreground'">
                                    {{ filteredCompounded.length }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- KATEGORI PILLS: GOLONGAN OBAT BPOM (0ms INSTANT CLIENT CACHE) -->
                    <div v-if="activeTab === 'obat_jadi'" class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-xs no-scrollbar pl-0.5 pr-2 scroll-smooth">
                        <button 
                            v-for="tab in bpomFilterTabs" 
                            :key="tab.key"
                            type="button"
                            @click="selectedCategory = tab.key"
                            :class="[
                                selectedCategory === tab.key
                                    ? (tab.isSpecial 
                                        ? 'bg-rose-600 text-white shadow-xs font-semibold' 
                                        : 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs font-semibold')
                                    : (tab.isSpecial
                                        ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-900/50 font-medium'
                                        : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80 font-medium')
                            ]"
                            class="h-8 px-2.5 rounded-lg text-xs flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer transition-all"
                        >
                            <span v-if="tab.dotColor && tab.key !== 'all'" class="w-2 h-2 rounded-full shrink-0" :class="tab.dotColor"></span>
                            <span>{{ tab.label }}</span>
                            <span 
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                :class="selectedCategory === tab.key 
                                    ? 'bg-white/20 text-white dark:bg-slate-900/20 dark:text-slate-900' 
                                    : (tab.isSpecial ? 'bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground')"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- GRID CONTENT (SCROLLABLE CATALOG) -->
                <div class="flex-1 overflow-y-auto p-3.5 sm:p-4 bg-slate-50/40 dark:bg-muted/10 custom-scrollbar">
                    
                    <!-- TAB 1: OBAT JADI -->
                    <div v-if="activeTab === 'obat_jadi'">
                        
                        <!-- EMPTY STATE -->
                        <div v-if="filteredMedicines.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-12 h-12 rounded-xl bg-muted/60 flex items-center justify-center text-xl mb-3 border border-border">
                                💊
                            </div>
                            <h4 class="font-bold text-foreground text-sm">Tidak Ada Obat Ditemukan</h4>
                            <p class="text-xs text-muted-foreground max-w-sm mt-1">
                                {{ searchQuery ? 'Tidak ada obat yang sesuai dengan kata kunci pencarian.' : 'Belum ada obat yang didistribusikan ke kasir ini.' }}
                            </p>
                        </div>

                        <!-- PRODUCT CARDS GRID (RESPONSIVE AUTO-FILL: 4 KOLOM SAAT SIDEBAR BUKA, 5-6 KOLOM SAAT SIDEBAR TUTUP) -->
                        <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(170px,1fr))] gap-3 sm:gap-3.5">
                            <div 
                                v-for="obat in filteredMedicines" 
                                :key="obat.id"
                                @click="addToCart(obat, 'obat_jadi')"
                                :class="[
                                    (obat.stok_kasir || 0) <= 0 
                                        ? 'opacity-55 cursor-not-allowed bg-slate-50 dark:bg-card/50 border-slate-200 dark:border-border' 
                                        : 'cursor-pointer bg-card border-border hover:border-primary/50 hover:shadow-md active:scale-[0.99]',
                                    getCartItemQty(obat.id, 'obat_jadi') > 0 
                                        ? 'ring-2 ring-primary border-primary bg-primary/[0.02]' 
                                        : 'shadow-2xs'
                                ]"
                                class="rounded-lg border p-2.5 flex flex-col justify-between transition-all duration-200 select-none relative group"
                                title="Klik kartu untuk menambahkan ke keranjang"
                            >
                                <!-- PRODUCT IMAGE CONTAINER WITH FLOATING BADGES & ACTION -->
                                <div class="h-32 w-full rounded-md bg-slate-100/80 dark:bg-muted/40 border border-slate-200/50 dark:border-border flex items-center justify-center mb-2 overflow-hidden relative group-hover:bg-slate-100 transition-colors">
                                    <img 
                                        v-if="obat.foto_obat" 
                                        :src="`/storage/medicines/${obat.foto_obat}`" 
                                        :alt="obat.nama_obat"
                                        loading="lazy"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                                    />
                                    <div v-else class="flex flex-col items-center justify-center text-muted-foreground/60 p-2">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>

                                    <!-- FLOATING DOSIS & SATUAN BADGE (Top-Left Gambar) -->
                                    <span class="absolute top-2 left-2 bg-card/95 backdrop-blur-sm px-2 py-0.5 rounded-md text-[11px] font-semibold text-foreground border border-border shadow-2xs pointer-events-none">
                                        {{ obat.satuan || obat.satuan_terkecil || 'Pcs' }}
                                    </span>

                                    <!-- FLOATING CART ACTION / COUNTER BADGE (Top-Right Gambar) -->
                                    <div 
                                        class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 shadow-2xs z-10"
                                        :class="getCartItemQty(obat.id, 'obat_jadi') > 0
                                            ? 'bg-primary text-primary-foreground ring-2 ring-card font-bold text-xs'
                                            : 'bg-card/90 text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground border border-border'"
                                    >
                                        <span v-if="getCartItemQty(obat.id, 'obat_jadi') > 0">{{ getCartItemQty(obat.id, 'obat_jadi') }}</span>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    </div>

                                    <!-- FLOATING STOK KASIR (Bottom-Left Gambar) -->
                                    <div class="absolute left-2 bottom-2 z-10">
                                        <span 
                                            class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-tight shadow-xs backdrop-blur-sm flex items-center gap-1"
                                            :class="(obat.stok_kasir || 0) > 5 
                                                ? 'bg-emerald-600/90 text-white' 
                                                : ((obat.stok_kasir || 0) > 0 ? 'bg-amber-600/90 text-white' : 'bg-rose-600/90 text-white')"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                            <span>{{ (obat.stok_kasir || 0) > 0 ? `${formatNumber(obat.stok_kasir)} stok` : 'Habis' }}</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- PRODUCT INFO & PRICE -->
                                <div class="flex-1 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <div class="flex items-center gap-1 truncate">
                                                <span 
                                                    v-if="obat.classification === 'obat_keras'" 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-rose-600 text-white shrink-0 tracking-wider"
                                                >
                                                    KERAS (K)
                                                </span>
                                                <span 
                                                    v-else-if="obat.classification === 'psikotropika' || obat.classification === 'narkotika'" 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-purple-700 text-white shrink-0 tracking-wider shadow-2xs"
                                                >
                                                    SIPNAP
                                                </span>
                                                <span 
                                                    v-else-if="obat.classification === 'prekursor'" 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50 shrink-0"
                                                >
                                                    PREKURSOR
                                                </span>
                                                <span 
                                                    v-else-if="obat.classification === 'bebas_terbatas'" 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 shrink-0"
                                                >
                                                    BEBAS TERBATAS
                                                </span>
                                                <span 
                                                    v-else 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 shrink-0"
                                                >
                                                    BEBAS
                                                </span>
                                            </div>
                                            <span v-if="obat.kode_obat" class="text-[10px] font-mono text-muted-foreground/70 shrink-0">
                                                {{ obat.kode_obat }}
                                            </span>
                                        </div>
                                        <h4 class="font-bold text-foreground text-xs sm:text-sm leading-snug line-clamp-2 group-hover:text-primary transition-colors" :title="obat.nama_obat">
                                            {{ obat.nama_obat }}
                                        </h4>
                                    </div>

                                    <div class="mt-2.5 pt-2 border-t border-border flex items-baseline justify-between">
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-sm sm:text-base font-extrabold text-foreground tracking-tight">
                                                {{ formatRupiah(obat.selling_price) }}
                                            </span>
                                            <span class="text-[11px] font-medium text-muted-foreground">
                                                / {{ obat.satuan || 'Pcs' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PRODUK RACIKAN -->
                    <div v-if="activeTab === 'racikan'">
                        <div v-if="filteredCompounded.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-12 h-12 rounded-xl bg-muted/60 flex items-center justify-center text-xl mb-3 border border-border">
                                🧪
                            </div>
                            <h4 class="font-bold text-foreground text-sm">Belum Ada Produk Racikan</h4>
                            <p class="text-xs text-muted-foreground max-w-sm mt-1">Tidak ada produk racikan apoteker aktif yang siap dijual.</p>
                        </div>

                        <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(170px,1fr))] gap-3 sm:gap-3.5">
                            <div 
                                v-for="racikan in filteredCompounded" 
                                :key="racikan.id"
                                @click="addToCart(racikan, 'racikan')"
                                :class="[
                                    'cursor-pointer bg-card border-border hover:border-primary/50 hover:shadow-md active:scale-[0.99]',
                                    getCartItemQty(racikan.id, 'racikan') > 0 
                                        ? 'ring-2 ring-primary border-primary bg-primary/[0.02]' 
                                        : 'shadow-2xs'
                                ]"
                                class="rounded-lg border p-2.5 flex flex-col justify-between transition-all duration-200 select-none group relative"
                                title="Klik kartu untuk menambahkan ke keranjang"
                            >
                                <!-- RACIKAN MEDIA CONTAINER -->
                                <div class="h-32 w-full rounded-md bg-slate-100/80 dark:bg-muted/40 border border-slate-200/50 dark:border-border flex items-center justify-center mb-2 relative overflow-hidden group-hover:bg-slate-100 transition-colors">
                                    <div class="flex flex-col items-center justify-center text-fuchsia-600/70 p-2">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>

                                    <!-- BATCH BADGE (Top-Left Gambar) -->
                                    <span class="absolute top-2 left-2 bg-card/95 backdrop-blur-sm px-2 py-0.5 rounded-md text-[10px] font-mono font-bold text-foreground border border-border shadow-2xs pointer-events-none">
                                        Batch: {{ racikan.batch_no }}
                                    </span>

                                    <!-- FLOATING CART ACTION / COUNTER BADGE (Top-Right Gambar) -->
                                    <div 
                                        class="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 shadow-2xs z-10"
                                        :class="getCartItemQty(racikan.id, 'racikan') > 0
                                            ? 'bg-primary text-primary-foreground ring-2 ring-card font-bold text-xs'
                                            : 'bg-card/90 text-muted-foreground group-hover:bg-primary group-hover:text-primary-foreground border border-border'"
                                    >
                                        <span v-if="getCartItemQty(racikan.id, 'racikan') > 0">{{ getCartItemQty(racikan.id, 'racikan') }}</span>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    </div>
                                </div>

                                <!-- RACIKAN INFO & PRICE -->
                                <div class="flex-1 flex flex-col justify-between">
                                    <div>
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-fuchsia-600 dark:text-fuchsia-400 block mb-1">
                                            Produk Racikan
                                        </span>
                                        <h4 class="font-bold text-foreground text-xs sm:text-sm leading-snug line-clamp-2 group-hover:text-primary transition-colors">
                                            {{ racikan.nama_produk }}
                                        </h4>
                                    </div>

                                    <div class="mt-2.5 pt-2 border-t border-border flex items-baseline justify-between">
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-sm sm:text-base font-extrabold text-foreground tracking-tight">
                                                {{ formatRupiah(racikan.harga_jual) }}
                                            </span>
                                            <span class="text-[11px] font-medium text-muted-foreground">
                                                / Paket
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========================================================= -->
            <!-- KOLOM KANAN: PANEL KERANJANG KASIR POS (LUXURY BENTO POS)   -->
            <!-- ========================================================= -->
            <div class="w-full lg:w-[350px] xl:w-[370px] bg-white dark:bg-card rounded-xl border border-slate-200/80 dark:border-border shadow-2xs flex flex-col h-full overflow-hidden shrink-0">
                
                <!-- HEADER KERANJANG -->
                <div class="p-3 sm:p-3.5 border-b border-slate-200/80 dark:border-border bg-slate-50/50 dark:bg-muted/20 flex items-center justify-between shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-semibold text-foreground">Kasir Apotek</span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Penjualan Langsung / OTC</p>
                    </div>

                    <button 
                        v-if="cart.length > 0" 
                        @click="clearCart" 
                        class="text-xs font-semibold text-destructive hover:text-destructive bg-destructive/10 hover:bg-destructive/20 px-2.5 py-1 rounded-md transition-colors flex items-center gap-1 cursor-pointer shadow-2xs"
                        title="Kosongkan Pesanan"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        <span>Reset</span>
                    </button>
                </div>

                <!-- DAFTAR ITEM KERANJANG (BENTO MICRO-CARDS) -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-slate-50/40 dark:bg-muted/10 custom-scrollbar">
                    
                    <!-- EMPTY STATE MINIMALIS -->
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center text-muted-foreground py-16 px-4">
                        <div class="w-12 h-12 rounded-xl bg-muted/60 flex items-center justify-center text-lg mb-2.5 border border-border">
                            🛒
                        </div>
                        <p class="text-xs font-semibold text-foreground">Keranjang Masih Kosong</p>
                        <p class="text-xs text-muted-foreground mt-1 max-w-[210px] leading-relaxed">
                            Klik pada kartu obat untuk menambahkan ke pesanan.
                        </p>
                    </div>

                    <!-- BARIS ITEM PESANAN (ELEGANT MICRO CARD) -->
                    <div 
                        v-for="(item, index) in cart" 
                        :key="index"
                        class="bg-white dark:bg-card p-3 rounded-xl border border-slate-200/80 dark:border-border shadow-2xs hover:border-slate-300 dark:hover:border-border/80 transition-all space-y-2"
                    >
                        <div class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <h5 class="font-bold text-foreground text-xs leading-snug line-clamp-2">
                                    {{ item.nama }}
                                </h5>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[11px] text-muted-foreground font-medium">
                                        {{ formatRupiah(item.unit_price) }} / {{ item.satuan }}
                                    </span>
                                </div>
                            </div>
                            <span class="text-xs sm:text-sm font-extrabold text-foreground tracking-tight whitespace-nowrap">
                                {{ formatRupiah(item.subtotal) }}
                            </span>
                        </div>

                        <!-- KONTROL KUANTITAS & HAPUS -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-border">
                            <div class="flex items-center bg-slate-100 dark:bg-muted rounded-lg p-0.5 border border-slate-200/60 dark:border-border">
                                <button 
                                    type="button" 
                                    @click="updateQuantity(index, -1)" 
                                    class="w-6 h-6 rounded-md flex items-center justify-center text-foreground hover:bg-white dark:hover:bg-card font-bold text-xs transition-colors cursor-pointer shadow-2xs"
                                >
                                    -
                                </button>
                                <input 
                                    type="number" 
                                    :value="item.quantity" 
                                    @input="setQuantity(index, $event.target.value)" 
                                    min="1" 
                                    :max="item.maxStock" 
                                    class="w-10 text-center bg-transparent border-none p-0 text-xs font-extrabold text-foreground focus:ring-0"
                                >
                                <button 
                                    type="button" 
                                    @click="updateQuantity(index, 1)" 
                                    :disabled="item.quantity >= item.maxStock"
                                    class="w-6 h-6 rounded-md flex items-center justify-center text-foreground hover:bg-white dark:hover:bg-card font-bold text-xs transition-colors disabled:opacity-30 cursor-pointer shadow-2xs"
                                >
                                    +
                                </button>
                            </div>

                            <button 
                                @click="removeFromCart(index)" 
                                class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 p-1.5 rounded-md text-xs transition-colors cursor-pointer"
                                title="Hapus dari keranjang"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- FOOTER TOTAL & TOMBOL BAYAR -->
                <div class="p-3.5 sm:p-4 border-t border-slate-200/80 dark:border-border bg-white dark:bg-card space-y-3 shrink-0">
                    
                    <!-- SUB-RINGKASAN HARGA -->
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center text-muted-foreground font-medium">
                            <span>Subtotal</span>
                            <span class="font-bold text-foreground text-sm">{{ formatRupiah(subtotal) }}</span>
                        </div>
                        
                        <!-- ELEGAN DISCOUNT ROW -->
                        <div class="bg-slate-50 dark:bg-muted/40 p-2.5 rounded-lg border border-slate-200/80 dark:border-border space-y-1.5">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-foreground">Diskon Transaksi</span>
                                    <span v-if="finalDiscount > 0" class="text-[10px] font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.2 rounded border border-rose-200/50">
                                        -{{ formatRupiah(finalDiscount) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button 
                                        type="button" 
                                        v-for="pct in [5, 10]" 
                                        :key="pct"
                                        @click="setDiscountPercent(pct)" 
                                        class="px-1.5 py-0.5 text-[10px] font-bold rounded border transition-colors cursor-pointer"
                                        :class="finalDiscount > 0 && finalDiscount === Math.round(subtotal * pct / 100) ? 'bg-primary text-white border-primary' : 'bg-card text-muted-foreground border-border hover:bg-muted hover:text-foreground'"
                                        :title="`Diskon ${pct}%`"
                                    >
                                        {{ pct }}%
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-[11px] font-bold text-muted-foreground pointer-events-none">Rp</span>
                                <input 
                                    v-model.number="discountAmount" 
                                    type="number" 
                                    min="0" 
                                    :max="subtotal"
                                    placeholder="0" 
                                    class="w-full text-right h-8 pl-8 pr-2.5 border border-slate-200 dark:border-border rounded-md text-xs font-bold text-rose-600 dark:text-rose-400 bg-white dark:bg-card focus:outline-none focus:ring-1 focus:ring-ring shadow-2xs"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- TOTAL UTAMA COCKPIT (LUXURY CONTRAST) -->
                    <div class="p-3 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 rounded-xl flex items-center justify-between shadow-xs">
                        <div>
                            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Total Tagihan</span>
                            <span class="text-xl sm:text-2xl font-black tracking-tight leading-none">
                                {{ formatRupiah(grandTotal) }}
                            </span>
                        </div>
                        <span class="text-xs font-bold px-2 py-1 rounded-md bg-white/10 dark:bg-slate-900/10 text-white dark:text-slate-900">
                            {{ cart.length }} Item
                        </span>
                    </div>

                    <!-- TOMBOL AKSI UTAMA -->
                    <button 
                        @click="openPaymentModal" 
                        :disabled="cart.length === 0"
                        class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99]"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>Bayar (F8)</span>
                    </button>
                </div>

            </div>

        </div>

        <!-- MODAL PEMBAYARAN KASIR POS -->
        <Teleport to="body">
            <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-xs p-4">
                <div class="bg-card w-full max-w-lg rounded-lg shadow-xl border border-border overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
                    
                    <!-- MODAL HEADER -->
                    <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
                        <div>
                            <h3 class="font-semibold text-foreground text-base">Pembayaran Transaksi OTC</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ cart.length }} jenis obat dalam pesanan</p>
                        </div>
                        <button @click="closePaymentModal" class="w-7 h-7 rounded-md bg-muted hover:bg-accent text-muted-foreground hover:text-foreground flex items-center justify-center text-xs font-medium transition-colors">
                            ✕
                        </button>
                    </div>

                    <!-- MODAL BODY -->
                    <div class="p-5 space-y-4">
                        
                        <!-- TOTAL TAGIHAN BESAR -->
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Total yang Harus Dibayar</p>
                                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mt-0.5">{{ formatRupiah(grandTotal) }}</p>
                            </div>
                            <span class="px-2.5 py-1 bg-background text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 rounded-md text-xs font-semibold">
                                {{ paymentMethod.toUpperCase() }}
                            </span>
                        </div>

                        <!-- PILIHAN METODE PEMBAYARAN -->
                        <div>
                            <label class="block text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">Pilih Metode Pembayaran</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'tunai'"
                                    :class="paymentMethod === 'tunai' ? 'bg-emerald-600 text-white border-emerald-600 font-semibold shadow-xs' : 'bg-background text-muted-foreground border-input hover:bg-muted'"
                                    class="h-9 rounded-md border text-xs font-medium flex items-center justify-center gap-1.5 transition-colors"
                                >
                                    <span>💵</span>
                                    <span>Tunai</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'qris'"
                                    :class="paymentMethod === 'qris' ? 'bg-emerald-600 text-white border-emerald-600 font-semibold shadow-xs' : 'bg-background text-muted-foreground border-input hover:bg-muted'"
                                    class="h-9 rounded-md border text-xs font-medium flex items-center justify-center gap-1.5 transition-colors"
                                >
                                    <span>📱</span>
                                    <span>QRIS</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="paymentMethod = 'transfer'"
                                    :class="paymentMethod === 'transfer' ? 'bg-emerald-600 text-white border-emerald-600 font-semibold shadow-xs' : 'bg-background text-muted-foreground border-input hover:bg-muted'"
                                    class="h-9 rounded-md border text-xs font-medium flex items-center justify-center gap-1.5 transition-colors"
                                >
                                    <span>🏦</span>
                                    <span>Transfer</span>
                                </button>
                            </div>
                        </div>

                        <!-- BAGIAN PEMBAYARAN TUNAI -->
                        <div v-if="paymentMethod === 'tunai'" class="space-y-3 pt-1">
                            
                            <!-- QUICK NOMINAL BUTTONS -->
                            <div>
                                <label class="block text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1.5">Pilihan Uang Cepat</label>
                                <div class="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                                    <button 
                                        type="button" 
                                        @click="setQuickCash('pas')" 
                                        class="px-2.5 py-1 bg-muted hover:bg-accent text-foreground border border-border text-xs font-medium rounded-md transition-colors"
                                    >
                                        Uang Pas
                                    </button>
                                    <button 
                                        v-for="nom in [10000, 20000, 50000, 100000]" 
                                        :key="nom"
                                        type="button" 
                                        @click="setQuickCash(nom)" 
                                        class="px-2.5 py-1 bg-muted hover:bg-accent text-foreground border border-border text-xs font-medium rounded-md transition-colors"
                                    >
                                        {{ formatNumber(nom) }}
                                    </button>
                                </div>
                            </div>

                            <!-- INPUT UANG DITERIMA -->
                            <div>
                                <label class="block text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1.5">Uang Diterima dari Pasien</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-muted-foreground font-semibold text-sm">Rp</span>
                                    <input 
                                        v-model.number="paidAmount" 
                                        type="number" 
                                        class="w-full h-10 pl-9 pr-3 bg-background border border-input rounded-md text-base font-bold text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" 
                                        placeholder="0"
                                        autofocus
                                        @keyup.enter="submitPayment"
                                    >
                                </div>
                            </div>

                            <!-- KEMBALIAN CARD -->
                            <div class="p-3 bg-muted/40 border border-border rounded-lg flex items-center justify-between">
                                <span class="text-xs font-medium text-muted-foreground">Kembalian</span>
                                <span class="text-lg font-bold" :class="kembalian > 0 ? 'text-emerald-600' : 'text-muted-foreground'">
                                    {{ formatRupiah(kembalian) }}
                                </span>
                            </div>
                        </div>

                        <!-- METODE NON-TUNAI (QRIS / TRANSFER) -->
                        <div v-else class="p-3 bg-muted/40 border border-border rounded-lg text-center space-y-1">
                            <p class="text-xs font-medium text-foreground">Pembayaran Non-Tunai Dipilih</p>
                            <p class="text-xs text-muted-foreground">Pastikan dana telah masuk ke rekening atau scan QRIS apotek sejumlah nominal tagihan.</p>
                        </div>

                    </div>

                    <!-- MODAL FOOTER ACTION -->
                    <div class="p-4 border-t border-border bg-muted/30 flex gap-2">
                        <button 
                            type="button" 
                            @click="closePaymentModal" 
                            class="w-1/3 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground font-medium rounded-md text-xs uppercase tracking-wider transition-colors"
                        >
                            Batal (Esc)
                        </button>
                        <button 
                            type="button" 
                            @click="submitPayment" 
                            :disabled="isProcessingPayment || (paymentMethod === 'tunai' && paidAmount < grandTotal)"
                            class="w-2/3 h-9 bg-emerald-600 hover:bg-emerald-700 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed text-white font-medium rounded-md text-xs uppercase tracking-wider shadow-sm transition-colors flex items-center justify-center gap-1.5"
                        >
                            <svg v-if="!isProcessingPayment" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ isProcessingPayment ? 'Memproses...' : 'Selesaikan Transaksi' }}</span>
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

        <!-- ALERT DIALOG KONFIRMASI KOSONGKAN KERANJANG -->
        <AlertDialog v-model:open="isClearCartDialogOpen">
            <AlertDialogContent class="sm:max-w-[420px]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Kosongkan Keranjang?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Semua item yang telah dipilih akan dihapus dari pesanan ini. Tindakan ini tidak dapat dibatalkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel @click="isClearCartDialogOpen = false">Batal</AlertDialogCancel>
                    <AlertDialogAction 
                        class="bg-destructive hover:bg-destructive/90 text-destructive-foreground"
                        @click="confirmClearCart"
                    >
                        Ya, Kosongkan
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
