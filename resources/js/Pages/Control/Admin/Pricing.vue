<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import Swal from 'sweetalert2';

const formatCurrency = (val) => Number(val) ? 'Rp ' + Number(val).toLocaleString('id-ID') : '-';

const props = defineProps({
    pendingProducts: Array,
    medicines: Array,
});

// Search & Filter State untuk Obat Jadi
const searchQuery = ref('');
const filterStatus = ref('all'); // 'all' | 'loss' | 'unpriced'

const isMedicineLoss = (m) =>
    (m.selling_price_large > 0 && m.selling_price_large < m.hpp_besar) ||
    (m.selling_price_mid > 0 && m.selling_price_mid < m.hpp_menengah) ||
    (m.selling_price > 0 && m.selling_price < m.hpp_kecil);

const isMedicineUnpriced = (m) =>
    (m.satuan_besar && !Number(m.selling_price_large)) ||
    (m.satuan_menengah && !Number(m.selling_price_mid)) ||
    !Number(m.selling_price);

const countLoss = computed(() => (props.medicines || []).filter(isMedicineLoss).length);
const countUnpriced = computed(() => (props.medicines || []).filter(isMedicineUnpriced).length);

const filteredMedicines = computed(() => {
    let list = props.medicines || [];
    if (filterStatus.value === 'loss') list = list.filter(isMedicineLoss);
    else if (filterStatus.value === 'unpriced') list = list.filter(isMedicineUnpriced);

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(m =>
            m.nama_obat?.toLowerCase().includes(q) ||
            m.kode_obat?.toLowerCase().includes(q) ||
            m.latest_batch_no?.toLowerCase().includes(q)
        );
    }
    return list;
});

// Modal State untuk Obat Jadi
const isModalOpen = ref(false);
const selectedMedicine = ref(null);
const formErrors = ref({});
const priceForm = ref({
    selling_price_large: '',
    selling_price_mid: '',
    selling_price: ''
});

const getMarginBadge = (sell, hpp) => {
    const s = parseFloat(sell) || 0, c = parseFloat(hpp) || 0;
    if (s <= 0 || c <= 0) return null;
    const val = Math.round(((s - c) / c) * 1000) / 10;
    return {
        val,
        text: (val > 0 ? '+' : '') + val + '%',
        isLoss: val < 0,
        isOver: val > 300,
        cls: val < 0 ? 'bg-rose-50 text-rose-700 border-rose-300' : val > 300 ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200',
        modalCls: val > 300 ? 'bg-rose-50 text-rose-700 border-rose-200' : val < 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'
    };
};

const modalTiers = computed(() => {
    const m = selectedMedicine.value;
    if (!m) return [];
    return [
        { field: 'selling_price_large', tierName: 'Tersier', unit: m.satuan_besar, hpp: m.hpp_besar },
        { field: 'selling_price_mid', tierName: 'Sekunder', unit: m.satuan_menengah, hpp: m.hpp_menengah },
        { field: 'selling_price', tierName: 'Primer', unit: m.base_unit, hpp: m.hpp_kecil },
    ].filter(t => t.unit);
});

const openPriceModal = (med) => {
    selectedMedicine.value = med;
    formErrors.value = {};
    priceForm.value = {
        selling_price_large: med.selling_price_large || '',
        selling_price_mid: med.selling_price_mid || '',
        selling_price: med.selling_price || ''
    };
    isModalOpen.value = true;
};

const closePriceModal = () => {
    isModalOpen.value = false;
    selectedMedicine.value = null;
    formErrors.value = {};
};

const submitMedicinePrices = () => {
    formErrors.value = {};
    router.post(route('control.admin.pricing.medicine.set', selectedMedicine.value.id), priceForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closePriceModal();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Harga jual obat jadi berhasil disimpan.',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-[24px]' }
            });
        },
        onError: (errs) => {
            formErrors.value = errs;
            const firstKey = Object.keys(errs)[0];
            const firstMsg = errs[firstKey];
            Swal.fire({
                icon: 'error',
                title: 'Peringatan Margin Tidak Wajar!',
                text: firstMsg || 'Terdapat kesalahan input harga jual.',
                confirmButtonColor: '#ef4444',
                customClass: { popup: 'rounded-[24px]' }
            });
        }
    });
};

