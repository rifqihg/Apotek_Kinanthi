<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    materials: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const editingMaterial = ref(null);

const clearSearch = () => {
    search.value = '';
};

const activeFilterTab = ref('all'); // 'all' | 'critical' | 'safe'

const getMatStock = (m) => Number(m?.stok_total ?? m?.total_quantity ?? 0);
const isMatCritical = (m) => getMatStock(m) <= (Number(m?.threshold_min) || 0);

const stats = computed(() => {
    const list = props.materials?.data || [];
    const total = props.materials?.total ?? list.length;
    const critical = list.filter(isMatCritical).length;
    const safe = list.filter(m => !isMatCritical(m)).length;
    return { total, critical, safe };
});

const filteredMaterials = computed(() => {
    let list = props.materials?.data || [];
    if (activeFilterTab.value === 'critical') {
        list = list.filter(isMatCritical);
    } else if (activeFilterTab.value === 'safe') {
        list = list.filter(m => !isMatCritical(m));
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(m =>
            m.nama_bahan?.toLowerCase().includes(q) ||
            m.kode_bahan?.toLowerCase().includes(q) ||
            m.bentuk_sediaan?.toLowerCase().includes(q) ||
            m.golongan_bahan?.toLowerCase().includes(q)
        );
    }
    return list;
});

const farmasiTaksonomi = {
    'Padat (Solida)': { 
        satuan: ['Kg', 'Gram', 'mg', 'mcg', 'IU', 'Zack', 'Box', 'Strip', 'Tablet', 'Kapsul']
    },
    'Cair (Liquida)': { 
        satuan: ['Liter', 'mL', 'cc', 'drop/tetes', 'Galon', 'Botol', 'Vial', 'Ampul']
    },
    'Semisolid': { 
        satuan: ['Tube', 'Pot', 'Gram', 'mg']
    }
};

const form = useForm({
    nama_bahan: '',
    kode_bahan: '',
    golongan_bahan: '',
    bentuk_sediaan: '',
    suhu_penyimpanan: '',
    satuan_pembelian: '',
    satuan_dasar_penggunaan: '',
    rasio_konversi: 1,
    expected_quantity: '',
    batch_no: '',
    expired_date: '',
    harga_beli_satuan: '',
    threshold_min: '',
    deskripsi: '',
});

const availableSatuan = computed(() => {
    return farmasiTaksonomi[form.bentuk_sediaan]?.satuan || [];
});

watch(() => form.bentuk_sediaan, (newBentuk, oldBentuk) => {
    if (newBentuk !== oldBentuk) {
        form.satuan_pembelian = '';
        form.satuan_dasar_penggunaan = '';
        form.rasio_konversi = 1;
    }
});

const metricWeights = {
    'Kg': 1000000,
    'Gram': 1000,
    'mg': 1,
    'mcg': 0.001,
    'Liter': 1000,
    'mL': 1,
    'cc': 1,
};

const isRatioLocked = ref(false);
const autoRatioMsg = ref('');

watch([() => form.satuan_pembelian, () => form.satuan_dasar_penggunaan], ([beli, pakai], [oldBeli, oldPakai]) => {
    if (!beli || !pakai) {
        isRatioLocked.value = false;
        autoRatioMsg.value = '';
        return;
    }

    const weightBeli = metricWeights[beli];
    const weightPakai = metricWeights[pakai];

    if (weightBeli !== undefined && weightPakai !== undefined) {
        const rasio = weightBeli / weightPakai;
        if (rasio > 0) {
            form.rasio_konversi = rasio;
            isRatioLocked.value = true;
            autoRatioMsg.value = '(Otomatis)';
        }
    } else {
        isRatioLocked.value = false;
        autoRatioMsg.value = '(Manual)';
        
        // Reset if it was previously locked to force user to type the new manual ratio
        if ((oldBeli !== beli || oldPakai !== pakai) && metricWeights[oldBeli] && metricWeights[oldPakai]) {
            form.rasio_konversi = '';
        }
    }
});

