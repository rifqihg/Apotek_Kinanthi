<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    medicines: Object,
    filters: Object,
    summary: Object
});

const isFiltering = ref(false);
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

// Keep date inputs in sync if filters prop changes
watch(() => props.filters, (newFilters) => {
    if (newFilters?.start_date !== undefined) startDate.value = newFilters.start_date || '';
    if (newFilters?.end_date !== undefined) endDate.value = newFilters.end_date || '';
}, { deep: true });

const applyDateFilter = () => {
    if (!startDate.value || !endDate.value) return;
    isFiltering.value = true;
    router.get(route(route().current()), {
        start_date: startDate.value,
        end_date: endDate.value,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isFiltering.value = false;
        },
    });
};

watch([startDate, endDate], () => {
    applyDateFilter();
});

const activeMedicines = computed(() => props.medicines || { data: [], links: [] });
const activeSummary = computed(() => props.summary || { total_items: 0, total_amount: 0, total_laba: 0 });



const exportToExcel = () => {
    const url = new URL(route('control.admin.reports.sales.export'));
    if (startDate.value) url.searchParams.append('start_date', startDate.value);
    if (endDate.value) url.searchParams.append('end_date', endDate.value);
    window.open(url.toString(), '_blank');
};

const exportToPdf = () => {
    const url = new URL(route('control.admin.reports.sales.export-pdf'));
    if (startDate.value) url.searchParams.append('start_date', startDate.value);
    if (endDate.value) url.searchParams.append('end_date', endDate.value);
    window.open(url.toString(), '_blank');
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};
</script>

<template>
<Head title="Laporan Penjualan (Rekap Produk)" />

<div class="space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-foreground">Laporan Penjualan</h2>
            <p class="text-muted-foreground text-sm mt-1">Rekapitulasi penjualan berdasarkan produk obat.</p>
        </div>

        <div class="flex items-center gap-3">
            <button @click="exportToPdf" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Export PDF
            </button>
            <button @click="exportToExcel" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Excel
            </button>
        </div>
    </div>

    <!-- Filters & Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Date Filters -->
        <div class="md:col-span-4 bg-card border border-border p-4 rounded-lg shadow-sm">
            <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3 flex items-center justify-between">
                <span>Filter Periode</span>
                <svg v-if="isFiltering" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Mulai</label>
                    <input type="date" v-model="startDate" class="w-full border-input bg-background rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 shadow-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Sampai</label>
                    <input type="date" v-model="endDate" class="w-full border-input bg-background rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 shadow-sm" />
                </div>
            </div>
        </div>

        <div class="md:col-span-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-card border border-border p-4 rounded-lg shadow-sm flex flex-col justify-center">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Total Item Terjual</p>
                <p class="text-2xl font-bold tracking-tight text-foreground">{{ activeSummary.total_items }} <span class="text-sm font-medium text-muted-foreground">PCS</span></p>
            </div>
            <div class="bg-card border border-border p-4 rounded-lg shadow-sm flex flex-col justify-center">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Total Penjualan</p>
                <p class="text-xl font-bold tracking-tight text-foreground">{{ formatCurrency(activeSummary.total_amount) }}</p>
            </div>
            <div class="bg-card border border-border p-4 rounded-lg shadow-sm flex flex-col justify-center">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Total Laba</p>
                <p class="text-xl font-bold tracking-tight text-emerald-600">{{ formatCurrency(activeSummary.total_laba) }}</p>
            </div>
        </div>
    </div>

    <!-- Table Data -->
    <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-12">No</TableHead>
                        <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Produk & Kategori</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Satuan</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">HPP Modal</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Harga Jual</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Qty Awal</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Terjual</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sisa Stok</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Omzet</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Laba Kotor</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Margin %</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!activeMedicines.data.length">
                        <TableCell colspan="12" class="py-8 px-3 text-center">
                            <p class="text-muted-foreground text-xs font-medium">Tidak ada data obat.</p>
                        </TableCell>
                    </TableRow>
                    <template v-for="(med, index) in activeMedicines.data" :key="med.id">
                        <TableRow class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors">
                            <TableCell class="py-2 px-3 align-middle text-center text-muted-foreground text-xs font-medium tabular-nums">{{ (activeMedicines.current_page - 1) * activeMedicines.per_page + index + 1 }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle">
                                <div class="flex flex-col">
                                    <span class="font-medium text-sm text-foreground leading-tight">{{ med.nama_produk }}</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-xs text-muted-foreground">{{ med.kode_obat || '-' }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground border border-border/60">
                                            {{ med.kategori || 'Obat Jadi' }}
                                        </span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center text-foreground font-medium text-sm">{{ med.satuan }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right text-foreground font-medium tabular-nums text-sm">{{ formatCurrency(med.modal) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right text-foreground font-medium tabular-nums text-sm">{{ formatCurrency(med.harga_jual) }}</TableCell>
                            
                            <TableCell class="py-2 px-3 align-middle text-center text-foreground font-medium tabular-nums text-sm">{{ med.qty_awal }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center font-semibold text-primary tabular-nums text-sm">{{ med.terjual }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center text-foreground font-medium tabular-nums text-sm">{{ med.sisa_stok }}</TableCell>
                            
                            <TableCell class="py-2 px-3 align-middle text-right font-medium text-foreground tabular-nums text-sm">{{ formatCurrency(med.total_penjualan) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums text-sm">{{ formatCurrency(med.laba) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right tabular-nums text-sm font-medium" :class="med.margin_percentage >= 20 ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">
                                {{ med.margin_percentage }}%
                            </TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center">
                                <span v-if="med.status === 'Aman'" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aman
                                </span>
                                <span v-else-if="med.status === 'Menipis'" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Menipis
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Habis
                                </span>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
            
            <!-- PAGINATION -->
            <div v-if="activeMedicines.data && activeMedicines.data.length > 0" class="px-6 py-4 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">
                    Halaman {{ activeMedicines.current_page }} dari {{ activeMedicines.last_page }}
                </span>
                
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in activeMedicines.links" :key="i">
                        <Link 
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                                link.active ? 'bg-primary text-primary-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:bg-muted',
                            ]"
                        />
                        <span 
                            v-else
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 text-xs font-medium rounded-md opacity-50 cursor-not-allowed',
                                'text-muted-foreground'
                            ]"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
