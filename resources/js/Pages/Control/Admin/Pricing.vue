<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { toast } from '@/Components/ui/toast';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

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
        },
        onError: (errs) => {
            formErrors.value = errs;
            const firstMsg = Object.values(errs)[0];
            if (firstMsg) {
                toast.error('Peringatan Margin', { description: firstMsg });
            }
        }
    });
};

const submitCompoundedPrice = (product) => {
    if (!product.temp_harga_jual || product.temp_harga_jual < 1) {
        toast.warning('Peringatan', { description: 'Harga jual harus lebih dari 0' });
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

    <div class="space-y-8">
            <!-- SECTION 1: RACIKAN -->
            <div>
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-foreground flex items-center gap-2">
                        Racikan Menunggu Penetapan Harga
                        <span class="bg-amber-500/10 text-amber-600 border border-amber-500/20 text-xs font-semibold px-2 py-0.5 rounded-md">{{ pendingProducts.length }}</span>
                    </h2>
                    <p class="text-sm text-muted-foreground mt-1">Produk racikan yang sudah selesai diajukan namun belum memiliki harga jual.</p>
                </div>
                
                <div class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <Table class="w-full min-w-[850px]">
                        <TableHeader>
                            <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                                <TableHead class="min-w-[220px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Nama Produk / Batch</TableHead>
                                <TableHead class="min-w-[120px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">HPP Bahan Baku</TableHead>
                                <TableHead class="min-w-[100px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Jasa Racik</TableHead>
                                <TableHead class="min-w-[100px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Kemasan</TableHead>
                                <TableHead class="min-w-[90px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Margin (%)</TableHead>
                                <TableHead class="min-w-[130px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Harga Jual Baru</TableHead>
                                <TableHead class="min-w-[90px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="pendingProducts.length === 0">
                                <TableCell colspan="7" class="py-8 px-3 text-center text-muted-foreground text-xs">Semua produk racikan sudah ditetapkan harganya.</TableCell>
                            </TableRow>
                            <TableRow v-for="product in pendingProducts" :key="product.id" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                                <TableCell class="py-2 px-3 align-middle break-words">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-foreground leading-snug break-words">{{ product.nama_produk }}</span>
                                        <div class="flex items-center gap-1.5 mt-0.5 text-xs">
                                            <span class="font-medium text-foreground/80">{{ product.batch_no }}</span>
                                            <span class="text-muted-foreground/40">·</span>
                                            <span class="text-muted-foreground">Qty: {{ Number(product.qty_hasil).toLocaleString('id-ID') }} {{ product.satuan }}</span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right">
                                    <span class="text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400 block">Rp {{ Number(product.calculated_hpp).toLocaleString('id-ID') }}</span>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right">
                                    <input type="number" v-model="product.jasa_racik" @input="calculateHargaJual(product)" class="w-full h-7 text-xs text-right border border-input bg-background rounded px-2 focus:border-primary focus:ring-1 focus:ring-primary font-medium tabular-nums" placeholder="0">
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right">
                                    <input type="number" v-model="product.biaya_kemasan" @input="calculateHargaJual(product)" class="w-full h-7 text-xs text-right border border-input bg-background rounded px-2 focus:border-primary focus:ring-1 focus:ring-primary font-medium tabular-nums" placeholder="0">
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-center">
                                    <input type="number" v-model="product.margin_percentage" @input="calculateHargaJual(product)" class="w-full h-7 text-xs text-center border border-input bg-background rounded px-2 focus:border-primary focus:ring-1 focus:ring-primary font-medium tabular-nums" placeholder="0">
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right">
                                    <input type="number" v-model="product.temp_harga_jual" class="w-full h-7 text-xs text-right border border-input bg-background rounded px-2 focus:border-primary focus:ring-1 focus:ring-primary font-medium tabular-nums" placeholder="Input harga">
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-center">
                                    <button @click="submitCompoundedPrice(product)" class="bg-primary hover:bg-primary/90 text-primary-foreground font-medium h-7 px-3 rounded text-xs transition-colors w-full shadow-sm">Tetapkan</button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: OBAT JADI -->
            <div class="pt-6 border-t border-border">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-foreground">Harga Obat Jadi</h2>
                    <p class="text-sm text-muted-foreground mt-1">Atur harga jual (selling price) untuk obat jadi. Harga ini akan jadi default di Kasir.</p>
                </div>

                <div class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
                    <!-- TOOLBAR: FILTER & SEARCH -->
                    <div class="p-4 border-b border-border bg-muted/30 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="filterStatus = 'all'" 
                                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
                                    :class="filterStatus === 'all' ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-background border border-input text-muted-foreground hover:bg-accent'">
                                <span>Semua Obat</span>
                                <span class="px-1.5 py-0.5 rounded-md text-xs" :class="filterStatus === 'all' ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-foreground'">{{ (medicines || []).length }}</span>
                            </button>

                            <button @click="filterStatus = 'loss'" 
                                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
                                    :class="filterStatus === 'loss' ? 'bg-destructive text-destructive-foreground shadow-sm' : 'bg-background border border-destructive/30 text-destructive hover:bg-destructive/10'">
                                <span>⚠️ Jual Rugi</span>
                                <span class="px-1.5 py-0.5 rounded-md text-xs" :class="filterStatus === 'loss' ? 'bg-destructive-foreground/20 text-destructive-foreground' : 'bg-destructive/10 text-destructive'">{{ countLoss }}</span>
                            </button>

                            <button @click="filterStatus = 'unpriced'" 
                                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
                                    :class="filterStatus === 'unpriced' ? 'bg-amber-600 text-white shadow-sm' : 'bg-background border border-amber-500/30 text-amber-600 hover:bg-amber-500/10'">
                                <span>Belum Lengkap</span>
                                <span class="px-1.5 py-0.5 rounded-md text-xs" :class="filterStatus === 'unpriced' ? 'bg-white/20 text-white' : 'bg-amber-500/10 text-amber-600'">{{ countUnpriced }}</span>
                            </button>
                        </div>

                        <!-- Search Input -->
                        <div class="relative w-full md:w-72">
                            <input type="text" v-model="searchQuery" 
                                   placeholder="Cari nama atau kode obat..." 
                                   class="w-full text-xs pl-9 pr-8 h-9 bg-background border border-input rounded-md focus:border-emerald-500 focus:ring-emerald-500 transition-colors font-medium">
                            <svg class="w-4 h-4 text-muted-foreground absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2.5 text-muted-foreground hover:text-foreground text-xs font-bold">✕</button>
                        </div>
                    </div>

                    <!-- TABLE CONTENT -->
                    <div class="overflow-x-auto">
                        <Table class="w-full min-w-[890px]">
                            <TableHeader class="sticky top-0 bg-muted/90 backdrop-blur z-10">
                                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                                    <TableHead class="min-w-[240px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Nama Obat</TableHead>
                                    <TableHead class="min-w-[160px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-left whitespace-nowrap">Kemasan</TableHead>
                                    <TableHead class="min-w-[140px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">HPP Modal (Netto)</TableHead>
                                    <TableHead class="min-w-[150px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right tabular-nums whitespace-nowrap">Harga Jual Saat Ini</TableHead>
                                    <TableHead class="min-w-[110px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Margin (%)</TableHead>
                                    <TableHead class="min-w-[90px] py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center whitespace-nowrap">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="filteredMedicines.length === 0">
                                    <TableCell colspan="6" class="py-8 px-3 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-8 h-8 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-xs font-medium">Tidak ada data obat yang sesuai dengan filter atau pencarian.</span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="med in filteredMedicines" :key="med.id" class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                    <!-- NAMA OBAT -->
                                    <TableCell class="py-2.5 px-3 align-top break-words">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-foreground leading-snug break-words">{{ med.nama_obat }}</span>
                                            <div class="flex flex-wrap gap-1.5 items-center mt-1 text-xs text-muted-foreground leading-tight">
                                                <span v-if="med.kode_obat">{{ med.kode_obat }}</span>
                                                <span v-if="med.kode_obat && med.latest_batch_no" class="text-muted-foreground/40">·</span>
                                                <span v-if="med.latest_batch_no">{{ med.latest_batch_no }}</span>
                                                <span v-if="isMedicineLoss(med)" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                    Jual Rugi
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- KEMASAN -->
                                    <TableCell class="py-2.5 px-3 align-top">
                                        <div class="flex flex-col gap-1">
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center gap-1.5 whitespace-nowrap">
                                                <span class="text-sm font-medium text-foreground">{{ med.satuan_besar }}</span>
                                                <span class="text-xs text-muted-foreground font-normal">(Tersier)</span>
                                            </div>
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center gap-1.5 whitespace-nowrap">
                                                <span class="text-sm font-medium text-foreground">{{ med.satuan_menengah }}</span>
                                                <span class="text-xs text-muted-foreground font-normal">(Sekunder)</span>
                                                <span v-if="med.mid_to_big_ratio" class="text-xs text-muted-foreground font-normal">(1={{ med.mid_to_big_ratio }})</span>
                                            </div>
                                            <div v-if="med.base_unit" class="h-6 flex items-center gap-1.5 whitespace-nowrap">
                                                <span class="text-sm font-medium text-foreground">{{ med.base_unit }}</span>
                                                <span class="text-xs text-muted-foreground font-normal">(Primer)</span>
                                                <span v-if="med.small_to_mid_ratio && med.satuan_menengah" class="text-xs text-muted-foreground font-normal">(1={{ med.small_to_mid_ratio }})</span>
                                                <span v-else-if="med.small_to_mid_ratio && !med.satuan_menengah && med.satuan_besar" class="text-xs text-muted-foreground font-normal">(1={{ med.small_to_mid_ratio }})</span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center text-muted-foreground text-xs italic">
                                                Belum diset
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- HPP MODAL (NETTO) -->
                                    <TableCell class="py-2.5 px-3 align-top text-right">
                                        <div class="flex flex-col gap-1">
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-end text-sm text-foreground font-medium tabular-nums">
                                                <span v-if="med.hpp_besar > 0">{{ formatCurrency(med.hpp_besar) }}</span>
                                                <span v-else class="text-muted-foreground/40">-</span>
                                            </div>
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-end text-sm text-foreground font-medium tabular-nums">
                                                <span v-if="med.hpp_menengah > 0">{{ formatCurrency(med.hpp_menengah) }}</span>
                                                <span v-else class="text-muted-foreground/40">-</span>
                                            </div>
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-end text-sm text-foreground font-medium tabular-nums">
                                                <span v-if="med.hpp_kecil > 0">{{ formatCurrency(med.hpp_kecil) }}</span>
                                                <span v-else class="text-muted-foreground/40">-</span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-end text-xs text-muted-foreground/40">-</div>
                                        </div>
                                    </TableCell>

                                    <!-- HARGA JUAL SAAT INI -->
                                    <TableCell class="py-2.5 px-3 align-top text-right">
                                        <div class="flex flex-col gap-1">
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-end text-sm tabular-nums">
                                                <span v-if="med.selling_price_large > 0" class="text-foreground font-medium">{{ formatCurrency(med.selling_price_large) }}</span>
                                                <span v-else class="text-xs text-amber-600 dark:text-amber-400 italic font-normal">Belum diset</span>
                                            </div>
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-end text-sm tabular-nums">
                                                <span v-if="med.selling_price_mid > 0" class="text-foreground font-medium">{{ formatCurrency(med.selling_price_mid) }}</span>
                                                <span v-else class="text-xs text-amber-600 dark:text-amber-400 italic font-normal">Belum diset</span>
                                            </div>
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-end text-sm tabular-nums">
                                                <span v-if="med.selling_price > 0" class="text-foreground font-medium">{{ formatCurrency(med.selling_price) }}</span>
                                                <span v-else class="text-xs text-amber-600 dark:text-amber-400 italic font-normal">Belum diset</span>
                                            </div>
                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-end text-xs text-muted-foreground/40">-</div>
                                        </div>
                                    </TableCell>

                                    <!-- MARGIN (%) -->
                                    <TableCell class="py-2.5 px-3 align-top text-center">
                                        <div class="flex flex-col gap-1">
                                            <!-- Margin Tersier -->
                                            <div v-if="med.satuan_besar" class="h-6 flex items-center justify-center">
                                                <span v-if="getMarginBadge(med.selling_price_large, med.hpp_besar)"
                                                      class="text-xs font-semibold px-1.5 py-0.5 rounded border inline-flex items-center gap-1 tabular-nums"
                                                      :class="getMarginBadge(med.selling_price_large, med.hpp_besar).cls">
                                                    <span>{{ getMarginBadge(med.selling_price_large, med.hpp_besar).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price_large, med.hpp_besar).isLoss" class="text-[10px] font-bold text-destructive">RUGI</span>
                                                </span>
                                                <span v-else class="text-xs text-muted-foreground/40 italic">-</span>
                                            </div>

                                            <!-- Margin Sekunder -->
                                            <div v-if="med.satuan_menengah" class="h-6 flex items-center justify-center">
                                                <span v-if="getMarginBadge(med.selling_price_mid, med.hpp_menengah)"
                                                      class="text-xs font-semibold px-1.5 py-0.5 rounded border inline-flex items-center gap-1 tabular-nums"
                                                      :class="getMarginBadge(med.selling_price_mid, med.hpp_menengah).cls">
                                                    <span>{{ getMarginBadge(med.selling_price_mid, med.hpp_menengah).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price_mid, med.hpp_menengah).isLoss" class="text-[10px] font-bold text-destructive">RUGI</span>
                                                </span>
                                                <span v-else class="text-xs text-muted-foreground/40 italic">-</span>
                                            </div>

                                            <!-- Margin Primer -->
                                            <div v-if="med.base_unit" class="h-6 flex items-center justify-center">
                                                <span v-if="getMarginBadge(med.selling_price, med.hpp_kecil)"
                                                      class="text-xs font-semibold px-1.5 py-0.5 rounded border inline-flex items-center gap-1 tabular-nums"
                                                      :class="getMarginBadge(med.selling_price, med.hpp_kecil).cls">
                                                    <span>{{ getMarginBadge(med.selling_price, med.hpp_kecil).text }}</span>
                                                    <span v-if="getMarginBadge(med.selling_price, med.hpp_kecil).isLoss" class="text-[10px] font-bold text-destructive">RUGI</span>
                                                </span>
                                                <span v-else class="text-xs text-muted-foreground/40 italic">-</span>
                                            </div>

                                            <div v-if="!med.satuan_besar && !med.satuan_menengah && !med.base_unit" class="h-6 flex items-center justify-center text-xs text-muted-foreground/40">-</div>
                                        </div>
                                    </TableCell>

                                    <!-- AKSI -->
                                    <TableCell class="py-2.5 px-3 align-middle text-center">
                                        <button @click="openPriceModal(med)" 
                                                class="bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 font-medium h-7 px-2.5 rounded text-xs transition-colors inline-flex items-center justify-center gap-1 w-full max-w-[80px]">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                            Atur
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL ATUR HARGA -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="closePriceModal"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md overflow-hidden flex flex-col border border-border">
                <div class="p-6 border-b border-border bg-muted/30">
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Atur Harga Jual</h3>
                    <p class="text-xs text-muted-foreground mt-1" v-if="selectedMedicine">Obat: <span class="font-semibold text-foreground">{{ selectedMedicine.nama_obat }}</span></p>
                </div>
                
                <div class="p-6 space-y-4 flex-1 overflow-y-auto">
                    <!-- Dynamic Packaging Tiers (Tersier / Sekunder / Primer) -->
                    <div v-for="t in modalTiers" :key="t.field" class="p-4 bg-muted/30 rounded-lg border border-border">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-foreground">Harga Kemasan {{ t.tierName }} ({{ t.unit }})</label>
                            <span v-if="t.hpp > 0" class="text-xs text-muted-foreground font-medium">
                                Modal HPP: Rp {{ Number(t.hpp).toLocaleString('id-ID') }}
                            </span>
                        </div>
                        <input type="number" v-model="priceForm[t.field]" 
                               class="w-full h-9 text-sm border border-input bg-background rounded-md focus:border-emerald-500 focus:ring-emerald-500 font-semibold px-3" 
                               :class="{'border-destructive focus:ring-destructive': formErrors[t.field]}" 
                               placeholder="0">
                        
                        <!-- Live Margin Indicator -->
                        <div v-if="getMarginBadge(priceForm[t.field], t.hpp)" class="mt-2 flex items-center gap-2">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-md border inline-flex items-center gap-1"
                                  :class="getMarginBadge(priceForm[t.field], t.hpp).modalCls">
                                <span>Margin: {{ getMarginBadge(priceForm[t.field], t.hpp).text }}</span>
                                <span v-if="getMarginBadge(priceForm[t.field], t.hpp).isOver">⚠️ Melebihi batas 300%!</span>
                                <span v-else-if="getMarginBadge(priceForm[t.field], t.hpp).isLoss">⚠️ Jual rugi!</span>
                                <span v-else>✓ Wajar</span>
                            </span>
                        </div>
                        <p v-if="formErrors[t.field]" class="text-destructive text-xs font-medium mt-1.5 leading-tight">
                            {{ formErrors[t.field] }}
                        </p>
                    </div>
                    
                    <div v-if="modalTiers.length === 0" class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-lg text-amber-600 text-sm">
                        <p class="font-semibold mb-1">Informasi Kemasan Belum Lengkap</p>
                        <p class="text-xs">Obat ini belum memiliki informasi kemasan dari faktur masuk.</p>
                        <div class="mt-3">
                            <label class="block text-xs font-semibold text-foreground mb-1">Harga Satuan Default</label>
                            <input type="number" v-model="priceForm.selling_price" class="w-full h-9 text-sm border border-input rounded-md focus:border-emerald-500 focus:ring-emerald-500 font-semibold bg-background px-3" placeholder="0">
                        </div>
                    </div>
                </div>
                
                <div class="p-4 sm:p-6 bg-muted/30 border-t border-border flex gap-3">
                    <button @click="closePriceModal" class="flex-1 bg-background border border-input text-foreground hover:bg-accent font-medium h-9 rounded-md text-sm transition-colors">Batal</button>
                    <button @click="submitMedicinePrices" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium h-9 rounded-md text-sm transition-colors shadow-sm">Simpan Harga</button>
                </div>
            </div>
            </div>
        </Teleport>
</template>

