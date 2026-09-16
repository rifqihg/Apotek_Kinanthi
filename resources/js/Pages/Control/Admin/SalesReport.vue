<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    medicines: Object,
    filters: Object,
    summary: Object
});

const page = usePage();
const activePage = ref(1);

const form = ref({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || ''
});

// Reset page when date filter changes
watch(form, () => {
    activePage.value = 1;
}, { deep: true });

const { data: queryReport, isFetching } = useQuery({
    queryKey: computed(() => ['admin-sales-report', form.value.start_date, form.value.end_date, activePage.value]),
    queryFn: async () => {
        const res = await axios.get(route('control.admin.reports.sales'), {
            params: {
                start_date: form.value.start_date,
                end_date: form.value.end_date,
                page: activePage.value,
            },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        return {
            medicines: res.data.props.medicines,
            summary: res.data.props.summary,
        };
    },
    initialData: () => ({
        medicines: props.medicines,
        summary: props.summary,
    }),
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (prev) => prev,
});

const activeMedicines = computed(() => queryReport.value?.medicines || props.medicines || { data: [], links: [] });
const activeSummary = computed(() => queryReport.value?.summary || props.summary || { total_items: 0, total_amount: 0, total_laba: 0 });

const goToPage = (url) => {
    if (!url) return;
    try {
        const urlObj = new URL(url, window.location.origin);
        const p = urlObj.searchParams.get('page');
        if (p) activePage.value = Number(p);
    } catch (e) {
        // fallback
    }
};

const exportToExcel = () => {
    const url = new URL(route('control.admin.reports.sales.export'));
    if (form.value.start_date) url.searchParams.append('start_date', form.value.start_date);
    if (form.value.end_date) url.searchParams.append('end_date', form.value.end_date);
    window.location.href = url.toString();
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
                <svg v-if="isFetching" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Mulai</label>
                    <input type="date" v-model="form.start_date" class="w-full border-input bg-background rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 shadow-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Sampai</label>
                    <input type="date" v-model="form.end_date" class="w-full border-input bg-background rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 shadow-sm" />
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
                        <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Produk</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Satuan</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Modal</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Harga Jual</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Qty Awal</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Terjual</TableHead>
                        <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sisa Stok</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Penjualan</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Modal</TableHead>
                        <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Laba</TableHead>
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
                            <TableCell class="py-2 px-3 align-middle text-center text-muted-foreground text-xs font-mono">{{ (activeMedicines.current_page - 1) * activeMedicines.per_page + index + 1 }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-foreground font-medium text-sm">{{ med.nama_produk }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center text-muted-foreground text-xs">{{ med.satuan }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right text-muted-foreground tabular-nums text-xs">{{ formatCurrency(med.modal) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right text-foreground font-medium tabular-nums text-sm">{{ formatCurrency(med.harga_jual) }}</TableCell>
                            
                            <TableCell class="py-2 px-3 align-middle text-center text-muted-foreground tabular-nums text-xs">{{ med.qty_awal }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center font-semibold text-foreground tabular-nums text-sm">{{ med.terjual }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center text-muted-foreground tabular-nums text-xs">{{ med.sisa_stok }}</TableCell>
                            
                            <TableCell class="py-2 px-3 align-middle text-right font-medium text-foreground tabular-nums text-sm">{{ formatCurrency(med.total_penjualan) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right text-muted-foreground tabular-nums text-xs">{{ formatCurrency(med.total_modal) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums text-sm">{{ formatCurrency(med.laba) }}</TableCell>
                            <TableCell class="py-2 px-3 align-middle text-center">
                                <span v-if="med.status === 'Tersedia'" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Tersedia
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Pesan
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
                    <button v-for="(link, i) in activeMedicines.links" :key="i"
                        type="button"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                            link.active ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted',
                            !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>
</div>
</template>
