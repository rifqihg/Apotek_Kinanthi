<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ submissions: Object, formulas: Array, rawMaterials: Array });
const showModal = ref(false);

const statusClass = (s) => ({
    pending_final_verification: 'bg-amber-50 text-amber-600 border-amber-200',
    active: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    rejected: 'bg-rose-50 text-rose-600 border-rose-200',
    cancelled: 'bg-slate-50 text-slate-400 border-[#E8EEF6]'
}[s] || 'bg-slate-50 text-slate-600 border-[#E8EEF6]');

const params = new URLSearchParams(window.location.search);
const prefillItemId = params.get('prescription_item_id');
const prefillId = params.get('prescription_id');

const form = useForm({
    nama_racikan: params.get('nama_racikan') || '',
    formula_id: '',
    apoteker_note: '',
    jumlah_unit: params.get('jumlah_unit') ? Number(params.get('jumlah_unit')) : 1,
    target_unit: params.get('target_unit') || 'Kapsul',
    prescription_id: prefillId || null,
    prescription_item_id: prefillItemId || null,
    berat_per_unit: 0.5,
    bahan_baku: [{ 
        id_batch: '',
        nama_bahan: '',
        qty_taken_pcs: '', 
        qty_neto_mass: 0,
        wastage_percentage: 2,
        original_stock: 0, 
        fefo_info: null 
    }],
    label: { tanggal_racik: '', bud_hari: 30, aturan_pakai: '', keterangan: '', nama_pasien: '' }
});

const rawMaterialsMap = computed(() => {
    const map = {};
    (props.rawMaterials || []).forEach(rm => {
        map[rm.id] = rm;
    });
    return map;
});

const total_massa_target = computed(() => {
    return (Number(form.jumlah_unit) || 0) * (Number(form.berat_per_unit) || 0);
});

const calculateItemNeto = (item) => {
    const rm = rawMaterialsMap.value[item.id_batch];
    const density = Number(rm?.density_factor) || 1;
    const gross = Number(item.qty_taken_pcs) || 0;
    const waste = Number(item.wastage_percentage) || 0;
    const neto = (gross * density) * (1 - waste / 100);
    return Number(neto.toFixed(2));
};

const total_massa_neto_bahan = computed(() => {
    return form.bahan_baku.reduce((sum, item) => {
        return sum + calculateItemNeto(item);
    }, 0);
});

const filler_dibutuhkan = computed(() => {
    const diff = total_massa_target.value - total_massa_neto_bahan.value;
    return diff > 0 ? Number(diff.toFixed(2)) : 0;
});

const yieldStats = computed(() => {
    const weight = Number(form.berat_per_unit) || 0;
    if (weight <= 0) return { yield: 0, remainder: 0 };
    if (total_massa_neto_bahan.value >= total_massa_target.value) {
        return {
            yield: Number(form.jumlah_unit),
            remainder: (total_massa_neto_bahan.value - total_massa_target.value).toFixed(3)
        };
    }
    const total = total_massa_neto_bahan.value + filler_dibutuhkan.value;
    return {
        yield: Math.floor(total / weight),
        remainder: (total % weight).toFixed(3)
    };
});

const is_mass_balance_ok = computed(() => {
    const currentTotal = total_massa_neto_bahan.value + filler_dibutuhkan.value;
    return Math.abs(currentTotal - total_massa_target.value) < 0.001 || total_massa_neto_bahan.value > total_massa_target.value;
});

const hasLiquidInCapsule = computed(() => {
    if (form.target_unit !== 'Kapsul') return false;
    return form.bahan_baku.some(d => {
        const rm = rawMaterialsMap.value[d.id_batch];
        return rm?.satuan_ukur === 'ml' || rm?.satuan_ukur === 'L';
    });
});

const isOverstock = (d) => {
    if (!d.id_batch || !d.original_stock) return false;
    return (Number(d.qty_taken_pcs) || 0) > (d.original_stock || 0);
};

const getRemaining = (d) => {
    const remaining = (d.original_stock || 0) - (Number(d.qty_taken_pcs) || 0);
    return Number(remaining.toFixed(2));
};

