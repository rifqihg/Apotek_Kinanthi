<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    returns: Object,
    activeIncomingStocks: Array,
});

const showModal = ref(false);

const form = useForm({
    incoming_stock_id: '',
    qty_retur_input: 1,
    satuan_level: 'terkecil',
    alasan_retur: '',
});

// Helper for UI
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
};
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

// State for filtering in modal
const selectedSupplierId = ref('');

// Get unique suppliers from active stocks
const availableSuppliers = computed(() => {
    const suppliers = [];
    const supplierIds = new Set();
    props.activeIncomingStocks.forEach(stock => {
        if (stock.supplier && !supplierIds.has(stock.supplier.id)) {
            supplierIds.add(stock.supplier.id);
            suppliers.push(stock.supplier);
        }
    });
    return suppliers;
});

// Get stocks for selected supplier
const availableStocks = computed(() => {
    if (!selectedSupplierId.value) return [];
    return props.activeIncomingStocks.filter(stock => stock.supplier_id === selectedSupplierId.value);
});

// Selected stock details
const selectedStockData = computed(() => {
    if (!form.incoming_stock_id) return null;
    return props.activeIncomingStocks.find(s => s.id === form.incoming_stock_id);
});

// Calculate multiplier based on selected unit
const currentMultiplier = computed(() => {
    if (!selectedStockData.value) return 1;
    const st = selectedStockData.value;
    if (form.satuan_level === 'besar') {
        if (st.mid_to_big_ratio > 0 && st.small_to_mid_ratio > 0) {
            return st.mid_to_big_ratio * st.small_to_mid_ratio;
        }
        return st.unit_conversion_factor || 1;
    } else if (form.satuan_level === 'menengah') {
        return st.small_to_mid_ratio || 1;
    }
    return 1;
});

// Converted total base quantity to return
const convertedQtyToReturn = computed(() => {
    return form.qty_retur_input * currentMultiplier.value;
});

// Get the actual unit name for UI string (DUS, STRIP, etc)
const getSelectedUnitName = () => {
    if (!selectedStockData.value) return '';
    const st = selectedStockData.value;
    if (form.satuan_level === 'besar') return st.satuan_input || 'Besar';
    if (form.satuan_level === 'menengah') return st.mid_unit || 'Menengah';
    return st.medicine?.satuan_stok || 'Terkecil';
};

// Maximum base returnable qty
const maxQty = computed(() => {
    if (!selectedStockData.value) return 0;
    return selectedStockData.value.qty_converted || 0;
});

// Maximum input returnable qty based on selected unit
const maxQtyInput = computed(() => {
    if (!selectedStockData.value) return 0;
    return Math.floor(maxQty.value / currentMultiplier.value);
});

// Submit form
const submitReturn = () => {
    if (convertedQtyToReturn.value > maxQty.value) {
        alert('Kuantitas melebihi batas stok tersedia!');
        return;
    }
    
    const reasonWithUnit = `[${form.qty_retur_input} ${getSelectedUnitName()}] ${form.alasan_retur}`;

    form.transform((data) => ({
        ...data,
        qty_retur: convertedQtyToReturn.value,
        alasan_retur: reasonWithUnit
    })).post(route('control.admin.returns.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            selectedSupplierId.value = '';
        },
    });
};
</script>

