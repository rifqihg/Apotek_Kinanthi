<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';

import { useToast } from '@/Components/ui/toast/use-toast';
import { useSmartSync } from '@/Composables/useSmartSync';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    submissions: Object, 
    filters: { type: Object, default: () => ({}) }
});

const formulas = ref([]);
const rawMaterials = ref([]);
const medicines = ref([]);
const isLoadingReferenceData = ref(false);
const hasLoadedReferenceData = ref(false);
const { toast } = useToast();

const loadReferenceData = async () => {
    if (hasLoadedReferenceData.value) return;
    isLoadingReferenceData.value = true;
    try {
        const response = await axios.get(route('control.apoteker.submissions.reference'));
        formulas.value = response.data.formulas || [];
        rawMaterials.value = response.data.rawMaterials || [];
        medicines.value = response.data.medicines || [];
        hasLoadedReferenceData.value = true;
    } catch (e) {
        toast({ title: 'Gagal', description: 'Gagal memuat data referensi', variant: 'destructive' });
    } finally {
        isLoadingReferenceData.value = false;
    }
};

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const unitFilter = ref(props.filters?.unit || 'all');

const filteredSubmissions = computed(() => {
    let list = props.submissions?.data || [];
    if (statusFilter.value !== 'all') {
        list = list.filter(s => s.status === statusFilter.value);
    }
    if (unitFilter.value !== 'all') {
        list = list.filter(s => (s.target_unit || '').toLowerCase() === unitFilter.value.toLowerCase());
    }
    if (search.value && search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(s => 
            s.nomor_pengajuan?.toLowerCase().includes(q) ||
            s.nama_produk?.toLowerCase().includes(q) ||
            s.user?.name?.toLowerCase().includes(q)
        );
    }
    return list;
});

const performServerSearch = useDebounceFn(() => {
    router.reload({
        data: {
            search: search.value || undefined,
            page: 1,
        },
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
        only: ['submissions', 'filters'],
    });
}, 400);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) performServerSearch();
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
    unitFilter.value = 'all';
    performServerSearch();
};
const showModal = ref(false);

// Sinkronisasi otomatis daftar pengajuan racikan (dijeda otomatis jika modal form racikan sedang aktif diisi)
useSmartSync({
    only: ['submissions'],
    interval: 12000,
    throttle: 3000,
    enabled: () => !showModal.value
});

watch(showModal, (newVal) => {
    if (newVal) loadReferenceData();
});

onMounted(() => {
    loadReferenceData(); // Pre-load data in background
});

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
        source_type: 'raw_material',
        id_batch: '',
        nama_bahan: '',
        qty_taken_pcs: '', 
        qty_neto_mass: 0,
        original_stock: 0, 
        fefo_info: null 
    }],
    label: { 
        tanggal_racik: new Date().toISOString().split('T')[0], 
        bud_hari: 30, 
        aturan_pakai: '', 
        keterangan: ''
    }
});

const rawMaterialsMap = computed(() => {
    const map = {};
    (rawMaterials.value || []).forEach(rm => {
        map[rm.id] = rm;
    });
    return map;
});

const medicinesMap = computed(() => {
    const map = {};
    (medicines.value || []).forEach(m => {
        map[m.id] = m;
    });
    return map;
});

const total_massa_target = computed(() => {
    return (Number(form.jumlah_unit) || 0) * (Number(form.berat_per_unit) || 0);
});

const calculateItemNeto = (item) => {
    const isMed = item.source_type === 'medicine';
    const density = isMed ? 0 : (Number(rawMaterialsMap.value[item.id_batch]?.density_factor) || 1);
    const gross = Number(item.qty_taken_pcs) || 0;
    const neto = gross * density;
    return Number(neto.toFixed(2));
};