const stockSafety = computed(() => {
    let overStock = false;
    const items = form.bahan_baku.map(d => {
        const remaining = (d.original_stock || 0) - (Number(d.qty_taken_pcs) || 0);
        // Only flag overstock if a batch is actually selected
        if (d.id_batch && d.original_stock > 0 && remaining < 0) overStock = true;
        return { ...d, remaining };
    });
    return { items, overStock };
});

watch(() => form.formula_id, (newId) => {
    if (!newId) return;
    const formula = (props.formulas || []).find(f => f.id === newId);
    if (formula) {
        if (formula.kategori_sediaan === 'Larutan Air/Sirup') {
            form.label.bud_hari = 14;
            form.target_unit = 'Botol';
            form.berat_per_unit = 0; 
        } else if (formula.kategori_sediaan === 'Sediaan Padat/Puyer/Kapsul') {
            form.label.bud_hari = 30;
            form.target_unit = 'Kapsul';
            form.berat_per_unit = 0.5;
        }
    }
});

const budDate = computed(() => {
    if (!form.label.tanggal_racik || !form.label.bud_hari) return '-';
    const d = new Date(form.label.tanggal_racik);
    d.setDate(d.getDate() + Number(form.label.bud_hari));
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
});

const massBalanceStatus = computed(() => {
    const target = total_massa_target.value;
    const currentTotal = total_massa_neto_bahan.value + filler_dibutuhkan.value;
    return { 
        ok: is_mass_balance_ok.value, 
        current: currentTotal.toFixed(2), 
        required: target.toFixed(2) 
    };
});

function addDetail() { 
    form.bahan_baku.push({ id_batch: '', nama_bahan: '', qty_taken_pcs: '', qty_neto_mass: 0, wastage_percentage: 2, original_stock: 0, fefo_info: null }); 
}
function removeDetail(i) { if(form.bahan_baku.length > 1) form.bahan_baku.splice(i, 1); }

async function fetchFEFO(detail) {
    if (!detail.id_batch) return;
    try {
        const { data } = await axios.get(route('control.apoteker.fefo', detail.id_batch) + '?type=raw_material');
        detail.fefo_info = data.suggestion;
        detail.original_stock = data.suggestion?.stock_on_hand || 0;
        detail.nama_bahan = data.suggestion?.raw_material?.nama_bahan || '';
    } catch { 
        detail.fefo_info = null; 
        detail.original_stock = 0;
    }
}

function submit() {
    // We let backend handle final validation for consistency
    form.bahan_baku.forEach(item => {
        item.qty_neto_mass = calculateItemNeto(item);
    });
    form.post(route('control.apoteker.submissions.store'), { 
        onSuccess: () => { 
            showModal.value = false; 
            form.reset(); 
            // Re-initialize with one empty item
            form.bahan_baku = [{ id_batch: '', nama_bahan: '', qty_taken_pcs: '', qty_neto_mass: 0, wastage_percentage: 2, original_stock: 0, fefo_info: null }]; 
            form.prescription_id = null;
            form.prescription_item_id = null;
        } 
    });
}

const cancelModal = ref({ show: false, id: null });
function confirmCancel(id) { cancelModal.value = { show: true, id }; }
function proceedCancel() {
    if (!cancelModal.value.id) return;
    router.post(route('control.apoteker.submissions.cancel', cancelModal.value.id), {}, {
        onFinish: () => cancelModal.value = { show: false, id: null }
    });
}

// Auto open modal if prefilling from prescription
if (prefillItemId) {
    showModal.value = true;
}
</script>