<template>
    <Head title="Retur Obat ke Supplier" />

    <ControlLayout>
        <template #header-title>Retur Obat</template>

        <div class="py-6 space-y-6 animate-in fade-in duration-700">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
                <!-- COMPACT PREMIUM HERO HEADER -->
                <div class="relative p-6 mb-6 bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50/50 rounded-full -mr-32 -mt-32 transition-transform group-hover:scale-110 duration-1000"></div>
                    
                    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 bg-blue-50 text-blue-700 text-[9px] font-bold uppercase tracking-widest rounded-full border border-blue-100">Inventory</span>
                                <span class="px-3 py-1 bg-slate-50 text-slate-500 text-[9px] font-bold uppercase tracking-widest rounded-full border border-[#E8EEF6]">Retur</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-none uppercase">Retur Obat</h1>
                                <p class="text-slate-500 font-medium mt-1.5 max-w-xl text-xs">Kelola pengembalian obat ke supplier</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-[16px] transition-all shadow-[0_4px_12px_rgba(37,99,235,0.2)] hover:shadow-[0_6px_16px_rgba(37,99,235,0.3)] hover:-translate-y-0.5">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Buat Retur
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLE: RIWAYAT RETUR -->
                <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-blue-50/30 border-b border-[#E8EEF6]">
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Tgl Retur</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Supplier</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Faktur & Obat</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center whitespace-nowrap">Ekuivalen Stok Dasar</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Alasan (Fisik Retur)</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EEF6]">
                            <tr v-if="!returns.data || returns.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-[16px] flex items-center justify-center text-2xl mb-4 grayscale">↩️</div>
                                        <p class="text-sm font-bold text-slate-600">Belum ada riwayat retur</p>
                                        <p class="text-xs text-slate-400 mt-1">Klik Buat Retur untuk mengembalikan obat.</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="item in returns.data" :key="item.id" class="group hover:bg-blue-50/10 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-slate-800">{{ formatDate(item.created_at) }}</div>
                                    <div class="text-[10px] font-medium text-slate-500">Oleh: {{ item.creator?.name || 'Sistem' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-bold text-slate-800">{{ item.supplier?.nama_pbf || '-' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold text-slate-800">{{ item.incoming_stock?.faktur_no || '-' }}</div>
                                    <div class="text-xs font-medium text-blue-600 mt-1">{{ item.incoming_stock?.medicine?.nama_obat || '-' }}</div>
                                    <div class="text-[10px] font-bold text-slate-500 mt-0.5">Batch: {{ item.incoming_stock?.batch_no || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2 py-1 bg-red-50 text-red-600 rounded-[8px] font-mono text-sm font-bold border border-red-100">
                                        -{{ item.qty_retur }} <span class="text-[9px] ml-1 uppercase">{{ item.incoming_stock?.medicine?.satuan_stok }}</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs font-medium text-slate-600 max-w-[250px]" :title="item.alasan_retur">
                                        {{ item.alasan_retur }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="inline-flex items-center px-2.5 py-1 bg-green-50 text-green-700 rounded-full text-[10px] font-bold border border-green-200">
                                        SELESAI
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- PAGINATION -->
                    <div v-if="returns.data && returns.data.length > 0" class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            Menampilkan {{ returns.data.length }} riwayat
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL BUAT RETUR -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
            <form @submit.prevent="submitReturn" class="w-full max-w-lg bg-white rounded-[24px] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden border border-[#E8EEF6] animate-in zoom-in-95 duration-200">
                
                <!-- HEADER -->
                <div class="px-6 py-5 border-b border-[#E8EEF6] bg-slate-50/50 flex-none flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-800">Form Retur Obat</h3>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">Kembalikan obat ke supplier (faktur)</p>
                    </div>
                    <button type="button" @click="showModal = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- BODY -->
                <div class="p-6 space-y-5 overflow-y-auto flex-1 custom-scrollbar">
                    
                    <!-- Supplier -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Supplier</label>
                        <select v-model="selectedSupplierId" @change="form.incoming_stock_id = ''" class="w-full h-[52px] bg-slate-50 border border-slate-200 rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                            <option value="">-- Pilih Supplier --</option>
                            <option v-for="sup in availableSuppliers" :key="sup.id" :value="sup.id">
                                {{ sup.nama_pbf }}
                            </option>
                        </select>
                    </div>

                    <!-- Faktur & Obat -->
                    <div v-if="selectedSupplierId">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Faktur / Obat</label>
                        <select v-model="form.incoming_stock_id" required class="w-full h-[52px] bg-slate-50 border border-slate-200 rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                            <option value="">-- Pilih Faktur (Batch) --</option>
                            <option v-for="st in availableStocks" :key="st.id" :value="st.id">
                                [{{ st.faktur_no }}] {{ st.medicine?.nama_obat }} - Batch {{ st.batch_no }}
                            </option>
                        </select>
                    </div>

                    <!-- Detail & Stok (Readonly) -->
                    <div v-if="selectedStockData" class="p-4 bg-blue-50/50 border border-blue-100 rounded-[16px] space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-slate-600">Obat</span>
                            <span class="font-bold text-slate-800">{{ selectedStockData.medicine?.nama_obat }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-slate-600">Batch / EXP</span>
                            <span class="font-bold text-slate-800">{{ selectedStockData.batch_no }} / {{ formatDate(selectedStockData.expired_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm pt-2 border-t border-blue-100">
                            <span class="font-medium text-slate-600">Stok Tersedia (Faktur)</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-bold font-mono">
                                {{ selectedStockData.qty_converted }} <span class="text-[10px] uppercase">{{ selectedStockData.medicine?.satuan_stok }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Satuan Retur -->
                    <div v-if="selectedStockData">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Satuan Fisik Retur</label>
                        <select v-model="form.satuan_level" required class="w-full h-[52px] bg-slate-50 border border-slate-200 rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                            <option v-if="selectedStockData.satuan_input" value="besar">{{ selectedStockData.satuan_input }} (Kemasan Terbesar)</option>
                            <option v-if="selectedStockData.mid_unit" value="menengah">{{ selectedStockData.mid_unit }} (Kemasan Menengah)</option>
                            <option v-if="selectedStockData.medicine?.satuan_stok" value="terkecil">{{ selectedStockData.medicine.satuan_stok }} (Satuan Dasar)</option>
                        </select>
                        <p class="text-[10px] font-medium text-slate-500 mt-1">Pilih kemasan fisik asli yang akan dikembalikan ke supplier.</p>
                    </div>

                    <!-- Jumlah Retur -->
                    <div v-if="selectedStockData">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Jumlah Retur ({{ getSelectedUnitName() }})</label>
                        <input type="number" v-model="form.qty_retur_input" required min="1" :max="maxQtyInput"
                            class="w-full h-[52px] bg-white border border-slate-200 rounded-[16px] px-4 text-lg font-bold text-slate-800 font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" />
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[10px] font-medium text-slate-500">Maksimal retur: {{ maxQtyInput }} {{ getSelectedUnitName() }}</p>
                            <p class="text-[10px] font-bold text-blue-600">Ekuivalen: -{{ convertedQtyToReturn }} {{ selectedStockData.medicine?.satuan_stok }}</p>
                        </div>
                        <p v-if="form.errors.qty_retur" class="text-[10px] text-red-500 mt-1">{{ form.errors.qty_retur }}</p>
                    </div>

                    <!-- Alasan -->
                    <div v-if="selectedStockData">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Alasan Retur</label>
                        <select v-model="form.alasan_retur" required class="w-full h-[52px] bg-slate-50 border border-slate-200 rounded-[16px] px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                            <option value="">-- Pilih Alasan --</option>
                            <option value="Kemasan Rusak">Kemasan Rusak</option>
                            <option value="Obat Rusak / Cacat">Obat Rusak / Cacat</option>
                            <option value="Mendekati Kadaluarsa (ED)">Mendekati Kadaluarsa (ED)</option>
                            <option value="Salah Kirim Barang">Salah Kirim Barang</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                        <p v-if="form.errors.alasan_retur" class="text-[10px] text-red-500 mt-1">{{ form.errors.alasan_retur }}</p>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="px-6 py-5 border-t border-[#E8EEF6] bg-white flex-none flex gap-3">
                    <button type="button" @click="showModal = false" class="flex-1 h-[52px] bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-[16px] transition-colors">
                        Batal
                    </button>
                    <button type="submit" :disabled="form.processing || !selectedStockData" class="flex-1 h-[52px] bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-[16px] transition-all shadow-[0_4px_12px_rgba(37,99,235,0.2)]">
                        {{ form.processing ? 'Memproses...' : 'Simpan Retur' }}
                    </button>
                </div>
            </form>
        </div>
    </ControlLayout>
</template>