const getItemUnit = (detail) => {
    if (!detail?.id_batch) return '';
    if (detail.source_type === 'medicine') {
        const med = medicinesMap.value[detail.id_batch];
        return med?.satuan_terkecil || med?.satuan_stok || 'Pcs';
    }
    const rm = rawMaterialsMap.value[detail.id_batch];
    return rm?.satuan_dasar_penggunaan || rm?.satuan_ukur || 'g';
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
    if (weight <= 0) return { yield: Number(form.jumlah_unit), remainder: 0 };
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
    const weight = Number(form.berat_per_unit) || 0;
    if (weight <= 0) return true;
    const currentTotal = total_massa_neto_bahan.value + filler_dibutuhkan.value;
    return Math.abs(currentTotal - total_massa_target.value) < 0.001 || total_massa_neto_bahan.value > total_massa_target.value;
});

const hasLiquidInCapsule = computed(() => {
    if (form.target_unit !== 'Kapsul') return false;
    return form.bahan_baku.some(d => {
        if (d.source_type === 'medicine') return false;
        const rm = rawMaterialsMap.value[d.id_batch];
        return rm?.satuan_dasar_penggunaan === 'ml' || rm?.satuan_dasar_penggunaan === 'mL' || rm?.satuan_dasar_penggunaan === 'L' || rm?.satuan_dasar_penggunaan === 'Liter';
    });
});

