<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    initialSupplierId: {
        type: [String, Number],
        default: '',
    },
    initialInvoices: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'select-invoice']);

const supplierFilter = ref('');
const searchQuery = ref('');
const invoices = ref(props.initialInvoices || []);
const isLoading = ref(false);

watch(() => props.initialInvoices, (val) => {
    if (val && val.length) {
        invoices.value = val;
    }
});

const fetchPastInvoices = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get(route('control.admin.incoming-stocks.past-invoices'), {
            params: {
                supplier_id: supplierFilter.value || undefined,
                search: searchQuery.value || undefined,
            },
        });
        if (res.data?.invoices) {
            const existingNos = new Set((invoices.value || []).map(i => i.faktur_no));
            const newItems = res.data.invoices.filter(i => !existingNos.has(i.faktur_no));
            invoices.value = [...invoices.value, ...newItems];
        }
    } catch (err) {
        console.error('Gagal mengambil riwayat faktur lampau:', err);
    } finally {
        isLoading.value = false;
    }
};

const handleSearchInput = useDebounceFn(() => {
    if (filteredPastInvoices.value.length === 0 && searchQuery.value.trim().length >= 2) {
        fetchPastInvoices();
    }
}, 300);

const filteredPastInvoices = computed(() => {
    let list = invoices.value || [];
    if (supplierFilter.value) {
        list = list.filter(inv => Number(inv.supplier_id) === Number(supplierFilter.value));
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(inv => 
            (inv.faktur_no || '').toLowerCase().includes(q) ||
            (inv.supplier_name || '').toLowerCase().includes(q) ||
            (inv.items || []).some(it => (it.medicine_name || '').toLowerCase().includes(q))
        );
    }
    return list;
});

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        supplierFilter.value = props.initialSupplierId ? String(props.initialSupplierId) : '';
        searchQuery.value = '';
        if (invoices.value.length === 0) {
            fetchPastInvoices();
        }
    }
});

const close = () => {
    emit('update:modelValue', false);
};

const selectInvoice = (inv) => {
    emit('select-invoice', inv);
    close();
};
</script>

<template>
<Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
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
                <button type="button" @click="close" class="p-1.5 text-muted-foreground hover:text-foreground rounded-md hover:bg-muted transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Toolbar Filter & Search -->
            <div class="p-3.5 sm:p-4 border-b border-border bg-card/60 flex flex-col sm:flex-row gap-2.5 shrink-0">
                <div class="w-full sm:w-1/3">
                    <select v-model="supplierFilter" class="w-full h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors">
                        <option value="">Semua Supplier</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.nama_pbf }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-2/3 relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-model="searchQuery" 
                        @input="handleSearchInput" 
                        type="text" 
                        placeholder="Cari nomor faktur atau nama obat..." 
                        class="w-full h-9 pl-9 pr-8 bg-background border border-input rounded-md text-xs font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors" 
                    />
                    <button 
                        v-if="searchQuery" 
                        type="button" 
                        @click="searchQuery = ''" 
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <!-- List Body -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                <div v-if="isLoading" class="py-12 text-center text-muted-foreground text-xs flex flex-col items-center justify-center gap-2">
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
                        @click="selectInvoice(inv)" 
                        class="h-8 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-md transition-all shrink-0 flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer"
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
                    @click="close" 
                    class="h-8 px-4 rounded-md text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</Teleport>
</template>
