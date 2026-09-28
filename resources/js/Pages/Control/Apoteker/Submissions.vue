<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    submissions: Object, 
    formulas: Array, 
    rawMaterials: Array, 
    medicines: Array,
    filters: { type: Object, default: () => ({}) }
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const unitFilter = ref(props.filters?.unit || 'all');
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        search: search.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        unit: unitFilter.value !== 'all' ? unitFilter.value : undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { isSearching.value = false; }
    });
};

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

watch([statusFilter, unitFilter], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
    unitFilter.value = 'all';
    applyFilters();
};
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
        keterangan: '', 
        nama_pasien: '' 
    }
});

const rawMaterialsMap = computed(() => {
    const map = {};
    (props.rawMaterials || []).forEach(rm => {
        map[rm.id] = rm;
    });
    return map;
});

const medicinesMap = computed(() => {
    const map = {};
    (props.medicines || []).forEach(m => {
        map[m.id] = m;
    });
    return map;
});

const total_massa_target = computed(() => {
    return (Number(form.jumlah_unit) || 0) * (Number(form.berat_per_unit) || 0);
});

const calculateItemNeto = (item) => {
    const isMed = item.source_type === 'medicine';
    const density = isMed ? 1 : (Number(rawMaterialsMap.value[item.id_batch]?.density_factor) || 1);
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
    return rm?.satuan_ukur || 'g';
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
        if (d.source_type === 'medicine') return false;
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
    const formula = (props.formulas || []).find(f => f.id === newId);
    if (formula) {
        const kat = (formula.kategori_sediaan || '').toLowerCase();
        if (kat.includes('larutan') || kat.includes('sirup') || kat.includes('suspensi')) {
            form.label.bud_hari = 14;
            form.target_unit = 'Botol';
            form.berat_per_unit = 0; 
        } else if (kat.includes('salep') || kat.includes('krim') || kat.includes('semisolid') || kat.includes('pasta') || kat.includes('gel')) {
            form.label.bud_hari = 30;
            form.target_unit = 'Pot';
            form.berat_per_unit = 10;
        } else {
            form.label.bud_hari = 30;
            form.target_unit = 'Kapsul';
            form.berat_per_unit = 0.5;
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
    try {
        const { data } = await axios.get(route('control.apoteker.fefo', detail.id_batch) + `?type=${type}`);
        detail.fefo_info = data.suggestion;
        detail.original_stock = Number(data.suggestion?.stock_on_hand) || 0;
        detail.nama_bahan = type === 'medicine'
            ? (data.suggestion?.medicine?.nama_obat || '')
            : (data.suggestion?.raw_material?.nama_bahan || '');
    } catch { 
        detail.fefo_info = null; 
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
    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Log Pengajuan & Racikan Obat
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pencatatan riwayat compounding farmasi, perhitungan neraca massa bahan aktif, dan validasi mutu sediaan.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Erlenmeyer Lab Flask (behind left) -->
                            <rect x="12" y="4" width="14" height="4.5" rx="1.8" stroke="#059669" stroke-width="2" fill="#ECFDF5" />
                            <path d="M15 8.5v11L5 46.5a4 4 0 0 0 3.5 5.5h23a4 4 0 0 0 3.5-5.5L25 19.5V8.5" stroke="#059669" stroke-width="2.2" stroke-linejoin="round" fill="#FFFFFF" />
                            <path d="M8 39c3-1.5 6 1.5 10 0s6-.5 10 1l2 6.5a3 3 0 0 1-2.5 4H10a3 3 0 0 1-2.8-4l.8-7.5z" fill="#A7F3D0" fill-opacity="0.9" />
                            <line x1="16" y1="26" x2="20" y2="26" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                            <line x1="14" y1="31" x2="20" y2="31" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                            <!-- Tilted Capsule (interlocking in front right) -->
                            <g transform="translate(37, 33) rotate(28)">
                                <rect x="-7.5" y="-18" width="15" height="36" rx="7.5" stroke="#059669" stroke-width="2.2" fill="#FFFFFF" />
                                <path d="M-7.5 -10.5 A7.5 7.5 0 0 1 7.5 -10.5 V0 H-7.5 Z" fill="#10B981" />
                                <line x1="-7.5" y1="0" x2="7.5" y2="0" stroke="#059669" stroke-width="1.8" />
                            </g>
                        </svg>
                    </div>
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

        <!-- ACTION BAR -->
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                Daftar Riwayat Pengajuan Racikan
            </h2>
            <button @click="showModal=true;form.reset();form.bahan_baku=[{source_type:'raw_material',id_batch:'',nama_bahan:'',qty_taken_pcs:'',qty_neto_mass:0,original_stock:0,fefo_info:null}];form.label={tanggal_racik:'',bud_hari:30,aturan_pakai:'',keterangan:'',nama_pasien:''};form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Buat Racikan Baru
            </button>
        </div>

        <!-- FILTER BAR -->
        <div class="bg-card border border-border p-3 rounded-lg shadow-sm flex flex-wrap gap-3 items-center">
            <!-- Search Input -->
            <div class="flex-1 min-w-[220px] relative">
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Cari racikan, formula, atau apoteker..." 
                    class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-8 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" 
                />
                <svg v-if="!isSearching" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <svg v-else class="animate-spin w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <button 
                    v-if="search" 
                    @click="search = ''; applyFilters()" 
                    type="button" 
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer p-0.5 rounded-full hover:bg-muted transition-colors"
                    title="Hapus pencarian"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Status Dropdown -->
            <select v-model="statusFilter" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer">
                <option value="all">Semua Status</option>
                <option value="pending_final_verification">Menunggu Verifikasi</option>
                <option value="active">Aktif (Siap)</option>
                <option value="rejected">Ditolak</option>
                <option value="cancelled">Dibatalkan</option>
            </select>

            <!-- Unit Dropdown -->
            <select v-model="unitFilter" class="h-9 bg-background border border-input rounded-md px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer">
                <option value="all">Semua Satuan</option>
                <option value="Kapsul">Kapsul</option>
                <option value="Puyer">Puyer</option>
                <option value="Botol">Botol</option>
                <option value="Pot">Pot</option>
                <option value="Tube">Tube</option>
            </select>

            <!-- Reset Button -->
            <button v-if="search || statusFilter !== 'all' || unitFilter !== 'all'" @click="clearFilters" class="h-9 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 rounded-md transition-colors cursor-pointer">
                Reset
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left border-collapse min-w-[880px]">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left min-w-[200px] whitespace-nowrap">Nama Racikan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left min-w-[160px] whitespace-nowrap">Formula</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[70px] whitespace-nowrap">Items</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[120px] whitespace-nowrap">BUD</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[140px] whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[190px] whitespace-nowrap">Aksi</th>
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
                                <button @click="printLabel(s.id)" title="Cetak Etiket Aturan Pakai Termal" class="text-emerald-700 dark:text-emerald-300 hover:text-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 px-2.5 h-8 rounded-md text-xs font-medium transition-colors flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
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
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 whitespace-nowrap">Jumlah Unit <span class="text-red-500">*</span></label>
                                <input v-model.number="form.jumlah_unit" type="number" min="1" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring" required />
                            </div>
                            <div class="sm:col-span-5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Satuan</label>
                                <select v-model="form.target_unit" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring" required>
                                    <option value="Kapsul">Kapsul (Serbuk Terbagi / Kapsul)</option>
                                    <option value="Puyer">Puyer (Serbuk Terbagi)</option>
                                    <option value="Botol">Botol (Larutan Oral / Sirup / Suspensi)</option>
                                    <option value="Pot">Pot (Salep / Krim / Semisolid)</option>
                                    <option value="Tube">Tube (Gel / Pasta Luar)</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 whitespace-nowrap">
                                    Berat/Unit (g)
                                    <span v-if="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2" class="text-red-500 ml-1" title="Peringatan: Berat melebihi kapasitas rata-rata cangkang kapsul (Max 1.2g)">⚠️</span>
                                </label>
                                <input v-model.number="form.berat_per_unit" type="number" step="0.01" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring transition-colors" :class="form.target_unit === 'Kapsul' && form.berat_per_unit > 1.2 ? 'border-red-500 text-red-500' : ''" placeholder="Target berat" />
                            </div>
                            <div class="sm:col-span-3 bg-background border border-border rounded-md p-2 flex flex-col justify-center text-center">
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

                                <div class="flex flex-col md:flex-row gap-2.5 items-end relative z-10">
                                    <div class="w-full md:w-[190px] md:shrink-0">
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
                                            <option v-for="rm in (rawMaterials || [])" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }} ({{ rm.satuan_ukur }})</option>
                                        </select>
                                    </div>
                                    <div class="w-full md:w-[80px] md:shrink-0">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 truncate" :title="'Qty Butuh' + (getItemUnit(d) ? ' (' + getItemUnit(d) + ')' : '')">
                                            Qty <span v-if="getItemUnit(d)" class="text-primary font-normal text-[10px] lowercase">({{ getItemUnit(d) }})</span>
                                        </label>
                                        <input 
                                            v-model.number="d.qty_taken_pcs" 
                                            type="number" 
                                            :step="d.source_type === 'medicine' ? '1' : 'any'" 
                                            min="0" 
                                            class="w-full bg-background border border-input rounded-md h-9 pl-2 pr-1 text-foreground text-xs font-semibold focus:ring-1 focus:ring-ring outline-none" 
                                            placeholder="0"
                                            required 
                                        />
                                    </div>
                                    <div class="w-full md:w-[80px] md:shrink-0">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 whitespace-nowrap" title="Massa Neto Bahan (Gram)">
                                            Massa (g)
                                        </label>
                                        <div class="w-full bg-muted border border-border rounded-md h-9 px-2 text-foreground font-semibold text-xs flex items-center justify-between">
                                            <span>{{ calculateItemNeto(d).toFixed(2) }}</span>
                                            <span class="text-muted-foreground font-normal text-[10px]">g</span>
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