const hasMedicineInIngredients = computed(() => {
    return form.bahan_baku.some(item => item.source_type === 'medicine' && item.id_batch);
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

watch(() => form.target_unit, (newUnit) => {
    if (!newUnit) return;
    const unit = newUnit.toLowerCase();
    if (unit === 'botol' || unit.includes('sirup') || unit.includes('larutan') || unit.includes('suspensi')) {
        form.label.bud_hari = 14;
    } else {
        // Kapsul, Puyer, Serbuk terbagi, Pot, Tube (Semisolid: Salep/Krim/Gel/Pasta) -> 30 hari
        form.label.bud_hari = 30;
    }
});

watch(() => form.formula_id, (newId) => {
    if (!newId) return;
    const formula = (formulas.value || []).find(f => f.id === newId);
    if (formula) {
        form.nama_racikan = formula.nama_resep || '';
        form.apoteker_note = formula.instruksi_racik || '';
        form.label.aturan_pakai = formula.signa_default || '';
        form.label.bud_hari = formula.bud_days || 30;

        form.jumlah_unit = Number(formula.target_qty) || 1;

        if (formula.target_unit) {
            form.target_unit = formula.target_unit;
            const unit = formula.target_unit.toLowerCase();
            if (unit === 'botol' || unit.includes('sirup') || unit.includes('larutan') || unit.includes('suspensi')) {
                form.berat_per_unit = 0;
            } else if (unit.includes('pot') || unit.includes('salep') || unit.includes('krim') || unit.includes('gel')) {
                form.berat_per_unit = 10;
            } else {
                form.berat_per_unit = 0.5;
            }
        } else {
            const kat = (formula.kategori_sediaan || '').toLowerCase();
            if (kat.includes('larutan') || kat.includes('sirup') || kat.includes('suspensi')) {
                form.target_unit = 'Botol';
                form.berat_per_unit = 0; 
            } else if (kat.includes('salep') || kat.includes('krim') || kat.includes('semisolid') || kat.includes('pasta') || kat.includes('gel')) {
                form.target_unit = 'Pot';
                form.berat_per_unit = 10;
            } else {
                form.target_unit = 'Kapsul';
                form.berat_per_unit = 0.5;
            }
        }

        if (formula.ingredients && formula.ingredients.length > 0) {
            form.bahan_baku = formula.ingredients.map(ing => ({
                source_type: ing.source_type,
                id_batch: ing.source_id,
                nama_bahan: '', 
                qty_taken_pcs: ing.qty_required || '',
                qty_neto_mass: 0,
                original_stock: 0,
                fefo_info: null
            }));
            
            // Trigger fetchFEFO asynchronously on the reactive proxies
            form.bahan_baku.forEach(d => fetchFEFO(d));
        }
    }
});

const earliestMaterialBatch = computed(() => {
    let earliest = null;
    form.bahan_baku.forEach(d => {
        if (d.fefo_info?.expired_date) {
            const exp = new Date(d.fefo_info.expired_date);
            if (!earliest || exp < earliest.date) {
                earliest = {
                    date: exp,
                    dateStr: d.fefo_info.expired_date,
                    name: d.nama_bahan || (d.source_type === 'medicine' ? 'Obat Jadi' : 'Bahan Baku')
                };
            }
        }
    });
    return earliest;
});

const isBudExceedingMaterialExpiry = computed(() => {
    if (!form.label.tanggal_racik || !form.label.bud_hari || !earliestMaterialBatch.value) return false;
    const bud = new Date(form.label.tanggal_racik);
    bud.setDate(bud.getDate() + Number(form.label.bud_hari));
    return bud > earliestMaterialBatch.value.date;
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
    form.bahan_baku.push({ 
        source_type: 'raw_material',
        id_batch: '', 
        nama_bahan: '', 
        qty_taken_pcs: '', 
        qty_neto_mass: 0, 
        original_stock: 0, 
        fefo_info: null 
    }); 
}
function removeDetail(i) { if(form.bahan_baku.length > 1) form.bahan_baku.splice(i, 1); }

async function fetchFEFO(detail) {
    if (!detail.id_batch) return;
    const type = detail.source_type || 'raw_material';
    detail.fefo_info = null; // Start loading
    try {
        const { data } = await axios.get(route('control.apoteker.fefo', detail.id_batch) + `?type=${type}`);
        if (data.suggestion) {
            detail.fefo_info = data.suggestion;
            detail.original_stock = Number(data.suggestion?.stock_on_hand) || 0;
            detail.nama_bahan = type === 'medicine'
                ? (data.suggestion?.medicine?.nama_obat || '')
                : (data.suggestion?.raw_material?.nama_bahan || '');
        } else {
            detail.fefo_info = { empty: true };
            detail.original_stock = 0;
            detail.nama_bahan = type === 'medicine' 
                ? (medicinesMap.value[detail.id_batch]?.nama_obat || '') 
                : (rawMaterialsMap.value[detail.id_batch]?.nama_bahan || '');
        }
    } catch { 
        detail.fefo_info = { error: 'Gagal memuat FEFO' };
        detail.original_stock = 0;
    }
}

function printLabel(id) {
    window.open(route('control.apoteker.compounding.label', id), '_blank', 'width=450,height=350');
}

function submit() {
    // We let backend handle final validation for consistency
    form.bahan_baku.forEach(item => {
        item.qty_neto_mass = calculateItemNeto(item);
    });
    form.post(route('control.apoteker.submissions.store'), { 
        preserveScroll: true,
        onSuccess: () => { 
            showModal.value = false; 
            form.reset(); 
            form.label.tanggal_racik = new Date().toISOString().split('T')[0];
            // Re-initialize with one empty item
            form.bahan_baku = [{ source_type: 'raw_material', id_batch: '', nama_bahan: '', qty_taken_pcs: '', qty_neto_mass: 0, original_stock: 0, fefo_info: null }]; 
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
        preserveScroll: true,
        onFinish: () => cancelModal.value = { show: false, id: null }
    });
}

const stats = computed(() => {
    const list = props.submissions?.data || [];
    const pending = list.filter(s => s.status === 'pending_final_verification').length;
    const active = list.filter(s => s.status === 'active').length;
    return { pending, active };
});

// Auto open modal if prefilling from prescription
if (prefillItemId) {
    showModal.value = true;
}
</script>

<template>
    <Head title="Log Racikan" />
    <div class="space-y-8">
        <!-- BENTO COCKPIT HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Tombol Buat Racikan (Desktop) -->
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="statusFilter = 'all'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="statusFilter === 'all' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="hidden sm:inline">Semua Sediaan</span>
                            <span class="sm:hidden">Semua</span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'pending_final_verification'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="statusFilter === 'pending_final_verification' 
                                ? 'bg-amber-500 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusFilter === 'pending_final_verification' ? 'bg-white' : 'bg-amber-500'"></span>
                            <span class="hidden sm:inline">Menunggu Verifikasi</span>
                            <span class="sm:hidden">Menunggu</span>
                            <span 
                                v-if="stats.pending > 0"
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="statusFilter === 'pending_final_verification' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
                            >{{ stats.pending }}</span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'active'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="statusFilter === 'active' 
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusFilter === 'active' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400'"></span>
                            <span>Aktif</span>
                        </button>

                        <button
                            type="button"
                            @click="statusFilter = 'rejected'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="statusFilter === 'rejected' 
                                ? 'bg-rose-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusFilter === 'rejected' ? 'bg-white' : 'bg-rose-500'"></span>
                            <span>Ditolak</span>
                        </button>
                    </div>

                    <button 
                        @click="showModal=true;form.reset();form.bahan_baku=[{source_type:'raw_material',id_batch:'',nama_bahan:'',qty_taken_pcs:'',qty_neto_mass:0,original_stock:0,fefo_info:null}];form.label={tanggal_racik:'',bud_hari:30,aturan_pakai:'',keterangan:''};form.clearErrors()" 
                        class="hidden sm:inline-flex h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span>Buat Racikan</span>
                    </button>
                </div>

                <!-- Baris 2: Search Input & Tombol Buat Racikan (Mobile) -->
                <div class="flex items-center gap-2 w-full">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari nama racikan, formula, ID batch..." 
                            class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button v-if="search" type="button" @click="search=''; performServerSearch()" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <button 
                        @click="showModal=true;form.reset();form.bahan_baku=[{source_type:'raw_material',id_batch:'',nama_bahan:'',qty_taken_pcs:'',qty_neto_mass:0,original_stock:0,fefo_info:null}];form.label={tanggal_racik:'',bud_hari:30,aturan_pakai:'',keterangan:''};form.clearErrors()" 
                        class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span>Buat</span>
                    </button>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Verifikasi Akhir -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            VERIFIKASI AKHIR
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.pending > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.pending > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.pending }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.pending > 0 ? 'Perlu konfirmasi mutu' : 'Tidak ada antrean' }}
                    </p>
                </div>

                <!-- Card 2: Racikan Siap -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            RACIKAN SIAP
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.active }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Sediaan aktif apotek
                    </p>
                </div>
            </div>
        </div>

        <!-- TABLE TOOLBAR -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-4">
            <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Daftar Riwayat Pengajuan Racikan
            </h2>
            <div class="flex items-center gap-2">
                <span class="text-xs text-muted-foreground font-medium">Bentuk Kemasan:</span>
                <select v-model="unitFilter" class="h-8 bg-card border border-input rounded-lg px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-colors cursor-pointer">
                    <option value="all">Semua Satuan</option>
                    <option value="Bungkus">Bungkus</option>
                    <option value="Kapsul">Kapsul</option>
                    <option value="Botol">Botol</option>
                    <option value="Tube">Tube</option>
                    <option value="Pot">Pot</option>
                </select>
                <button v-if="search || statusFilter !== 'all' || unitFilter !== 'all'" @click="clearFilters" class="h-8 px-2.5 text-xs font-medium border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5 rounded-lg cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </button>
            </div>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left border-collapse min-w-[880px]">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left min-w-[200px] whitespace-nowrap">Nama Racikan / Batch</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left min-w-[160px] whitespace-nowrap">Formula</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[70px] whitespace-nowrap">Items</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Target Hasil</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[120px] whitespace-nowrap">Tgl Racik / BUD</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[140px] whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[190px] whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!filteredSubmissions.length">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-12 h-12 bg-muted rounded-md flex items-center justify-center text-muted-foreground border border-border">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <span class="text-sm font-medium text-muted-foreground">Belum Ada Riwayat Pengajuan Racikan</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="s in filteredSubmissions" :key="s.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-medium text-foreground text-sm block">{{ s.nama_racikan || 'Tanpa Nama' }}</span>
                            <span class="text-xs text-muted-foreground tracking-wide mt-0.5 block">
                                {{ s.compounded_product?.batch_no || ('ID-' + s.id) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium text-muted-foreground">{{ s.formula?.nama_resep || '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-foreground text-sm">
                            {{ (s.details?.length || 0) }} 
                        </td>
                        <td class="px-4 py-3 text-center text-foreground font-medium text-sm">
                            {{ Number(s.compounded_product?.qty_hasil || s.target_quantity || s.expected_yield || 0).toLocaleString('id-ID') }} <span class="text-xs text-muted-foreground font-normal">{{ s.target_unit }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground text-xs font-medium">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-foreground" v-if="s.label?.tanggal_racik">{{ new Date(s.label.tanggal_racik).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                                <span class="text-red-500" v-if="s.label?.tanggal_kadaluarsa_bud">Exp: {{ new Date(s.label.tanggal_kadaluarsa_bud).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="statusClass(s.status)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block whitespace-nowrap">
                                {{ s.status === 'pending_final_verification' ? 'Menunggu Harga' : (s.status ? s.status.replace(/_/g, ' ') : 'unknown') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="printLabel(s.id)" title="Cetak Etiket Aturan Pakai Termal" class="text-foreground hover:text-foreground bg-background hover:bg-accent border border-input px-2.5 h-8 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5 shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    Etiket
                                </button>
                                <Link :href="route('control.apoteker.submissions.show', s.id)" class="inline-flex items-center justify-center text-foreground hover:text-primary bg-secondary hover:bg-secondary/80 border border-border px-3 h-8 rounded-md text-xs font-medium transition-colors">
                                    Detail
                                </Link>
                                <button v-if="s.status === 'pending_final_verification'" @click="confirmCancel(s.id)" class="text-red-600 dark:text-red-400 hover:bg-red-500/10 border border-red-500/20 px-3 h-8 rounded-md text-xs font-medium transition-colors">
                                    Batalkan
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="submissions?.links?.length > 3" class="px-4 py-3 bg-muted/20 border-t border-border flex items-center justify-between rounded-b-lg">
            <span class="text-xs text-muted-foreground font-medium">Halaman {{ submissions?.current_page || 1 }} dari {{ submissions?.last_page || 1 }} (Total {{ submissions?.total || 0 }} pengajuan)</span>
            <div class="flex items-center gap-1">
                <template v-for="(l, i) in (submissions?.links || [])" :key="i">
                    <Link 
                        v-if="l.url" 
                        :href="l.url"
                        preserve-state 
                        preserve-scroll 
                        :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                        v-html="l.label"
                    />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Create Modal (Complex Form) -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity" @click="showModal=false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-border">
                <div class="sticky top-0 z-10 px-6 py-4 border-b border-border bg-card flex items-center justify-between">
                    <h3 class="text-foreground font-semibold text-base">Submit Racikan Baru</h3>
                    <button @click="showModal=false" class="text-muted-foreground hover:text-foreground p-1 rounded-md transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-6 relative">
                    <!-- Loading Overlay -->
                    <div v-if="isLoadingReferenceData" class="absolute inset-0 bg-card/80 backdrop-blur-sm z-10 flex flex-col items-center justify-center gap-3">
                        <svg class="w-8 h-8 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm font-medium text-muted-foreground animate-pulse">Memuat data referensi bahan & resep...</p>
                    </div>
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
                    <div class="bg-muted/30 border border-border rounded-md p-3.5 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-foreground font-semibold text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 11v10"></path></svg>
                                Target Produksi & Hasil Jadi
                            </h4>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-muted text-foreground border border-border">
                                Target: {{ form.jumlah_unit || 0 }} {{ form.target_unit }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 whitespace-nowrap">Jumlah Jadi <span class="text-red-500">*</span></label>
                                <input v-model.number="form.jumlah_unit" type="number" min="1" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring" required />
                            </div>
                            <div class="sm:col-span-8">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Bentuk Sediaan / Satuan Kemasan <span class="text-red-500">*</span></label>
                                <select v-model="form.target_unit" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring" required>
                                    <option value="Kapsul">Kapsul (Serbuk Terbagi / Kapsul)</option>
                                    <option value="Puyer">Puyer (Serbuk Terbagi)</option>
                                    <option value="Botol">Botol (Larutan Oral / Sirup / Suspensi)</option>
                                    <option value="Pot">Pot (Salep / Krim / Semisolid)</option>
                                    <option value="Tube">Tube (Gel / Pasta Luar)</option>
                                    <option value="Sachet">Sachet</option>
                                </select>
                            </div>
                        </div>

                        <!-- Safety Information -->

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
                    <div class="bg-muted/30 border border-border rounded-md p-3.5 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <p class="text-foreground font-semibold text-sm">Daftar Bahan Racikan <span class="text-red-500">*</span></p>
                            <button type="button" @click="addDetail" class="inline-flex items-center gap-1 text-primary hover:text-primary/80 text-xs font-medium bg-background border border-border hover:bg-muted px-3 h-8 rounded-md transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Tambah Bahan
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="(d,i) in form.bahan_baku" :key="'detail-'+i" class="bg-card border border-border rounded-md p-3 sm:p-3.5 shadow-sm relative overflow-hidden group">
                                <!-- Overstock Alert Overlay -->
                                <div v-if="isOverstock(d)" class="absolute inset-0 bg-red-500/10 pointer-events-none border border-red-500 rounded-md z-20"></div>

                                <div class="flex flex-col md:flex-row gap-2.5 items-end relative z-10">
                                    <div class="w-full md:w-[220px] md:shrink-0">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 whitespace-nowrap">Tipe Bahan</label>
                                        <select v-model="d.source_type" @change="d.id_batch=''; d.fefo_info=null; d.original_stock=0;" class="w-full bg-background border border-input rounded-md h-9 px-2 text-foreground text-xs font-medium focus:ring-1 focus:ring-ring outline-none transition-colors">
                                            <option value="raw_material">Bahan Baku (Murni)</option>
                                            <option value="medicine">Obat Jadi (Tablet/Kapsul)</option>
                                        </select>
                                    </div>
                                    <div class="w-full md:flex-1 md:min-w-0">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 truncate">
                                            {{ d.source_type === 'medicine' ? 'Pilih Obat Jadi' : 'Pilih Bahan Baku' }}
                                        </label>
                                        <select v-if="d.source_type === 'medicine'" v-model="d.id_batch" @change="fetchFEFO(d)" class="w-full bg-background border border-input rounded-md h-9 px-2.5 text-foreground text-xs focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                            <option value="">Pilih Obat Jadi...</option>
                                            <option v-for="med in (medicines || [])" :key="med.id" :value="med.id">{{ med.nama_obat }} ({{ med.satuan_terkecil || med.satuan_stok || 'Pcs' }})</option>
                                        </select>
                                        <select v-else v-model="d.id_batch" @change="fetchFEFO(d)" class="w-full bg-background border border-input rounded-md h-9 px-2.5 text-foreground text-xs focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                            <option value="">Pilih Bahan Baku...</option>
                                            <option v-for="rm in (rawMaterials || [])" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }} ({{ rm.satuan_dasar_penggunaan || rm.satuan_ukur }})</option>
                                        </select>
                                    </div>
                                    <div class="w-full md:w-[140px] md:shrink-0">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 truncate" :title="'Qty Butuh' + (getItemUnit(d) ? ' (' + getItemUnit(d) + ')' : '')">
                                            Qty <span v-if="getItemUnit(d)" class="text-primary font-normal text-[10px] lowercase">({{ getItemUnit(d) }})</span>
                                        </label>
                                        <div class="relative flex items-center">
                                            <input 
                                                v-model.number="d.qty_taken_pcs" 
                                                type="number" 
                                                :step="d.source_type === 'medicine' ? '1' : 'any'" 
                                                min="0" 
                                                class="w-full bg-background border border-input rounded-md h-9 pl-2 pr-8 text-foreground text-xs font-semibold focus:ring-1 focus:ring-ring outline-none" 
                                                placeholder="0"
                                                required 
                                            />
                                            <div class="absolute right-2 text-[10px] font-bold text-muted-foreground pointer-events-none select-none uppercase">
                                                {{ getItemUnit(d) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="w-full md:w-9 md:shrink-0 flex justify-center pb-0.5">
                                        <button type="button" @click="removeDetail(i)" class="h-9 w-9 flex items-center justify-center text-muted-foreground hover:text-red-500 hover:bg-red-500/10 rounded-md transition-colors" title="Hapus bahan">
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
                                                <template v-if="d.fefo_info.empty">
                                                    <span class="text-rose-500 font-medium">Stok habis atau kadaluarsa (Silakan input stok baru)</span>
                                                </template>
                                                <template v-else-if="d.fefo_info.error">
                                                    <span class="text-rose-500">{{ d.fefo_info.error }}</span>
                                                </template>
                                                <template v-else>
                                                    Rekomendasi FEFO: <span class="bg-background border border-border px-1.5 py-0.5 rounded font-medium text-foreground tracking-wide">{{ d.fefo_info.batch_no }}</span> 
                                                    <span class="mx-1.5 text-muted-foreground">|</span> 
                                                    Exp: <strong class="text-foreground">{{ new Date(d.fefo_info.expired_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) }}</strong>
                                                    <span class="mx-1.5 text-muted-foreground">|</span> 
                                                    Sisa stok fisik: <strong :class="isOverstock(d) ? 'text-red-600 font-bold' : 'text-foreground'">{{ getRemaining(d) }}</strong>
                                                </template>
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
                    
                    <div class="mt-4 bg-amber-50 border border-amber-200/50 rounded-lg p-3 flex items-start gap-3">
                        <div class="mt-0.5 text-amber-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div class="text-xs text-amber-800">
                            <p class="font-semibold mb-0.5">Perhatian: Presisi Kuantitas Pengambilan Stok</p>
                            <p>Pastikan Anda memasukkan Qty butuh berdasarkan <strong>satuan dasar stok</strong> yang tercantum di dalam kotak input (bukan menggunakan fraksi satuan beli seperti "0.5 Kg"). Pemotongan fisik stok akan langsung memotong nilai tersebut.</p>
                        </div>
                    </div>
                    <!-- Label / Etiket -->
                    <div class="border-t border-border pt-4 space-y-3.5">
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

                        <!-- Peringatan USP <795> jika BUD melebihi ED bahan terdekat -->
                        <div v-if="isBudExceedingMaterialExpiry" class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800/50 rounded-md flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <div class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                                <strong class="font-semibold">Peringatan Standar Farmakope USP &lt;795&gt;:</strong>
                                Estimasi Beyond Use Date ({{ budDate }}) melebihi tanggal kedaluwarsa fisik terdekat dari bahan <strong>{{ earliestMaterialBatch.name }}</strong> ({{ new Date(earliestMaterialBatch.dateStr).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) }}). Harap kurangi durasi BUD hari agar tidak melampaui masa kedaluwarsa bahan aktif.
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Aturan Pakai <span class="text-red-500">*</span></label>
                            <input v-model="form.label.aturan_pakai" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 3 x sehari 1 bungkus sesudah makan" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Keterangan Tambahan</label>
                                <input v-model="form.label.keterangan" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Kocok dahulu, habiskan, dll..." />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 space-y-3">
                        <!-- Error Banner -->
                        <div v-if="$page.props.flash?.error || Object.keys(form.errors).length > 0" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-md p-3">
                            <div v-if="$page.props.flash?.error" class="text-red-600 dark:text-red-400 text-xs font-medium">
                                {{ $page.props.flash.error }}
                            </div>
                            <ul v-if="Object.keys(form.errors).length > 0" class="text-red-600 dark:text-red-400 text-[11px] font-medium list-disc list-inside mt-1 space-y-0.5">
                                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

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
