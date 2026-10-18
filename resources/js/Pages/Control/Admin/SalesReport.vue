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
const activeSummary = computed(() => props.summary || { total_items: 0, total_amount: 0, total_modal: 0, total_laba: 0 });

const averageMargin = computed(() => {
    const amount = Number(activeSummary.value.total_amount) || 0;
    const laba = Number(activeSummary.value.total_laba) || 0;
    if (amount <= 0) return 0;
    return Math.round((laba / amount) * 100);
});

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
    <!-- UNIFIED FILTER & EXPORT ACTION BAR -->
    <div class="bg-card rounded-lg border border-border p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Sisi Kiri: Filter Rentang Waktu Kompak -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider shrink-0 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-muted-foreground shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Rentang Waktu:
            </span>
            <div class="flex items-center gap-2">
                <input 
                    type="date" 
                    v-model="startDate" 
                    class="h-9.5 px-3 rounded-md border border-input bg-background text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500 transition-colors cursor-pointer" 
                    title="Dari Tanggal"
                />
                <span class="text-sm text-muted-foreground font-semibold">-</span>
                <input 
                    type="date" 
                    v-model="endDate" 
                    class="h-9.5 px-3 rounded-md border border-input bg-background text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500 transition-colors cursor-pointer" 
                    title="Sampai Tanggal"
                />
                <svg v-if="isFiltering" class="animate-spin h-4 w-4 text-emerald-600 shrink-0 ml-1" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Sisi Kanan: Tombol Ekspor PDF & Excel (Ukuran Standar, rounded-md) -->
        <div class="flex items-center gap-2.5 shrink-0">
            <button 
                @click="exportToPdf" 
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white text-sm font-medium rounded-md transition-colors shadow-sm cursor-pointer whitespace-nowrap"
                title="Ekspor Laporan PDF"
            >
                <!-- Official Adobe PDF Icon -->
                <svg class="w-4 h-4 shrink-0 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7.5 2h9l5.5 5.5v12.5a2 2 0 0 1-2 2h-12.5a2 2 0 0 1-2-2v-16a2 2 0 0 1 2-2zm8 1.5v4.5h4.5l-4.5-4.5zm-8.5 7.5v6.5h1.5v-2.25h1.25a2.125 2.125 0 0 0 0-4.25h-2.75zm1.5 1.5h1.25a.625.625 0 0 1 0 1.25h-1.25v-1.25zm4.5-1.5v6.5h2.25a2.5 2.5 0 0 0 2.5-2.5v-1.5a2.5 2.5 0 0 0-2.5-2.5h-2.25zm1.5 1.5h.75a1 1 0 0 1 1 1v1.5a1 1 0 0 1-1 1h-.75v-3.5zm5.5-1.5v6.5h1.5v-2.5h2v-1.5h-2v-1h2.25v-1.5h-3.75z"/>
                </svg>
                <span>Export PDF</span>
            </button>
            <button 
                @click="exportToExcel" 
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-sm font-medium rounded-md transition-colors shadow-sm cursor-pointer whitespace-nowrap"
                title="Ekspor Laporan Excel"
            >
                <!-- Official Microsoft Excel Icon -->
                <svg class="w-4 h-4 shrink-0 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M21.17 3.25Q21.5 3.25 21.76 3.5 22 3.74 22 4.08V19.92Q22 20.26 21.76 20.5 21.5 20.75 21.17 20.75H7.83Q7.5 20.75 7.24 20.5 7 20.26 7 19.92V17H2.83Q2.5 17 2.24 16.76 2 16.5 2 16.17V7.83Q2 7.5 2.24 7.24 2.5 7 2.83 7H7V4.08Q7 3.74 7.24 3.5 7.5 3.25 7.83 3.25M7 13H5.5L4.5 11.2 3.5 13H2L3.75 10 2 7H3.5L4.5 8.8 5.5 7H7L5.25 10ZM13 5H9V8H13ZM19 5H15V8H19ZM13 10H9V14H13ZM19 10H15V14H19ZM13 16H9V19H13ZM19 16H15V19H19Z"/>
                </svg>
                <span>Export Excel</span>
            </button>
        </div>
    </div>

    <!-- 5 STAT CARDS (PROPORSIONAL, GRID 5 KOLOM) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Card 1: Total Item Terjual -->
        <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-center">
            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Item Terjual</p>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ activeSummary.total_items }} <span class="text-sm font-medium text-muted-foreground">PCS</span></p>
        </div>

        <!-- Card 2: Total Omzet -->
        <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-center">
            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Omzet</p>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ formatCurrency(activeSummary.total_amount) }}</p>
        </div>

        <!-- Card 3: Total Modal (HPP) -->
        <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-center">
            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Modal (HPP)</p>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ formatCurrency(activeSummary.total_modal || 0) }}</p>
        </div>

        <!-- Card 4: Total Laba -->
        <div class="bg-card p-5 rounded-lg border border-emerald-200/60 dark:border-emerald-800/40 bg-emerald-50/20 dark:bg-emerald-950/10 shadow-sm flex flex-col justify-center">
            <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-2">Total Laba</p>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ formatCurrency(activeSummary.total_laba) }}</p>
        </div>

        <!-- Card 5: Rata-Rata Margin -->
        <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-center">
            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Rata-Rata Margin</p>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ averageMargin }}%</p>
        </div>
    </div>

    <!-- Table Data -->
    <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <Table class="w-full min-w-[940px] xl:min-w-full">
                <TableHeader>
                    <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-10 min-w-10 whitespace-nowrap">No</TableHead>
                        <TableHead class="py-2.5 px-2 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[170px] whitespace-nowrap">Produk & Kategori</TableHead>
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-16 min-w-16 whitespace-nowrap">Satuan</TableHead>
                        <TableHead class="py-2.5 px-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[85px] whitespace-nowrap">HPP Modal</TableHead>
                        <TableHead class="py-2.5 px-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[85px] whitespace-nowrap">Harga Jual</TableHead>
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-14 min-w-14 whitespace-nowrap">Qty Awal</TableHead>
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-14 min-w-14 whitespace-nowrap">Terjual</TableHead>
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-14 min-w-14 whitespace-nowrap">Sisa Stok</TableHead>
                        <TableHead class="py-2.5 px-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[95px] whitespace-nowrap">Total Omzet</TableHead>
                        <TableHead class="py-2.5 px-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[95px] whitespace-nowrap">Laba Bersih</TableHead>
                        <TableHead class="py-2.5 px-2 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider w-16 min-w-16 whitespace-nowrap">Margin %</TableHead>
                        <TableHead class="py-2.5 px-2 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider w-20 min-w-20 whitespace-nowrap">Status</TableHead>
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
                            <TableCell class="py-2.5 px-2 align-middle text-center text-muted-foreground text-xs font-medium tabular-nums whitespace-nowrap">{{ (activeMedicines.current_page - 1) * activeMedicines.per_page + index + 1 }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle min-w-[170px]">
                                <div class="flex flex-col">
                                    <span class="font-medium text-sm text-foreground leading-tight">{{ med.nama_produk }}</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-xs text-muted-foreground">{{ med.kode_obat || '-' }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground border border-border/60 whitespace-nowrap">
                                            {{ med.kategori || 'Obat Jadi' }}
                                        </span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-center text-foreground font-medium text-sm whitespace-nowrap">{{ med.satuan }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-right text-foreground font-medium tabular-nums text-sm whitespace-nowrap">{{ formatCurrency(med.modal) }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-right text-foreground font-medium tabular-nums text-sm whitespace-nowrap">{{ formatCurrency(med.harga_jual) }}</TableCell>
                            
                            <TableCell class="py-2.5 px-2 align-middle text-center text-foreground font-medium tabular-nums text-sm whitespace-nowrap">{{ med.qty_awal }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-center font-semibold text-primary tabular-nums text-sm whitespace-nowrap">{{ med.terjual }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-center text-foreground font-medium tabular-nums text-sm whitespace-nowrap">{{ med.sisa_stok }}</TableCell>
                            
                            <TableCell class="py-2.5 px-2 align-middle text-right font-medium text-foreground tabular-nums text-sm whitespace-nowrap">{{ formatCurrency(med.total_penjualan) }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums text-sm whitespace-nowrap">{{ formatCurrency(med.laba) }}</TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-right tabular-nums text-sm font-medium whitespace-nowrap" :class="med.margin_percentage >= 20 ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">
                                {{ med.margin_percentage }}%
                            </TableCell>
                            <TableCell class="py-2.5 px-2 align-middle text-center whitespace-nowrap">
                                <span v-if="med.status === 'Aman'" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aman
                                </span>
                                <span v-else-if="med.status === 'Menipis'" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Menipis
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20 whitespace-nowrap">
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
