<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import Swal from 'sweetalert2';

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
const isSearching = ref(false);

// Debounced Server-Side Live Search via Inertia Partial Reload
let searchTimer = null;
const performServerSearch = () => {
    isSearching.value = true;
    router.reload({
        data: {
            search: searchQuery.value || undefined,
            category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        },
        preserveState: true,
        preserveScroll: true,
        only: ['medicines', 'compoundedProducts', 'filters'],
        onFinish: () => {
            isSearching.value = false;
        },
    });
};

watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        performServerSearch();
    }, 300);
});

watch(selectedCategory, () => {
    performServerSearch();
});

const cart = ref([]);
const paymentMethod = ref('tunai');
const discountAmount = ref(0);
const paidAmount = ref(0);
const isPaymentModalOpen = ref(false);

// Helper to format currency
const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0);
};

const formatNumber = (num) => parseFloat(num || 0).toLocaleString('id-ID');

// Available categories with counts for filter pills (matching POS style in Image 2)
const categoryList = computed(() => {
    if (props.categories && props.categories.length > 0) {
        return props.categories.map(c => ({
            name: c.nama_kategori,
            count: props.medicines ? props.medicines.filter(m => (m.category?.nama_kategori || m.kategori) === c.nama_kategori).length : 0
        }));
    }
    if (!props.medicines) return [];
    const map = {};
    props.medicines.forEach(m => {
        const cat = m.category?.nama_kategori || m.kategori || 'Lainnya';
        map[cat] = (map[cat] || 0) + 1;
    });
    return Object.entries(map).map(([name, count]) => ({ name, count }));
});

// Filtered medicines (Instant local filter + Server-side debounce sync)
const filteredMedicines = computed(() => {
    if (!props.medicines) return [];
    return props.medicines.filter(m => {
        const query = searchQuery.value.toLowerCase().trim();
        const matchesSearch = !query || 
            (m.nama_obat && m.nama_obat.toLowerCase().includes(query)) ||
            (m.kode_obat && m.kode_obat.toLowerCase().includes(query)) ||
            (m.kategori && m.kategori.toLowerCase().includes(query));
        
        const cat = m.category?.nama_kategori || m.kategori || 'Lainnya';
        const matchesCategory = selectedCategory.value === 'all' || cat.toLowerCase() === selectedCategory.value.toLowerCase();

        return matchesSearch && matchesCategory;
    });
});