const formatStokBerjenjang = (stokDasar, rasio, satuanBeli, satuanPakai) => {
    if (!stokDasar) return `0 ${satuanPakai || ''}`;
    const qtyBase = Number(stokDasar);
    const ratio = Number(rasio);
    
    if (!ratio || ratio <= 1 || !satuanBeli) return `${qtyBase.toLocaleString('id-ID')} ${satuanPakai || ''}`;
    
    const valueBeli = Math.floor(qtyBase / ratio);
    const valuePakai = qtyBase % ratio;
    
    let result = '';
    if (valueBeli > 0) result += `${valueBeli.toLocaleString('id-ID')} ${satuanBeli}`;
    
    if (valuePakai > 0) {
        if (result.length > 0) result += ' ';
        const remainderStr = valuePakai % 1 === 0 ? valuePakai.toLocaleString('id-ID') : valuePakai.toLocaleString('id-ID', { maximumFractionDigits: 4 });
        result += `${remainderStr} ${satuanPakai}`;
    }
    
    return result || `0 ${satuanPakai}`;
};

const openAdd = () => {
    editingMaterial.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (m) => {
    editingMaterial.value = m;
    form.nama_bahan = m.nama_bahan;
    form.kode_bahan = m.kode_bahan || '';
    form.golongan_bahan = m.golongan_bahan || '';
    form.bentuk_sediaan = m.bentuk_sediaan || '';
    form.suhu_penyimpanan = m.suhu_penyimpanan || '';
    form.satuan_pembelian = m.satuan_pembelian || '';
    form.satuan_dasar_penggunaan = m.satuan_dasar_penggunaan || '';
    form.rasio_konversi = m.rasio_konversi || 1;
    form.harga_beli_satuan = m.harga_beli_satuan || '';
    form.threshold_min = m.threshold_min || '';
    form.deskripsi = m.deskripsi || '';
    // Non-editable after creation:
    form.expected_quantity = '';
    form.batch_no = '';
    form.expired_date = '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingMaterial.value = null;
};

const submitForm = () => {
    if (editingMaterial.value) {
        form.put(route('control.admin.raw-materials.update', editingMaterial.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.post(route('control.admin.raw-materials.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
};

const deleteMaterial = (m) => {
    if (!confirm(`Hapus bahan baku "${m.nama_bahan}"? Tindakan ini tidak dapat diurungkan.`)) return;
    router.delete(route('control.admin.raw-materials.destroy', m.id), { preserveScroll: true });
};
</script>

<template>
<Head title="Data Bahan Baku" />
<div class="space-y-6">
    <!-- BENTO COCKPIT HEADER -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- SISI KIRI: KONSOL OPERASIONAL (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between gap-2.5">
            <!-- Baris 1: Filter Tabs (Pills) & Tombol Desktop -->
            <div class="flex items-center justify-between gap-2 w-full">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    <button
                        type="button"
                        @click="activeFilterTab = 'all'"
                        class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'all' 
                            ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span>Semua Bahan</span>
                        <span 
                            v-if="stats.total" 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'all' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ stats.total }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'critical'"
                        class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'critical' 
                            ? 'bg-amber-500 text-white shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <svg class="w-3.5 h-3.5" :class="activeFilterTab === 'critical' ? 'text-white' : 'text-amber-500'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Stok Kritis</span>
                        <span 
                            v-if="stats.critical" 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'critical' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'"
                        >{{ stats.critical }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'safe'"
                        class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'safe' 
                            ? 'bg-emerald-600 text-white shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <svg class="w-3.5 h-3.5" :class="activeFilterTab === 'safe' ? 'text-white' : 'text-emerald-500'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Siap Racik</span>
                        <span 
                            v-if="stats.safe" 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'safe' ? 'bg-white/20 text-white' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300'"
                        >{{ stats.safe }}</span>
                    </button>
                </div>

                <!-- Action Button Desktop -->
                <button 
                    @click="openAdd"
                    class="hidden sm:inline-flex h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center justify-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Bahan</span>
                </button>
            </div>

            <!-- Baris 2: Search Input & Mobile Action Button -->
            <div class="flex items-center gap-2 w-full">
                <div class="relative flex-1">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        type="text" 
                        v-model="search" 
                        placeholder="Cari nama atau kode bahan baku..." 
                        class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all"
                    />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                        <button 
                            v-if="search" 
                            type="button" 
                            @click="clearSearch" 
                            class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button 
                    @click="openAdd"
                    class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs flex items-center justify-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah</span>
                </button>
            </div>
        </div>

        <!-- SISI KANAN: 2 STAT CARDS (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
            <!-- Card 1: Stok Kritis -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        STOK KRITIS
                    </span>
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" :class="stats.critical > 0 ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted text-slate-400 dark:text-muted-foreground'">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.critical > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                        {{ stats.critical }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    {{ stats.critical > 0 ? 'Perlu restock segera' : 'Semua stok aman' }}
                </p>
            </div>

            <!-- Card 2: Siap Racik -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        SIAP RACIK
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ stats.safe }}
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Stok aman tersedia
                </p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <Table class="w-full min-w-[980px]">
            <TableHeader>
                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                    <TableHead class="w-12 min-w-12 text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">NO</TableHead>
                    <TableHead class="min-w-[220px] text-left py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Nama Bahan</TableHead>
                    <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Golongan</TableHead>
                    <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Satuan Beli</TableHead>
                    <TableHead class="min-w-[130px] text-right tabular-nums py-2 px-3 pr-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Harga Beli</TableHead>
                    <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Stok (Dasar)</TableHead>
                    <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Threshold</TableHead>
                    <TableHead class="min-w-[110px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Status Stok</TableHead>
                    <TableHead class="min-w-[90px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-if="!filteredMaterials || filteredMaterials.length === 0">
                    <TableCell colspan="9" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">
                        {{ activeFilterTab !== 'all' ? 'Tidak ada bahan baku yang sesuai dengan filter.' : 'Belum ada bahan baku terdaftar.' }}
                    </TableCell>
                </TableRow>
                <TableRow v-for="(m, index) in filteredMaterials" :key="m.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                    <!-- NO -->
                    <TableCell class="py-2.5 px-3 text-center align-middle text-xs font-medium text-muted-foreground tabular-nums">
                        {{ ((materials.current_page || 1) - 1) * (materials.per_page || 15) + index + 1 }}
                    </TableCell>
                    <!-- NAMA BAHAN -->
                    <TableCell class="py-2.5 px-3 break-words whitespace-normal align-middle">
                        <div class="flex flex-col">
                            <span class="font-medium text-foreground text-sm leading-snug break-words">{{ m.nama_bahan }}</span>
                            <span class="text-[10px] text-muted-foreground leading-tight mt-0.5">{{ m.kode_bahan || 'No Code' }} &bull; {{ m.bentuk_sediaan || '-' }}</span>
                        </div>
                    </TableCell>
                    <!-- GOLONGAN -->
                    <TableCell class="py-2.5 px-3 text-center text-xs font-medium align-middle">
                        <span class="px-2 py-0.5 rounded-full border" 
                            :class="{
                                'bg-red-50 text-red-600 border-red-200': m.golongan_bahan === 'Keras' || m.golongan_bahan === 'Narkotika' || m.golongan_bahan === 'Psikotropika',
                                'bg-blue-50 text-blue-600 border-blue-200': m.golongan_bahan === 'Bebas Terbatas',
                                'bg-green-50 text-green-600 border-green-200': m.golongan_bahan === 'Bebas' || !m.golongan_bahan,
                            }">
                            {{ m.golongan_bahan || 'Bebas' }}
                        </span>
                    </TableCell>
                    <!-- SATUAN BELI -->
                    <TableCell class="py-2.5 px-3 text-center text-sm font-medium text-foreground uppercase align-middle">
                        {{ m.satuan_pembelian || m.satuan_dasar_penggunaan }}
                    </TableCell>
                    <!-- HARGA BELI -->
                    <TableCell class="py-2.5 px-3 pr-4 text-right font-medium text-foreground text-sm tabular-nums align-middle">
                        Rp {{ Number(m.harga_beli_satuan || 0).toLocaleString('id-ID') }}
                    </TableCell>
                    <!-- STOK TOTAL -->
                    <TableCell class="py-2.5 px-3 text-center align-middle">
                        <div class="flex flex-col items-center justify-center">
                            <span :class="Number(m.stok_total) < Number(m.threshold_min) ? 'text-destructive font-semibold' : 'text-foreground font-medium'" class="text-sm">
                                {{ formatStokBerjenjang(m.stok_total, m.rasio_konversi, m.satuan_pembelian, m.satuan_dasar_penggunaan) }}
                            </span>
                            <span class="text-[10px] text-muted-foreground mt-0.5" v-if="m.rasio_konversi > 1">Total Base: {{ Number(m.stok_total).toLocaleString('id-ID') }} {{ m.satuan_dasar_penggunaan || 'g' }}</span>
                        </div>
                    </TableCell>
                    <!-- THRESHOLD -->
                    <TableCell class="py-2.5 px-3 text-center align-middle tabular-nums">
                        <div class="flex flex-col items-center justify-center">
                            <span class="text-sm font-medium text-foreground">
                                {{ (m.threshold_min / (m.rasio_konversi || 1)).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ m.satuan_pembelian || m.satuan_dasar_penggunaan }}
                            </span>
                            <span class="text-[10px] text-muted-foreground mt-0.5" v-if="m.rasio_konversi > 1">Base: {{ Number(m.threshold_min).toLocaleString('id-ID') }} {{ m.satuan_dasar_penggunaan || 'g' }}</span>
                        </div>
                    </TableCell>
                    <!-- STATUS STOK -->
                    <TableCell class="py-2.5 px-3 text-center align-middle whitespace-nowrap">
                        <span :class="Number(m.stok_total) < Number(m.threshold_min) ? 'text-destructive' : 'text-emerald-600'" class="text-xs font-medium inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full" :class="Number(m.stok_total) < Number(m.threshold_min) ? 'bg-destructive' : 'bg-emerald-500'"></span>
                            {{ Number(m.stok_total) < Number(m.threshold_min) ? 'Kritis' : 'Aman' }}
                        </span>
                    </TableCell>
                    <!-- AKSI -->
                    <TableCell class="py-2.5 px-3 text-center align-middle">
                        <div class="flex items-center justify-center gap-1">
                            <button @click="openEdit(m)" class="h-7 w-7 rounded-md bg-background border border-input hover:bg-accent text-muted-foreground hover:text-foreground transition-colors flex items-center justify-center" title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="deleteMaterial(m)" class="h-7 w-7 rounded-md bg-background border border-input hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors flex items-center justify-center" title="Hapus">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination Footer -->
        <div v-if="materials.links && materials.links.length > 3" class="px-5 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
            <p class="text-xs font-medium text-muted-foreground">Halaman {{ materials.current_page || 1 }} dari {{ materials.last_page || 1 }} (Total {{ materials.total || 0 }} bahan baku)</p>
            <div class="flex items-center gap-1">
                <template v-for="(link, i) in materials.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll 
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add / Edit -->
<Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-card rounded-lg shadow-lg w-full max-w-lg overflow-hidden border border-border">
            <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">{{ editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru' }}</h3>
                    <p class="text-xs text-muted-foreground">{{ editingMaterial ? 'Perbarui data master bahan baku.' : 'Daftarkan bahan baku baru. Stok awal langsung aktif.' }}</p>
                </div>
                <button @click="closeModal" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form @submit.prevent="submitForm" class="p-6 space-y-4 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-2 gap-4">
                    <!-- BLOK 1: Identitas Dasar -->
                    <div class="col-span-2 pt-1">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Identitas & Klasifikasi</p>
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Nama Bahan Baku <span class="text-destructive">*</span></label>
                        <input v-model="form.nama_bahan" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: Paracetamol murni">
                        <p v-if="form.errors.nama_bahan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.nama_bahan }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Kode Bahan</label>
                        <input v-model="form.kode_bahan" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Golongan</label>
                        <select v-model="form.golongan_bahan" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                            <option value="">Belum Diklasifikasi</option>
                            <option value="Bebas">Bebas</option>
                            <option value="Bebas Terbatas">Bebas Terbatas</option>
                            <option value="Keras">Keras (G)</option>
                            <option value="Prekursor">Prekursor</option>
                            <option value="Narkotika">Narkotika</option>
                            <option value="Psikotropika">Psikotropika</option>
                        </select>
                        <p v-if="form.errors.golongan_bahan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.golongan_bahan }}</p>
                    </div>

                    <!-- BLOK 2: Parameter Fisik & Klinis -->
                    <div class="col-span-2 pt-3 border-t border-border mt-2">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Parameter Fisik & Penyimpanan</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Bentuk Sediaan <span class="text-destructive">*</span></label>
                        <select v-model="form.bentuk_sediaan" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                            <option value="" disabled>Pilih Sediaan...</option>
                            <option v-for="key in Object.keys(farmasiTaksonomi)" :key="key" :value="key">{{ key }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Suhu Penyimpanan</label>
                        <select v-model="form.suhu_penyimpanan" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                            <option value="">Pilih Suhu...</option>
                            <option value="Suhu Ruang (15-30°C)">Suhu Ruang (15-30°C)</option>
                            <option value="Sejuk (8-15°C)">Sejuk (8-15°C)</option>
                            <option value="Dingin (2-8°C)">Dingin (2-8°C)</option>
                        </select>
                    </div>

                    <!-- BLOK 3: Mesin Konversi & Ekonomi -->
                    <div class="col-span-2 pt-3 border-t border-border mt-2">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Konversi Satuan & Ekonomi</p>
                    </div>
                    <div class="col-span-2 grid grid-cols-3 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Satuan Beli <span class="text-destructive">*</span></label>
                            <select v-model="form.satuan_pembelian" :disabled="!form.bentuk_sediaan" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground disabled:opacity-50">
                                <option value="" disabled>Pilih Satuan...</option>
                                <option v-for="unit in availableSatuan" :key="unit" :value="unit">{{ unit }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Satuan Pakai (Base) <span class="text-destructive">*</span></label>
                            <select v-model="form.satuan_dasar_penggunaan" :disabled="!form.bentuk_sediaan" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground disabled:opacity-50">
                                <option value="" disabled>Pilih Satuan Pakai...</option>
                                <option v-for="unit in availableSatuan" :key="unit" :value="unit">{{ unit }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Rasio Konversi <span v-if="autoRatioMsg" :class="isRatioLocked ? 'text-emerald-600' : 'text-amber-600'" class="text-[10px] ml-1 lowercase font-bold">{{ autoRatioMsg }}</span><span v-else class="text-destructive">*</span></label>
                            <input v-model="form.rasio_konversi" type="number" min="1" step="any" :readonly="isRatioLocked" required :class="['w-full h-9 px-3 border border-input rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors', isRatioLocked ? 'bg-muted opacity-80 cursor-not-allowed border-transparent font-bold' : 'bg-background hover:border-primary']" placeholder="Contoh: 100">
                        </div>
                    </div>
                    
                    <div v-if="form.satuan_pembelian && form.satuan_dasar_penggunaan && form.rasio_konversi" class="col-span-2 text-xs font-medium text-emerald-600 bg-emerald-50 border border-emerald-100 p-2 rounded-md">
                        Info: 1 {{ form.satuan_pembelian }} = {{ form.rasio_konversi }} {{ form.satuan_dasar_penggunaan }}
                    </div>
                    
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Harga Beli / Satuan Beli <span class="text-destructive">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-sm text-muted-foreground font-medium">Rp</span>
                            <input v-model="form.harga_beli_satuan" type="number" required min="0" class="w-full h-9 pl-9 pr-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                        </div>
                    </div>

                    <!-- BLOK 4: Data Fisik / Stok Awal -->
                    <template v-if="!editingMaterial">
                        <div class="col-span-2 pt-3 border-t border-border mt-2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Data Fisik / Stok Awal</p>
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Qty Stok Awal (Satuan Beli) <span class="text-destructive">*</span></label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.expected_quantity" type="number" required min="0" class="flex-1 h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                                <span v-if="form.satuan_pembelian" class="text-xs font-medium text-muted-foreground uppercase bg-muted px-3 py-2 rounded-md border border-input">{{ form.satuan_pembelian }}</span>
                            </div>
                            <p v-if="form.expected_quantity && form.rasio_konversi" class="text-[10px] font-medium text-emerald-600 mt-1">Stok akan tercatat di sistem sebagai: {{ form.expected_quantity * form.rasio_konversi }} {{ form.satuan_dasar_penggunaan }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">No. Batch</label>
                            <input v-model="form.batch_no" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional (auto-generate)">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Tanggal Kadaluarsa <span class="text-destructive">*</span></label>
                            <input v-model="form.expired_date" type="date" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                        </div>
                    </template>

                    <!-- BLOK 5: Aturan Sistem & Catatan -->
                    <div class="col-span-2 pt-3 border-t border-border mt-2">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Aturan Sistem & Catatan</p>
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Minimum Stok (Threshold) <span class="text-destructive">*</span></label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.threshold_min" type="number" required min="0" class="flex-1 h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Dalam satuan dasar...">
                            <span v-if="form.satuan_dasar_penggunaan" class="text-xs font-medium text-muted-foreground uppercase bg-muted px-3 py-2 rounded-md border border-input">{{ form.satuan_dasar_penggunaan }}</span>
                        </div>
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Deskripsi / Catatan Tambahan</label>
                        <textarea v-model="form.deskripsi" rows="2" class="w-full p-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors resize-none" placeholder="Opsional"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <button type="button" @click="closeModal" class="h-9 px-4 rounded-md border border-input bg-background text-foreground text-sm font-medium hover:bg-accent transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm transition-colors disabled:opacity-50 shadow-sm">
                        {{ form.processing ? 'Menyimpan...' : (editingMaterial ? 'Simpan Perubahan' : 'Tambah Bahan Baku') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</Teleport>
</template>