const submitCompoundedPrice = (product) => {
    if (!product.temp_harga_jual || product.temp_harga_jual < 1) {
        alert('Harga jual harus lebih dari 0');
        return;
    }
    
    router.post(route('control.admin.pricing.compounded.set', product.id), {
        biaya_kemasan: product.biaya_kemasan || 0,
        jasa_racik: product.jasa_racik || 0,
        margin_percentage: product.margin_percentage || 0,
        total_modal_buat: product.total_modal_buat,
        harga_jual_final: product.temp_harga_jual
    }, {
        preserveScroll: true,
        onSuccess: () => {
            product.temp_harga_jual = '';
        }
    });
};

const calculateHargaJual = (product) => {
    const hpp = parseFloat(product.calculated_hpp) || 0;
    const kemasan = parseFloat(product.biaya_kemasan) || 0;
    const jasa = parseFloat(product.jasa_racik) || 0;
    const margin = parseFloat(product.margin_percentage) || 0;
    
    const modal = hpp + kemasan + jasa;
    product.total_modal_buat = modal;
    product.temp_harga_jual = modal + (modal * margin / 100);
};
</script>

<template>
    <Head title="Penetapan Harga" />

    <ControlLayout>
        <template #header-title>Penetapan Harga</template>

        <div class="space-y-8">
            <!-- SECTION 1: RACIKAN -->
            <div>
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        Racikan Menunggu Penetapan Harga
                        <span class="bg-amber-100 text-amber-700 text-xs font-black px-2 py-1 rounded-full">{{ pendingProducts.length }}</span>
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Produk racikan yang sudah selesai diajukan namun belum memiliki harga jual.</p>
                </div>
                
                <div class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Nama Produk / Batch</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right">HPP Bahan Baku</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right w-32">Jasa Racik</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right w-32">Kemasan</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider text-right w-24">Margin (%)</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider w-40 text-right">Harga Jual Baru</th>
                                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider w-32 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="pendingProducts.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">Semua produk racikan sudah ditetapkan harganya.</td>
                            </tr>
                            <tr v-for="product in pendingProducts" :key="product.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800">{{ product.nama_produk }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ product.batch_no }}</div>
                                    <div class="text-xs text-slate-400">Qty: {{ Number(product.qty_hasil).toLocaleString('id-ID') }} {{ product.satuan }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-emerald-600 bg-emerald-50/30">
                                    Rp {{ Number(product.calculated_hpp).toLocaleString('id-ID') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <input type="number" v-model="product.jasa_racik" @input="calculateHargaJual(product)" class="w-full text-sm text-right border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold" placeholder="0">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <input type="number" v-model="product.biaya_kemasan" @input="calculateHargaJual(product)" class="w-full text-sm text-right border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold" placeholder="0">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <input type="number" v-model="product.margin_percentage" @input="calculateHargaJual(product)" class="w-full text-sm text-right border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold" placeholder="0">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <input type="number" v-model="product.temp_harga_jual" class="w-full text-sm text-right border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold" placeholder="Input harga">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="submitCompoundedPrice(product)" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-[52px] px-6 rounded-[16px] text-xs transition-colors w-full">Tetapkan</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 2: OBAT JADI -->
            <div class="pt-6 border-t border-[#E8EEF6]/60">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-slate-800">Harga Obat Jadi</h2>
                    <p class="text-sm text-slate-500 mt-1">Atur harga jual (selling price) untuk obat jadi. Harga ini akan jadi default di Kasir.</p>
                </div>

                <div class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden h-[640px] flex flex-col">
                    <!-- TOOLBAR: FILTER & SEARCH -->
                    <div class="p-4 border-b border-[#E8EEF6] bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="filterStatus = 'all'" 
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                    :class="filterStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-[#E8EEF6] text-slate-600 hover:bg-slate-50'">
                                <span>Semua Obat</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="filterStatus === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">{{ (medicines || []).length }}</span>
                            </button>

                            <button @click="filterStatus = 'loss'" 
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                    :class="filterStatus === 'loss' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white border border-rose-200 text-rose-700 hover:bg-rose-50/50'">
                                <span>⚠️ Jual Rugi</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="filterStatus === 'loss' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800'">{{ countLoss }}</span>
                            </button>

                            <button @click="filterStatus = 'unpriced'" 
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                    :class="filterStatus === 'unpriced' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white border border-amber-200 text-amber-700 hover:bg-amber-50/50'">
                                <span>Belum Lengkap</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="filterStatus === 'unpriced' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">{{ countUnpriced }}</span>
                            </button>
                        </div>

                        <!-- Search Input -->
                        <div class="relative w-full md:w-72">
                            <input type="text" v-model="searchQuery" 
                                   placeholder="Cari nama atau kode obat..." 
                                   class="w-full text-xs pl-9 pr-8 py-2 bg-white border border-[#E8EEF6] rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
                        </div>
                    </div>

                    <!-- TABLE CONTENT -->
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50/95 backdrop-blur z-10">
                                <tr class="border-b border-[#E8EEF6]">
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider">Nama Obat</th>
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider">Kemasan</th>
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider text-right">HPP Modal (Netto)</th>
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Harga Jual Saat Ini</th>
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Margin (%)</th>
                                    <th class="px-6 py-3.5 text-xs font-black text-slate-500 uppercase tracking-wider w-28 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="filteredMedicines.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-xs font-medium">Tidak ada data obat yang sesuai dengan filter atau pencarian.</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="med in filteredMedicines" :key="med.id" class="hover:bg-slate-50/60 transition-colors">
                                    <!-- NAMA OBAT -->
                                    <td class="px-6 py-4 align-top">
                                        <div class="font-bold text-slate-800 text-sm">{{ med.nama_obat }}</div>
                                        <div class="flex flex-wrap gap-1.5 items-center mt-1.5">
                                            <span v-if="med.kode_obat" class="text-[10px] text-slate-400 font-mono bg-slate-100 px-1.5 py-0.5 rounded">{{ med.kode_obat }}</span>
                                            <span v-if="med.latest_batch_no" class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[9px] font-black rounded font-mono uppercase tracking-widest border border-amber-200">
                                                {{ med.latest_batch_no }}
                                            </span>
                                            <span v-if="isMedicineLoss(med)" class="px-2 py-0.5 bg-rose-100 text-rose-700 text-[9px] font-black rounded-full border border-rose-200 uppercase tracking-wide inline-flex items-center gap-1">
                                                <span>⚠️ Jual Rugi</span>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- KEMASAN -->
                                    <td class="px-6 py-4 align-top text-xs font-semibold text-slate-600">
                                        <div class="flex flex-col gap-2">
                                            <!-- Tersier -->
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center">
                                                <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] font-bold text-slate-700">
                                                    {{ med.satuan_besar }} <span class="text-[9px] font-normal text-slate-400 font-sans">(Tersier)</span>
                                                </span>
                                            </div>
                                            <!-- Sekunder -->
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center gap-1.5">
                                                <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] font-bold text-slate-700">
                                                    {{ med.satuan_menengah }} <span class="text-[9px] font-normal text-slate-400 font-sans">(Sekunder)</span>
                                                </span>
                                                <span v-if="med.mid_to_big_ratio" class="text-[9px] text-slate-400 font-mono">
                                                    (1={{ med.mid_to_big_ratio }})
                                                </span>
                                            </div>
                                            <!-- Primer -->
                                            <div v-if="med.base_unit" class="h-6 flex items-center gap-1.5">
                                                <span class="bg-slate-100 px-2 py-0.5 rounded text-[11px] font-bold text-slate-700">
                                                    {{ med.base_unit }} <span class="text-[9px] font-normal text-slate-400 font-sans">(Primer)</span>
                                                </span>
                                                <span v-if="med.small_to_mid_ratio && med.satuan_menengah" class="text-[9px] text-slate-400 font-mono">
                                                    (1={{ med.small_to_mid_ratio }})
                                                </span>
                                                <span v-else-if="med.small_to_mid_ratio && !med.satuan_menengah && med.satuan_besar" class="text-[9px] text-slate-400 font-mono">
                                                    (1={{ med.small_to_mid_ratio }})
                                                </span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center text-slate-400 text-xs italic">
                                                Belum diset
                                            </div>
                                        </div>
                                    </td>

                                    <!-- HPP MODAL (NETTO) -->
                                    <td class="px-6 py-4 align-top text-right">
                                        <div class="flex flex-col gap-2">
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-end font-mono text-xs text-slate-500 font-medium">
                                                <span v-if="med.hpp_besar > 0">{{ formatCurrency(med.hpp_besar) }}</span>
                                                <span v-else class="text-[10px] text-slate-300 italic">-</span>
                                            </div>
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-end font-mono text-xs text-slate-500 font-medium">
                                                <span v-if="med.hpp_menengah > 0">{{ formatCurrency(med.hpp_menengah) }}</span>
                                                <span v-else class="text-[10px] text-slate-300 italic">-</span>
                                            </div>
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-end font-mono text-xs text-slate-500 font-medium">
                                                <span v-if="med.hpp_kecil > 0">{{ formatCurrency(med.hpp_kecil) }}</span>
                                                <span v-else class="text-[10px] text-slate-300 italic">-</span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-end text-[10px] text-slate-300 italic">
                                                -
                                            </div>
                                        </div>
                                    </td>

                                    <!-- HARGA JUAL SAAT INI -->
                                    <td class="px-6 py-4 align-top text-right font-bold">
                                        <div class="flex flex-col gap-2">
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-end font-mono text-xs">
                                                <span v-if="med.selling_price_large > 0" class="text-slate-800">{{ formatCurrency(med.selling_price_large) }}</span>
                                                <span v-else class="text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-semibold italic">Belum diset</span>
                                            </div>
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-end font-mono text-xs">
                                                <span v-if="med.selling_price_mid > 0" class="text-slate-800">{{ formatCurrency(med.selling_price_mid) }}</span>
                                                <span v-else class="text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-semibold italic">Belum diset</span>
                                            </div>
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-end font-mono text-xs">
                                                <span v-if="med.selling_price > 0" class="text-slate-800">{{ formatCurrency(med.selling_price) }}</span>
                                                <span v-else class="text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-semibold italic">Belum diset</span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-end text-[10px] text-slate-400 italic">
                                                -
                                            </div>
                                        </div>
                                    </td>

                                    <!-- MARGIN (%) -->
                                    <td class="px-6 py-4 align-top text-right font-bold">
                                        <div class="flex flex-col gap-2">
                                            <!-- Margin Tersier -->
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-end">
                                                <span v-if="getMarginBadge(med.selling_price_large, med.hpp_besar)"
                                                      class="text-[11px] font-bold px-2 py-0.5 rounded-md border inline-flex items-center gap-1 font-mono"
                                                      :class="getMarginBadge(med.selling_price_large, med.hpp_besar).cls">
                                                    <span>{{ getMarginBadge(med.selling_price_large, med.hpp_besar).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price_large, med.hpp_besar).isLoss" class="text-[9px] font-black text-rose-600">RUGI</span>
                                                </span>
                                                <span v-else class="text-[10px] text-slate-300 font-medium italic">-</span>
                                            </div>

                                            <!-- Margin Sekunder -->
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-end">
                                                <span v-if="getMarginBadge(med.selling_price_mid, med.hpp_menengah)"
                                                      class="text-[11px] font-bold px-2 py-0.5 rounded-md border inline-flex items-center gap-1 font-mono"
                                                      :class="getMarginBadge(med.selling_price_mid, med.hpp_menengah).cls">
                                                    <span>{{ getMarginBadge(med.selling_price_mid, med.hpp_menengah).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price_mid, med.hpp_menengah).isLoss" class="text-[9px] font-black text-rose-600">RUGI</span>
                                                </span>
                                                <span v-else class="text-[10px] text-slate-300 font-medium italic">-</span>
                                            </div>

                                            <!-- Margin Primer -->
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-end">
                                                <span v-if="getMarginBadge(med.selling_price, med.hpp_kecil)"
                                                      class="text-[11px] font-bold px-2 py-0.5 rounded-md border inline-flex items-center gap-1 font-mono"
                                                      :class="getMarginBadge(med.selling_price, med.hpp_kecil).cls">
                                                    <span>{{ getMarginBadge(med.selling_price, med.hpp_kecil).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price, med.hpp_kecil).isLoss" class="text-[9px] font-black text-rose-600">RUGI</span>
                                                </span>
                                                <span v-else class="text-[10px] text-slate-300 font-medium italic">-</span>
                                            </div>

                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-end text-[10px] text-slate-300 italic">
                                                -
                                            </div>
                                        </div>
                                    </td>

                                    <!-- AKSI -->
                                    <td class="px-6 py-4 align-top text-center">
                                        <button @click="openPriceModal(med)" 
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-[36px] px-3.5 rounded-[12px] text-xs transition-all shadow-sm hover:shadow shadow-indigo-100 flex items-center justify-center gap-1.5 w-full">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Atur</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL ATUR HARGA -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closePriceModal"></div>
            <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="p-6 border-b border-[#E8EEF6]">
                    <h3 class="text-lg font-bold text-slate-800">Atur Harga Jual</h3>
                    <p class="text-sm text-slate-500 mt-1" v-if="selectedMedicine">Obat: <span class="font-bold text-indigo-600">{{ selectedMedicine.nama_obat }}</span></p>
                </div>
                
                <div class="p-6 space-y-4 flex-1 overflow-y-auto">
                    <!-- Dynamic Packaging Tiers (Tersier / Sekunder / Primer) -->
                    <div v-for="t in modalTiers" :key="t.field" class="p-4 bg-slate-50 rounded-[16px] border border-slate-100">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Harga Kemasan {{ t.tierName }} ({{ t.unit }})</label>
                            <span v-if="t.hpp > 0" class="text-[11px] font-mono text-slate-500 font-semibold">
                                Modal HPP: Rp {{ Number(t.hpp).toLocaleString('id-ID') }}
                            </span>
                        </div>
                        <input type="number" v-model="priceForm[t.field]" 
                               class="w-full text-sm border-[#E8EEF6] rounded-[12px] focus:ring-indigo-500 focus:border-indigo-500 font-bold" 
                               :class="{'border-rose-400 focus:ring-rose-400': formErrors[t.field]}" 
                               placeholder="0">
                        
                        <!-- Live Margin Indicator -->
                        <div v-if="getMarginBadge(priceForm[t.field], t.hpp)" class="mt-2 flex items-center gap-2">
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-md border inline-flex items-center gap-1"
                                  :class="getMarginBadge(priceForm[t.field], t.hpp).modalCls">
                                <span>Margin: {{ getMarginBadge(priceForm[t.field], t.hpp).text }}</span>
                                <span v-if="getMarginBadge(priceForm[t.field], t.hpp).isOver">⚠️ Melebihi batas 300%!</span>
                                <span v-else-if="getMarginBadge(priceForm[t.field], t.hpp).isLoss">⚠️ Jual rugi!</span>
                                <span v-else>✓ Wajar</span>
                            </span>
                        </div>
                        <p v-if="formErrors[t.field]" class="text-rose-600 text-[11px] font-semibold mt-1.5 leading-tight">
                            {{ formErrors[t.field] }}
                        </p>
                    </div>
                    
                    <div v-if="modalTiers.length === 0" class="p-4 bg-amber-50 rounded-[12px] text-amber-700 text-sm">
                        <p class="font-bold mb-1">Informasi Kemasan Belum Lengkap</p>
                        <p class="text-xs">Obat ini belum memiliki informasi kemasan dari faktur masuk.</p>
                        <div class="mt-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Harga Satuan Default</label>
                            <input type="number" v-model="priceForm.selling_price" class="w-full text-sm border-amber-200 rounded-[12px] focus:ring-amber-500 focus:border-amber-500 font-bold bg-white" placeholder="0">
                        </div>
                    </div>
                </div>
                
                <div class="p-6 bg-slate-50 border-t border-[#E8EEF6] flex gap-3">
                    <button @click="closePriceModal" class="flex-1 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold h-[48px] rounded-[12px] text-sm transition-colors">Batal</button>
                    <button @click="submitMedicinePrices" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-[48px] rounded-[12px] text-sm transition-colors shadow-lg shadow-indigo-200">Simpan Harga</button>
                </div>
            </div>
        </div>
    </ControlLayout>
</template>