// Filtered compounded products
const filteredCompounded = computed(() => {
    if (!props.compoundedProducts) return [];
    return props.compoundedProducts.filter(p => {
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
        Swal.fire({
            title: 'Stok Kasir Kosong',
            text: `Stok ${item.nama_obat} di kasir Anda saat ini adalah 0. Silakan minta Admin melakukan distribusi stok terlebih dahulu.`,
            icon: 'warning',
            confirmButtonColor: '#2563EB'
        });
        return;
    }

    const existing = cart.value.find(c => c.id === item.id && c.item_type === itemType);
    if (existing) {
        if (existing.quantity >= maxStock) {
            Swal.fire({
                title: 'Batas Stok Kasir Tercapai',
                text: `Maksimal stok yang tersedia di etalase kasir Anda adalah ${maxStock} ${existing.satuan}.`,
                icon: 'info',
                confirmButtonColor: '#2563EB'
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
            subtotal: price
        });
    }
};

const updateQuantity = (index, delta) => {
    const item = cart.value[index];
    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
    } else if (newQty > item.maxStock) {
        Swal.fire('Stok Terbatas', `Maksimal kuantitas tersedia di kasir adalah ${item.maxStock} ${item.satuan}`, 'warning');
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
        Swal.fire('Stok Terbatas', `Maksimal kuantitas tersedia di kasir adalah ${item.maxStock} ${item.satuan}`, 'warning');
    }
    item.quantity = Math.max(1, qty);
    item.subtotal = item.quantity * item.unit_price;
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    if (cart.value.length === 0) return;
    Swal.fire({
        title: 'Kosongkan Keranjang?',
        text: 'Semua item yang telah dipilih akan dihapus dari pesanan ini.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Ya, Kosongkan',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            cart.value = [];
            discountAmount.value = 0;
            paidAmount.value = 0;
        }
    });
};

// --- PAYMENT MODAL & CHECKOUT ---
const openPaymentModal = () => {
    if (cart.value.length === 0) {
        Swal.fire('Keranjang Kosong', 'Pilih minimal 1 obat dari katalog terlebih dahulu.', 'warning');
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
    if (cart.value.length === 0) return;
    if (paymentMethod.value === 'tunai' && paidAmount.value < grandTotal.value) {
        Swal.fire('Uang Kurang', `Uang yang diterima kurang dari total tagihan (${formatRupiah(grandTotal.value)}).`, 'error');
        return;
    }

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
            isPaymentModalOpen.value = false;
            cart.value = [];
            paidAmount.value = 0;
            discountAmount.value = 0;
            Swal.fire({
                title: 'Transaksi Berhasil!',
                text: 'Pembayaran telah sukses diproses dan stok kasir telah diperbarui.',
                icon: 'success',
                confirmButtonColor: '#16A34A',
                confirmButtonText: 'Selesai'
            });
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

    <div class="h-[calc(100vh-85px)] flex flex-col lg:flex-row gap-4 overflow-hidden -mt-1">
            
            <!-- ========================================================= -->
            <!-- KOLOM KIRI: KATALOG PRODUK (CARD GRID MODERN POS)       -->
            <!-- ========================================================= -->
            <div class="flex-1 flex flex-col bg-card rounded-lg border border-border shadow-sm overflow-hidden">
                
                <!-- TOP BAR: SEARCH & TABS -->
                <div class="p-4 border-b border-border bg-card space-y-3 shrink-0">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        
                        <!-- TAB SWITCHER: OBAT JADI VS RACIKAN -->
                        <div class="flex p-1 bg-muted rounded-md border border-border w-full sm:w-auto">
                            <button 
                                @click="activeTab = 'obat_jadi'"
                                :class="activeTab === 'obat_jadi' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground font-medium'"
                                class="px-3 py-1.5 rounded-md text-xs transition-colors uppercase tracking-wider flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                <span>Obat Jadi ({{ filteredMedicines.length }})</span>
                            </button>
                            <button 
                                @click="activeTab = 'racikan'"
                                :class="activeTab === 'racikan' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground font-medium'"
                                class="px-3 py-1.5 rounded-md text-xs transition-colors uppercase tracking-wider flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                <span>Racikan ({{ filteredCompounded.length }})</span>
                            </button>
                        </div>

                        <!-- SEARCH BAR -->
                        <div class="relative w-full sm:w-80">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                                <svg v-if="!isSearching" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                <svg v-else class="w-4 h-4 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            </div>
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Cari obat di sini..." 
                                class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-8 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                            >
                            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground hover:text-foreground text-xs font-medium">
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- KATEGORI PILLS -->
                    <div v-if="activeTab === 'obat_jadi' && categoryList.length > 0" class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                        <button 
                            @click="selectedCategory = 'all'"
                            :class="selectedCategory === 'all' ? 'bg-emerald-600 text-white font-medium shadow-xs' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground border border-border'"
                            class="px-2.5 py-1 rounded-md whitespace-nowrap transition-colors text-xs flex items-center gap-1"
                        >
                            <svg v-if="selectedCategory === 'all'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            <span>Semua ({{ props.medicines?.length || 0 }})</span>
                        </button>
                        <button 
                            v-for="cat in categoryList" 
                            :key="cat.name"
                            @click="selectedCategory = cat.name"
                            :class="selectedCategory === cat.name ? 'bg-emerald-600 text-white font-medium shadow-xs' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground border border-border'"
                            class="px-2.5 py-1 rounded-md whitespace-nowrap transition-colors text-xs flex items-center gap-1"
                        >
                            <svg v-if="selectedCategory === cat.name" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            <span>{{ cat.name }} ({{ cat.count }})</span>
                        </button>
                    </div>
                </div>

                <!-- GRID CONTENT (SCROLLABLE CATALOG) -->
                <div class="flex-1 overflow-y-auto p-4 bg-muted/20">
                    
                    <!-- TAB 1: OBAT JADI -->
                    <div v-if="activeTab === 'obat_jadi'">
                        
                        <!-- EMPTY STATE -->
                        <div v-if="filteredMedicines.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-12 h-12 bg-muted text-muted-foreground rounded-lg flex items-center justify-center text-xl mb-3 border border-border">
                                💊
                            </div>
                            <h4 class="font-semibold text-foreground text-sm">Tidak Ada Obat Ditemukan</h4>
                            <p class="text-xs text-muted-foreground max-w-sm mt-1">
                                {{ searchQuery ? 'Tidak ada obat yang sesuai dengan kata kunci pencarian.' : 'Belum ada obat yang didistribusikan ke kasir ini.' }}
                            </p>
                        </div>

                        <!-- PRODUCT CARDS GRID -->
                        <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                            <div 
                                v-for="obat in filteredMedicines" 
                                :key="obat.id"
                                @click="addToCart(obat, 'obat_jadi')"
                                :class="[
                                    (obat.stok_kasir || 0) <= 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-input active:scale-[0.99]',
                                    getCartItemQty(obat.id, 'obat_jadi') > 0 ? 'border-emerald-600 ring-1 ring-emerald-600 bg-emerald-50/20' : 'border-border bg-card'
                                ]"
                                class="rounded-lg border p-3 flex flex-col transition-all duration-150 select-none relative group shadow-xs"
                                title="Klik kartu untuk menambahkan ke keranjang"
                            >
                                <!-- BADGE STOK & SATUAN DI ATAS GAMBAR -->
                                <div class="flex items-center justify-between gap-1 mb-2">
                                    <span 
                                        class="px-2 py-0.5 rounded-md text-xs font-medium flex items-center gap-1"
                                        :class="(obat.stok_kasir || 0) > 5 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (obat.stok_kasir || 0) > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="(obat.stok_kasir || 0) > 5 ? 'bg-emerald-500' : (obat.stok_kasir || 0) > 0 ? 'bg-amber-500' : 'bg-rose-500'"></span>
                                        Stok: {{ formatNumber(obat.stok_kasir) }}
                                    </span>

                                    <span class="px-2 py-0.5 bg-muted text-muted-foreground rounded-md text-xs font-medium border border-border">
                                        {{ obat.satuan || obat.satuan_terkecil || obat.satuan_stok || 'Pcs' }}
                                    </span>
                                </div>

                                <!-- PRODUCT IMAGE -->
                                <div class="h-28 w-full rounded-md bg-muted/40 border border-border flex items-center justify-center p-2 mb-2 overflow-hidden group-hover:bg-muted/70 transition-colors">
                                    <img 
                                        v-if="obat.foto_obat" 
                                        :src="`/storage/medicines/${obat.foto_obat}`" 
                                        :alt="obat.nama_obat"
                                        class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-200" 
                                    />
                                    <div v-else class="flex flex-col items-center justify-center text-muted-foreground">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    </div>
                                </div>

                                <!-- PRODUCT INFO & PRICE -->
                                <div class="flex-1 flex flex-col justify-between">
                                    <div>
                                        <span class="text-xs text-muted-foreground block mb-0.5">
                                            {{ obat.category?.nama_kategori || obat.kategori || 'Obat' }}
                                        </span>
                                        <h4 class="font-semibold text-foreground text-xs leading-snug line-clamp-2" :title="obat.nama_obat">
                                            {{ obat.nama_obat }}
                                        </h4>
                                    </div>

                                    <div class="mt-2 pt-2 border-t border-border flex items-center justify-between">
                                        <div>
                                            <p class="text-xs text-muted-foreground leading-none">Harga</p>
                                            <p class="text-sm font-bold text-foreground mt-0.5 leading-none">
                                                {{ formatRupiah(obat.selling_price) }}
                                            </p>
                                        </div>

                                        <div v-if="getCartItemQty(obat.id, 'obat_jadi') > 0" class="flex items-center gap-1 bg-emerald-600 text-white px-2 py-0.5 rounded-md text-xs font-semibold">
                                            <span>✓</span>
                                            <span>{{ getCartItemQty(obat.id, 'obat_jadi') }}x</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PRODUK RACIKAN -->
                    <div v-if="activeTab === 'racikan'">
                        <div v-if="filteredCompounded.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-12 h-12 bg-muted text-muted-foreground rounded-lg flex items-center justify-center text-xl mb-3 border border-border">
                                🧪
                            </div>
                            <h4 class="font-semibold text-foreground text-sm">Belum Ada Produk Racikan</h4>
                            <p class="text-xs text-muted-foreground max-w-sm mt-1">Tidak ada produk racikan apoteker aktif yang siap dijual.</p>
                        </div>

                        <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                            <div 
                                v-for="racikan in filteredCompounded" 
                                :key="racikan.id"
                                @click="addToCart(racikan, 'racikan')"
                                :class="getCartItemQty(racikan.id, 'racikan') > 0 ? 'border-emerald-600 ring-1 ring-emerald-600 bg-emerald-50/20' : 'border-border bg-card'"
                                class="rounded-lg border p-3 flex flex-col cursor-pointer hover:border-input active:scale-[0.99] transition-all duration-150 select-none group shadow-xs"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="px-2 py-0.5 bg-muted text-muted-foreground border border-border rounded-md text-xs font-medium tracking-wide">
                                        Batch: {{ racikan.batch_no }}
                                    </span>
                                </div>

                                <div class="h-24 w-full rounded-md bg-muted/40 border border-border flex items-center justify-center mb-2 text-muted-foreground">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                </div>

                                <div class="flex-1 flex flex-col justify-between">
                                    <h4 class="font-semibold text-foreground text-xs leading-snug line-clamp-2">
                                        {{ racikan.nama_produk }}
                                    </h4>

                                    <div class="mt-2 pt-2 border-t border-border flex items-center justify-between">
                                        <div>
                                            <p class="text-xs text-muted-foreground leading-none">Harga Jual</p>
                                            <p class="text-sm font-bold text-foreground mt-0.5 leading-none">
                                                {{ formatRupiah(racikan.harga_jual) }}
                                            </p>
                                        </div>

                                        <div v-if="getCartItemQty(racikan.id, 'racikan') > 0" class="flex items-center gap-1 bg-emerald-600 text-white px-2 py-0.5 rounded-md text-xs font-semibold">
                                            <span>✓</span>
                                            <span>{{ getCartItemQty(racikan.id, 'racikan') }}x</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========================================================= -->
            <!-- KOLOM KANAN: PANEL KERANJANG KASIR POS                     -->
            <!-- ========================================================= -->
            <div class="w-full lg:w-[360px] bg-card rounded-lg border border-border shadow-sm flex flex-col h-full overflow-hidden shrink-0">
                
                <!-- HEADER KERANJANG -->
                <div class="p-3.5 border-b border-border bg-card flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-semibold text-foreground">Kasir Apotek</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Penjualan Langsung / OTC</p>
                    </div>

                    <button 
                        v-if="cart.length > 0" 
                        @click="clearCart" 
                        class="text-xs font-medium text-destructive hover:text-destructive bg-destructive/10 hover:bg-destructive/20 px-2 py-1 rounded-md transition-colors flex items-center gap-1"
                        title="Kosongkan Pesanan"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        <span>Reset</span>
                    </button>
                </div>

                <!-- DAFTAR ITEM KERANJANG -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-muted/20">
                    
                    <!-- EMPTY STATE MINIMALIS -->
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center text-muted-foreground py-16 px-4">
                        <div class="w-12 h-12 rounded-md bg-muted flex items-center justify-center text-lg mb-2.5 border border-border">
                            🛒
                        </div>
                        <p class="text-xs font-semibold text-foreground">Keranjang Masih Kosong</p>
                        <p class="text-xs text-muted-foreground mt-1 max-w-[210px] leading-relaxed">
                            Klik pada kartu obat untuk menambahkan ke pesanan.
                        </p>
                    </div>

                    <!-- BARIS ITEM PESANAN -->
                    <div 
                        v-for="(item, index) in cart" 
                        :key="index"
                        class="bg-card p-2.5 rounded-md border border-border shadow-xs hover:border-input transition-colors"
                    >
                        <div class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <h5 class="font-semibold text-foreground text-xs leading-tight truncate">
                                    {{ item.quantity }}x {{ item.nama }}
                                </h5>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ formatRupiah(item.unit_price) }} / {{ item.satuan }}
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-foreground whitespace-nowrap">
                                {{ formatRupiah(item.subtotal) }}
                            </span>
                        </div>

                        <!-- KONTROL KUANTITAS & HAPUS -->
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-border">
                            <div class="flex items-center bg-muted rounded-md p-0.5 border border-border">
                                <button 
                                    type="button" 
                                    @click="updateQuantity(index, -1)" 
                                    class="w-5 h-5 rounded flex items-center justify-center text-foreground hover:bg-background font-medium text-xs transition-colors"
                                >
                                    -
                                </button>
                                <input 
                                    type="number" 
                                    :value="item.quantity" 
                                    @input="setQuantity(index, $event.target.value)" 
                                    min="1" 
                                    :max="item.maxStock" 
                                    class="w-8 text-center bg-transparent border-none p-0 text-xs font-semibold text-foreground focus:ring-0"
                                >
                                <button 
                                    type="button" 
                                    @click="updateQuantity(index, 1)" 
                                    :disabled="item.quantity >= item.maxStock"
                                    class="w-5 h-5 rounded flex items-center justify-center text-foreground hover:bg-background font-medium text-xs transition-colors disabled:opacity-30"
                                >
                                    +
                                </button>
                            </div>

                            <button 
                                @click="removeFromCart(index)" 
                                class="text-muted-foreground hover:text-destructive p-1 text-xs transition-colors"
                                title="Hapus dari keranjang"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- FOOTER TOTAL & TOMBOL BAYAR -->
                <div class="p-4 border-t border-border bg-card space-y-3 shrink-0">
                    
                    <!-- SUB-RINGKASAN HARGA -->
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between items-center text-muted-foreground">
                            <span>Subtotal</span>
                            <span class="font-medium text-foreground">{{ formatRupiah(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-muted-foreground">
                            <span>Diskon</span>
                            <div class="w-24">
                                <input 
                                    v-model.number="discountAmount" 
                                    type="number" 
                                    min="0" 
                                    placeholder="0" 
                                    class="w-full text-right h-6 px-1.5 border border-input rounded-md text-xs font-semibold text-destructive bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- TOTAL UTAMA -->
                    <div class="flex items-center justify-between pt-2 border-t border-border">
                        <span class="text-sm font-semibold text-foreground">Total</span>
                        <span class="text-lg font-bold text-foreground tracking-tight">{{ formatRupiah(grandTotal) }}</span>
                    </div>

                    <!-- TOMBOL AKSI UTAMA -->
                    <button 
                        @click="openPaymentModal" 
                        :disabled="cart.length === 0"
                        class="w-full h-10 bg-emerald-600 hover:bg-emerald-700 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed text-white font-medium rounded-md text-sm transition-colors shadow-sm flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>Bayar (F8)</span>
                        <span v-if="cart.length > 0" class="ml-1 px-1.5 py-0.5 bg-emerald-800 text-white text-xs rounded-md font-semibold">
                            {{ cart.length }}
                        </span>
                    </button>
                </div>

            </div>

        </div>

        <!-- ========================================================= -->
        <!-- MODAL PEMBAYARAN KASIR POS                                 -->
        <!-- ========================================================= -->
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
                        :disabled="paymentMethod === 'tunai' && paidAmount < grandTotal"
                        class="w-2/3 h-9 bg-emerald-600 hover:bg-emerald-700 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed text-white font-medium rounded-md text-xs uppercase tracking-wider shadow-sm transition-colors flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>Selesaikan Transaksi</span>
                    </button>
                </div>

            </div>
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
