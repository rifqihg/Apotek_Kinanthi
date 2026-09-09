<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import axios from 'axios';

defineOptions({ layout: ControlLayout });

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
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Manajemen Log Racikan Obat</h1>
                <p class="text-sm text-muted-foreground mt-1">Daftar riwayat dan pengajuan compounding apotek</p>
            </div>
            <button @click="showModal=true;form.reset();form.bahan_baku=[{id_batch:'',qty_taken_pcs:'',wastage_percentage:2,fefo_info:null}];form.label={tanggal_racik:'',bud_hari:30,aturan_pakai:'',keterangan:'',nama_pasien:''};form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-9 px-4 rounded-md text-sm font-medium shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Buat Racikan Baru
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left">Nama Racikan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left">Formula</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Items</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">BUD</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!submissions?.data?.length">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-12 h-12 bg-muted rounded-md flex items-center justify-center text-muted-foreground border border-border">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <span class="text-sm font-medium text-muted-foreground">Belum Ada Riwayat Pengajuan Racikan</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="s in (submissions?.data || [])" :key="s.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-medium text-foreground text-sm block">{{ s.nama_racikan || 'Tanpa Nama' }}</span>
                            <span class="text-xs text-muted-foreground tracking-wide mt-0.5 block">ID-{{ s.id }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium text-muted-foreground">{{ s.formula?.nama_resep || '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-foreground text-sm">
                            {{ (s.details?.length || 0) }} 
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground text-xs font-medium">
                            {{ s.label?.tanggal_kadaluarsa_bud ? new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="statusClass(s.status)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block whitespace-nowrap">
                                {{ s.status === 'pending_final_verification' ? 'Menunggu Harga' : (s.status ? s.status.replace(/_/g, ' ') : 'unknown') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="router.get(route('control.apoteker.submissions.show', s.id))" class="text-foreground hover:text-primary bg-secondary hover:bg-secondary/80 border border-border px-3 h-8 rounded-md text-xs font-medium transition-colors">
                                    Detail
                                </button>
                                <button v-if="s.status === 'pending_final_verification'" @click="confirmCancel(s.id)" class="text-red-600 dark:text-red-400 hover:bg-red-500/10 border border-red-500/20 px-3 h-8 rounded-md text-xs font-medium transition-colors">
                                    Batalkan
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="submissions?.links?.length > 3" class="flex justify-center gap-1 pt-4">
            <template v-for="l in (submissions?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                    class="w-8 h-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>

    <!-- Create Modal (Complex Form) -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity" @click="showModal=false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto border border-border">
                <div class="sticky top-0 z-10 px-6 py-4 border-b border-border bg-card flex items-center justify-between">
                    <h3 class="text-foreground font-semibold text-base">Submit Racikan Baru</h3>
                    <button @click="showModal=false" class="text-muted-foreground hover:text-foreground p-1 rounded-md transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Pre-fill Banner -->
                    <div v-if="form.prescription_item_id" class="bg-primary/10 border border-primary/20 rounded-md p-3 flex items-center gap-3">
                        <div class="p-1.5 rounded-md bg-primary/20 text-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-foreground text-sm font-medium">Racikan ini dikerjakan untuk Resep ID #{{ form.prescription_id }}</p>
                            <p class="text-muted-foreground text-xs">Parameter otomatis diisi dari instruksi dokter.</p>
                        </div>
                    </div>

                    <!-- Hidden Fields for FK -->
                    <input type="hidden" v-model="form.prescription_id" />
                    <input type="hidden" v-model="form.prescription_item_id" />

                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Racikan <span class="text-red-500">*</span></label>
                            <input v-model="form.nama_racikan" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Template Formula</label>
                            <select v-model="form.formula_id" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                                <option value="">Tanpa formula (Kustom)</option>
                                <option v-for="f in (formulas || [])" :key="f.id" :value="f.id">{{ f.nama_resep }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Target Output Section -->
                    <div class="bg-muted/30 border border-border rounded-md p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-foreground font-semibold text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 11v10"></path></svg>
                                Target Produksi & Hasil Jadi
                            </h4>
                            <!-- Status Mass Balance -->
                            <div class="flex items-center gap-1.5">
                                <div :class="massBalanceStatus.ok ? 'bg-emerald-500' : 'bg-red-500'" class="w-2 h-2 rounded-full animate-pulse"></div>
                                <span :class="massBalanceStatus.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-xs font-semibold uppercase tracking-wider">
                                    {{ massBalanceStatus.ok ? 'Mass Balance OK' : 'Insufficient Mass' }}
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Jumlah Unit <span class="text-red-500">*</span></label>
                                <input v-model.number="form.jumlah_unit" type="number" min="1" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring" required />
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Satuan</label>
                                <select v-model="form.target_unit" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring" required>
                                    <option value="Kapsul">Kapsul</option>
                                    <option value="Puyer">Puyer</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Pot">Pot</option>
                                </select>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                                    Berat/Unit (g)
                                    <span v-if="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2" class="text-red-500 ml-1" title="Peringatan: Berat melebihi kapasitas rata-rata cangkang kapsul (Max 1.2g)">⚠️</span>
                                </label>
                                <input v-model.number="form.berat_per_unit" type="number" step="0.01" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring transition-colors" :class="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2 ? 'border-red-500 text-red-500' : ''" placeholder="Target berat" />
                            </div>
                            <div class="sm:col-span-1 bg-background border border-border rounded-md p-2 flex flex-col justify-center text-center">
                                <p class="text-xs text-muted-foreground uppercase">Estimasi Hasil</p>
                                <p class="text-sm font-semibold text-foreground">{{ yieldStats.yield }} {{ form.target_unit }}</p>
                                <p v-if="yieldStats.remainder > 0" class="text-xs text-muted-foreground mt-0.5">Sisa: {{ yieldStats.remainder }}g</p>
                            </div>
                        </div>

                        <!-- Filler & Safety Information -->
                        <div v-if="filler_dibutuhkan > 0" class="p-3 bg-background border border-border rounded-md flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 bg-primary/20 text-primary rounded-full flex items-center justify-center text-xs font-bold">B</div>
                                <p class="text-foreground text-xs font-medium">Butuh Bahan Tambahan (Filler):</p>
                            </div>
                            <p class="text-foreground font-semibold text-sm">{{ filler_dibutuhkan.toFixed(2) }}g</p>
                        </div>

                        <div v-if="hasLiquidInCapsule" class="p-3 bg-red-500/10 border border-red-500/20 rounded-md flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <p class="text-red-600 dark:text-red-400 text-xs font-medium">Peringatan: Campuran mengandung cairan. Gunakan pengering (Aerosil/Magnesium Carbonat).</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Apoteker</label>
                        <textarea v-model="form.apoteker_note" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none resize-none transition-colors" placeholder="Instruksi khusus untuk compounding..."></textarea>
                    </div>

                    <!-- Details -->
                    <div class="bg-muted/30 border border-border rounded-md p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-foreground font-semibold text-sm">Daftar Bahan Racikan <span class="text-red-500">*</span></p>
                            <button type="button" @click="addDetail" class="inline-flex items-center gap-1 text-primary hover:text-primary/80 text-xs font-medium bg-background border border-border hover:bg-muted px-3 h-8 rounded-md transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Tambah Bahan
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="(d,i) in form.bahan_baku" :key="'detail-'+i" class="bg-card border border-border rounded-md p-4 shadow-sm relative overflow-hidden group">
                                <!-- Overstock Alert Overlay -->
                                <div v-if="isOverstock(d)" class="absolute inset-0 bg-red-500/10 pointer-events-none border border-red-500 rounded-md z-20"></div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end relative z-10">
                                    <div class="md:col-span-5">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Pilih Bahan Baku</label>
                                        <select v-model="d.id_batch" @change="fetchFEFO(d)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                            <option value="">Cari Bahan...</option>
                                            <option v-for="rm in (rawMaterials || [])" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }} ({{ rm.satuan_ukur }})</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Qty Butuh</label>
                                        <input v-model.number="d.qty_taken_pcs" type="number" step="0.01" min="0" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm font-semibold focus:ring-1 focus:ring-ring outline-none" required />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Penyusutan (%)</label>
                                        <input v-model.number="d.wastage_percentage" type="number" step="0.1" min="0" max="100" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm font-semibold focus:ring-1 focus:ring-ring outline-none" required />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Qty Neto (Mass)</label>
                                        <div class="w-full bg-muted border border-border rounded-md h-9 px-3 text-foreground font-semibold text-center text-sm flex items-center justify-center">
                                            {{ calculateItemNeto(d).toFixed(2) }} g
                                        </div>
                                    </div>
                                    <div class="md:col-span-1 flex justify-center">
                                        <button type="button" @click="removeDetail(i)" class="text-muted-foreground hover:text-red-500 transition-colors p-2 rounded-md hover:bg-red-500/10">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- FEFO Suggestion & Real-time Stock Monitor -->
                                <div v-if="d.id_batch" class="mt-3 bg-muted/50 border border-border rounded-md px-3 py-2 flex items-center gap-2">
                                    <div class="p-1 rounded-md bg-primary/10 text-primary">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="flex-1 text-xs">
                                        <p class="text-foreground leading-relaxed">
                                            <template v-if="d.fefo_info">
                                                Rekomendasi FEFO: <span class="bg-background border border-border px-1.5 py-0.5 rounded font-medium text-foreground tracking-wide">{{ d.fefo_info.batch_no }}</span> 
                                                <span class="mx-1.5 text-muted-foreground">|</span> 
                                                Exp: <strong class="text-foreground">{{ new Date(d.fefo_info.expired_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) }}</strong>
                                                <span class="mx-1.5 text-muted-foreground">|</span> 
                                                Sisa stok fisik: <strong :class="isOverstock(d) ? 'text-red-600 font-bold' : 'text-foreground'">{{ getRemaining(d) }}</strong>
                                            </template>
                                            <template v-else>
                                                <span class="text-muted-foreground animate-pulse">Mengambil data batch FEFO terdekat...</span>
                                            </template>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Label / Etiket -->
                    <div class="border-t border-border pt-4 space-y-4">
                        <h4 class="text-foreground font-semibold text-sm flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                            Informasi Etiket / Label Obat
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tanggal Racik</label>
                                <input v-model="form.label.tanggal_racik" type="date" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Beyond Use Date (BUD) hari</label>
                                <input v-model="form.label.bud_hari" type="number" min="1" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">Estimasi Kadaluarsa (BUD)</label>
                                <div class="w-full bg-muted border border-border rounded-md px-3 h-9 text-foreground font-medium text-xs flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ budDate }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Aturan Pakai <span class="text-red-500">*</span></label>
                            <input v-model="form.label.aturan_pakai" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 3 x sehari 1 bungkus sesudah makan" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Pasien</label>
                                <input v-model="form.label.nama_pasien" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Nama pasien..." />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Keterangan Tambahan</label>
                                <input v-model="form.label.keterangan" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Kocok dahulu, habiskan, dll..." />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" :disabled="form.processing || stockSafety.overStock" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white h-10 rounded-md text-sm font-medium shadow-sm transition-colors uppercase tracking-wider disabled:opacity-50">
                            {{ stockSafety.overStock ? 'Bahan Tidak Mencukupi (Batch Over)' : 'Finalisasi & Cetak Etiket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Cancel Confirmation -->
    <Teleport to="body">
        <div v-if="cancelModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity" @click="cancelModal.show = false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md p-6 text-center border border-border">
                <div class="w-12 h-12 bg-red-500/10 text-red-600 dark:text-red-400 rounded-md flex items-center justify-center mx-auto mb-4 border border-red-500/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <h3 class="text-base font-semibold text-foreground mb-2">Batalkan Racikan?</h3>
                <p class="text-muted-foreground text-xs leading-relaxed mb-6">
                    Proses ini akan mengembalikan alokasi stok ke batch FEFO secara otomatis. Tindakan ini tidak dapat dibatalkan.
                </p>
                
                <div class="flex justify-end gap-2">
                    <button @click="cancelModal.show = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">
                        Kembali Ke Log
                    </button>
                    <button @click="proceedCancel" class="px-4 h-9 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors">
                        Ya, Batalkan Protokol
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