<template>
    <Head title="Log Racikan" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Manajemen Log Racikan Obat</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Daftar riwayat dan pengajuan compounding apotek</p>
            </div>
            <button @click="showModal=true;form.reset();form.bahan_baku=[{id_batch:'',qty_taken_pcs:'',wastage_percentage:2,fefo_info:null}];form.label={tanggal_racik:'',bud_hari:30,aturan_pakai:'',keterangan:'',nama_pasien:''};form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-[1.2rem] text-[10px] font-black uppercase tracking-widest shadow-lg shadow-slate-900/10 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Buat Racikan Baru
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-[1.5rem] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-slate-50/50 border-b border-[#E8EEF6] uppercase">
                    <tr>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-left uppercase">Nama Racikan</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-left uppercase">Formula</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Items</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">BUD</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Status</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!submissions?.data?.length">
                        <td colspan="6" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 border border-[#E8EEF6]">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Belum Ada Riwayat Pengajuan Racikan</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="s in (submissions?.data || [])" :key="s.id" class="hover:bg-emerald-50/20 transition-all group">
                        <td class="px-8 py-5">
                            <span class="font-bold text-slate-800 text-sm block group-hover:text-emerald-700 transition-colors uppercase tracking-tighter leading-tight">{{ s.nama_racikan || 'Tanpa Nama' }}</span>
                            <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest mt-1 block">ID-{{ s.id }}</span>
                        </td>
                        <td class="px-8 py-5">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-tight">{{ s.formula?.nama_resep || '-' }}</span>
                        </td>
                        <td class="px-8 py-5 text-center font-mono font-black text-slate-700 text-xs">
                            {{ (s.details?.length || 0) }} 
                        </td>
                        <td class="px-8 py-5 text-center text-slate-500 text-[11px] font-bold">
                            {{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span :class="statusClass(s.status)" class="text-[9px] px-3 py-1.5 rounded-[16px] font-black uppercase tracking-widest border shadow-[0_8px_24px_rgba(15,23,42,0.04)] block w-fit mx-auto whitespace-nowrap">
                                {{ s.status === 'pending_final_verification' ? 'Menunggu Harga' : (s.status ? s.status.replace(/_/g, ' ') : 'unknown') }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <div class="flex items-center justify-center gap-3">
                                <button @click="router.get(route('control.apoteker.submissions.show', s.id))" class="text-slate-900 border border-[#E8EEF6] bg-white hover:bg-slate-50 px-3 py-1.5 rounded-[16px] text-[9px] font-black uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all active:scale-95">
                                    Detail
                                </button>
                                <button v-if="s.status === 'pending_final_verification'" @click="confirmCancel(s.id)" class="text-rose-600 border border-rose-100 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-[16px] text-[9px] font-black uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all active:scale-95">
                                    Batalkan
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="submissions?.links?.length > 3" class="flex justify-center gap-1.5 pt-6">
            <template v-for="l in (submissions?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-emerald-500 hover:text-emerald-500'" 
                    class="w-9 h-9 flex items-center justify-center rounded-[16px] text-xs font-black transition-all border uppercase" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>

    <!-- Create Modal (Complex Form) -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-0">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showModal=false"></div>
            <div class="relative bg-white rounded-[16px] shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto scrollbar-v-minimal transform transition-all border border-[#E8EEF6]">
                <div class="sticky top-0 z-10 px-6 py-4 border-b border-[#E8EEF6] bg-white flex items-center justify-between">
                    <h3 class="text-slate-900 font-bold text-lg">Submit Racikan Baru</h3>
                    <button @click="showModal=false" class="text-slate-400 hover:text-slate-600 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Pre-fill Banner -->
                    <div v-if="form.prescription_item_id" class="bg-indigo-50 border border-indigo-100 rounded-[24px] p-4 flex items-center gap-3">
                        <div class="bg-indigo-100 p-2 rounded-[16px] text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-indigo-900 text-sm font-bold">Racikan ini dikerjakan untuk Resep ID #{{ form.prescription_id }}</p>
                            <p class="text-indigo-600 text-xs mt-0.5">Parameter otomatis diisi dari instruksi dokter.</p>
                        </div>
                    </div>

                    <!-- Hidden Fields for FK -->
                    <input type="hidden" v-model="form.prescription_id" />
                    <input type="hidden" v-model="form.prescription_item_id" />

                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Nama Racikan <span class="text-red-500">*</span></label>
                            <input v-model="form.nama_racikan" type="text" class="w-full bg-white border border-[#E8EEF6] rounded-[24px] h-[52px] px-6.5 text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition-shadow" required />
                        </div>
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Template Formula</label>
                            <select v-model="form.formula_id" class="w-full bg-white border border-[#E8EEF6] rounded-[24px] h-[52px] px-6.5 text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition-shadow">
                                <option value="">Tanpa formula (Kustom)</option>
                                <option v-for="f in (formulas || [])" :key="f.id" :value="f.id">{{ f.nama_resep }}</option>
                            </select>
                        </div>
                    </div>
                    <!-- Target Output Section -->
                    <div class="bg-blue-50 border border-blue-100 rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-blue-900 font-bold text-base flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 11v10"></path></svg>
                                Target Produksi & Hasil Jadi
                            </h3>
                            <!-- Status Mass Balance -->
                            <div class="flex items-center gap-2">
                                <div :class="massBalanceStatus.ok ? 'bg-teal-500' : 'bg-red-500'" class="w-2.5 h-2.5 rounded-full animate-pulse"></div>
                                <span :class="massBalanceStatus.ok ? 'text-teal-700' : 'text-red-700'" class="text-[10px] font-black uppercase tracking-wider">
                                    {{ massBalanceStatus.ok ? 'Mass Balance OK' : 'Insufficient Mass' }}
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div class="sm:col-span-1">
                                <label class="block text-blue-700 text-[10px] font-black uppercase mb-1.5 ml-1">Jumlah Unit <span class="text-red-500">*</span></label>
                                <input v-model.number="form.jumlah_unit" type="number" min="1" class="w-full bg-white border border-blue-200 rounded-[16px] h-[52px] px-6.5 text-base font-black text-blue-900 outline-none" required />
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-blue-700 text-[10px] font-black uppercase mb-1.5 ml-1">Satuan</label>
                                <select v-model="form.target_unit" class="w-full bg-white border border-blue-200 rounded-[16px] h-[52px] px-6.5 text-sm font-bold text-slate-700 outline-none" required>
                                    <option value="Kapsul">Kapsul</option>
                                    <option value="Puyer">Puyer</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Pot">Pot</option>
                                </select>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-blue-700 text-[10px] font-black uppercase mb-1.5 ml-1">
                                    Berat/Unit (g)
                                    <span v-if="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2" class="text-red-500 animate-pulse ml-1" title="Peringatan: Berat melebihi kapasitas rata-rata cangkang kapsul (Max 1.2g)">⚠️</span>
                                </label>
                                <input v-model.number="form.berat_per_unit" type="number" step="0.01" class="w-full bg-white border border-blue-200 rounded-[16px] h-[52px] px-6.5 text-sm font-bold text-slate-700 outline-none transition-colors" :class="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2 ? 'border-red-300 bg-red-50 text-red-700' : ''" placeholder="Target berat" />
                            </div>
                            <div class="sm:col-span-1 bg-white/50 border border-blue-100 rounded-[16px] p-2 flex flex-col justify-center text-center relative">
                                <p class="text-[8px] font-black text-blue-400 uppercase">Estimasi Hasil</p>
                                <p class="text-sm font-black text-blue-700">{{ yieldStats.yield }} {{ form.target_unit }}</p>
                                <p v-if="yieldStats.remainder > 0" class="text-[8px] font-bold text-slate-400 mt-0.5">Sisa: {{ yieldStats.remainder }}g</p>
                            </div>
                        </div>

                        <!-- Filler & Safety Information -->
                        <div v-if="filler_dibutuhkan > 0" class="mt-4 p-3 bg-white/40 border border-blue-100 rounded-[24px] flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-[10px] font-black">B</div>
                                <p class="text-blue-900 text-xs font-bold">Butuh Bahan Tambahan (Filler):</p>
                            </div>
                            <p class="text-blue-700 font-black text-sm">{{ filler_dibutuhkan.toFixed(2) }}g</p>
                        </div>

                        <div v-if="hasLiquidInCapsule" class="mt-2 p-3 bg-red-50 border border-red-100 rounded-[24px] flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <p class="text-red-700 text-[10px] font-bold uppercase tracking-tight">Peringatan: Campuran mengandung cairan. Gunakan pengering (Aerosil/Magnesium Carbonat).</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Catatan Apoteker</label>
                        <textarea v-model="form.apoteker_note" rows="2" class="w-full bg-white border border-[#E8EEF6] rounded-[24px] h-[52px] px-6.5 text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none resize-none transition-shadow" placeholder="Instruksi khusus untuk compounding..."></textarea>
                    </div>

                    <!-- Details -->
                    <div class="bg-slate-50 border border-[#E8EEF6] rounded-[24px] p-4">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-slate-800 font-bold">Daftar Bahan Racikan <span class="text-red-500">*</span></p>
                            <button type="button" @click="addDetail" class="inline-flex items-center gap-1 text-teal-600 hover:text-teal-700 text-sm font-semibold bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-[16px] transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Tambah Bahan
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            <div v-for="(d,i) in form.bahan_baku" :key="'detail-'+i" class="bg-white border border-[#E8EEF6] rounded-[24px] p-5 shadow-[0_8px_24px_rgba(15,23,42,0.04)] relative overflow-hidden group">
                                <!-- Overstock Alert Overlay -->
                                <div v-if="isOverstock(d)" class="absolute inset-0 bg-red-50/10 backdrop-blur-[0.5px] pointer-events-none border-2 border-red-500 rounded-[24px] z-20"></div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end relative z-10">
                                    <div class="md:col-span-5">
                                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1.5 ml-1">Pilih Bahan Baku</label>
                                        <select v-model="d.id_batch" @change="fetchFEFO(d)" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none transition-all" required>
                                            <option value="">Cari Bahan...</option>
                                            <option v-for="rm in (rawMaterials || [])" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }} ({{ rm.satuan_ukur }})</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1.5 ml-1">Qty Butuh</label>
                                        <input v-model.number="d.qty_taken_pcs" type="number" step="0.01" min="0" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-black focus:border-teal-500 outline-none" required />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1.5 ml-1">Penyusutan (%)</label>
                                        <input v-model.number="d.wastage_percentage" type="number" step="0.1" min="0" max="100" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-black focus:border-teal-500 outline-none" required />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-slate-500 text-[10px] font-black uppercase mb-1.5 ml-1">Qty Neto (Mass)</label>
                                        <div class="w-full bg-teal-50 border border-teal-100 rounded-[16px] h-[52px] px-6.5 text-teal-700 font-black text-center text-sm shadow-inner min-h-[42px] flex items-center justify-center">
                                            {{ calculateItemNeto(d).toFixed(2) }} g
                                        </div>
                                    </div>
                                    <div class="md:col-span-1 flex justify-center pb-1">
                                        <button type="button" @click="removeDetail(i)" class="text-slate-300 hover:text-red-500 transition-colors p-2 rounded-[16px] hover:bg-red-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- FEFO Suggestion & Real-time Stock Monitor -->
                                <div v-if="d.id_batch" class="mt-4 bg-teal-50/50 border border-teal-100 rounded-[16px] px-4 py-3 flex items-center gap-3">
                                    <div class="bg-teal-100 p-1.5 rounded-[16px]">
                                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-teal-900 text-[11px] font-medium leading-relaxed">
                                            <template v-if="d.fefo_info">
                                                Rekomendasi FEFO: <span class="bg-white border border-teal-200 px-1.5 py-0.5 rounded font-mono font-bold text-teal-700">{{ d.fefo_info.batch_no }}</span> 
                                                <span class="mx-2 text-teal-300">|</span> 
                                                Exp: <strong class="text-teal-800">{{ new Date(d.fefo_info.expired_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) }}</strong>
                                                <span class="mx-2 text-teal-300">|</span> 
                                                Sisa stok fisik: <strong :class="isOverstock(d) ? 'text-red-600 font-black' : 'text-teal-900'">{{ getRemaining(d) }}</strong>
                                            </template>
                                            <template v-else>
                                                <span class="animate-pulse">Mengambil data batch FEFO terdekat...</span>
                                            </template>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Label / Etiket -->
                    <div class="border-t border-[#E8EEF6] pt-5">
                        <h4 class="text-slate-800 font-bold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                            Informasi Etiket / Label Obat
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Tanggal Racik</label>
                                <input v-model="form.label.tanggal_racik" type="date" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] px-3 py-2 text-slate-900 focus:border-teal-500 outline-none transition-shadow" required />
                            </div>
                            <div>
                                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Beyond Use Date (BUD) hari</label>
                                <input v-model="form.label.bud_hari" type="number" min="1" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] px-3 py-2 text-slate-900 focus:border-teal-500 outline-none transition-shadow" required />
                            </div>
                            <div>
                                <label class="block text-slate-500 text-xs font-semibold mb-1.5">Estimasi Kadaluarsa (BUD)</label>
                                <div class="w-full bg-teal-50 border border-teal-100 rounded-[16px] px-3 py-2 text-teal-700 font-bold flex items-center h-[38px] shadow-inner">
                                    <svg class="w-4 h-4 mr-1.5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ budDate }}
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-slate-700 text-xs font-semibold mb-1.5">Aturan Pakai <span class="text-red-500">*</span></label>
                            <input v-model="form.label.aturan_pakai" type="text" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] px-3 py-2.5 text-slate-900 focus:border-teal-500 outline-none transition-shadow" placeholder="Contoh: 3 x sehari 1 bungkus sesudah makan" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Nama Pasien</label>
                                <input v-model="form.label.nama_pasien" type="text" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] px-3 py-2.5 text-slate-900 focus:border-teal-500 outline-none transition-shadow" placeholder="Nama pasien..." />
                            </div>
                            <div>
                                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Keterangan Tambahan</label>
                                <input v-model="form.label.keterangan" type="text" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] px-3 py-2.5 text-slate-900 focus:border-teal-500 outline-none transition-shadow" placeholder="Kocok dahulu, habiskan, dll..." />
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" :disabled="form.processing || stockSafety.overStock" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-4 rounded-[1.5rem] text-base font-black shadow-lg shadow-teal-100 transition-all active:scale-[0.98] disabled:opacity-50 disabled:grayscale">
                            {{ stockSafety.overStock ? 'Bahan Tidak Mencukupi (Batch Over)' : 'Finalisasi & Cetak Etiket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Cancel Confirmation -->
    <Teleport to="body">
        <div v-if="cancelModal.show" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity" @click="cancelModal.show = false"></div>
            <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-md p-10 text-center transform transition-all border border-[#E8EEF6]">
                <div class="w-24 h-24 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner border border-rose-100">
                    <span class="text-4xl animate-bounce">⚠️</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 uppercase tracking-tighter mb-3">Batalkan Racikan?</h3>
                <p class="text-slate-500 text-[11px] font-bold uppercase tracking-tight leading-relaxed mb-10">
                    Proses ini akan mengembalikan alokasi stok ke batch FEFO secara otomatis. Tindakan ini tidak dapat dibatalkan.
                </p>
                
                <div class="flex flex-col gap-4">
                    <button @click="proceedCancel" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-4 rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-rose-200 transition-all active:scale-95">
                        Ya, Batalkan Protokol
                    </button>
                    <button @click="cancelModal.show = false" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 py-4 rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] transition-all">
                        Kembali Ke Log
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.scrollbar-v-minimal::-webkit-scrollbar { width: 5px; }
.scrollbar-v-minimal::-webkit-scrollbar-track { background: transparent; }
.scrollbar-v-minimal::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.08); border-radius: 10px; }
.scrollbar-v-minimal::-webkit-scrollbar-button { display: none; }
.scrollbar-v-minimal:hover::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); }

/* Horizontal scrollbar for tables on mobile */
.scrollbar-h-minimal::-webkit-scrollbar { height: 5px; }
.scrollbar-h-minimal::-webkit-scrollbar-track { background: transparent; }
.scrollbar-h-minimal::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.05); border-radius: 10px; }
.scrollbar-h-minimal:hover::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); }
</style>
