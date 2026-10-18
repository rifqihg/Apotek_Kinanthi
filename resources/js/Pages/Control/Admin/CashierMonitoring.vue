<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    filters: Object,
    kpis: Object,
    transactions: [Array, Object],
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

const expandedRows = ref({});
const toggleRow = (id) => { expandedRows.value[id] = !expandedRows.value[id]; };

const activeKpis = computed(() => props.kpis || {});

const rawTransactions = computed(() => props.transactions || []);
const activeTransactions = computed(() => {
    const t = rawTransactions.value;
    if (Array.isArray(t)) return t;
    return t?.data || [];
});

const activePagination = computed(() => {
    const t = rawTransactions.value;
    return (t && !Array.isArray(t) && t.links) ? t : null;
});



const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

const exportToExcel = () => {
    const url = new URL(route('control.admin.reports.cashier-monitoring.export'));
    if (startDate.value) url.searchParams.append('start_date', startDate.value);
    if (endDate.value) url.searchParams.append('end_date', endDate.value);
    window.open(url.toString(), '_blank');
};

const exportToPdf = () => {
    const url = new URL(route('control.admin.reports.cashier-monitoring.export-pdf'));
    if (startDate.value) url.searchParams.append('start_date', startDate.value);
    if (endDate.value) url.searchParams.append('end_date', endDate.value);
    window.open(url.toString(), '_blank');
};
</script>

<template>
    <Head title="Monitoring Transaksi" />

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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Transaksi</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ activeKpis.total_transactions || 0 }}</p>
                <p v-if="activeKpis.total_void > 0" class="text-[11px] text-destructive mt-1 font-medium">{{ activeKpis.total_void }} void/batal</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Omzet</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ formatRupiah(activeKpis.total_sales || activeKpis.total_omset) }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-emerald-200/60 dark:border-emerald-800/40 bg-emerald-50/20 dark:bg-emerald-950/10 shadow-sm">
                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-2">Setoran Tunai (Laci)</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ formatRupiah(activeKpis.total_tunai) }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-sky-200/60 dark:border-sky-800/40 bg-sky-50/20 dark:bg-sky-950/10 shadow-sm">
                <p class="text-xs font-semibold text-sky-700 dark:text-sky-400 uppercase tracking-wider mb-2">Non-Tunai (QRIS/Trf)</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-sky-600 dark:text-sky-400">{{ formatRupiah(activeKpis.total_non_tunai) }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Diskon</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-destructive">{{ formatRupiah(activeKpis.total_discount) }}</p>
            </div>
        </div>

        <!-- Tabel Riwayat Transaksi -->
        <div class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
            <div class="p-5 border-b border-border">
                <h2 class="text-lg font-bold text-foreground">Jurnal Transaksi Kasir & Rekonsiliasi Kas</h2>
                <p class="text-xs text-muted-foreground mt-0.5">Audit akuntabilitas transaksi, setoran tunai laci, dan pembatalan nota.</p>
            </div>
            <div class="overflow-x-auto">
                <Table class="w-full min-w-[1070px]">
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="w-10 min-w-10 py-2 px-3 text-center"></TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[130px] whitespace-nowrap">Waktu</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[180px] whitespace-nowrap">No. Faktur & Kasir</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Metode Bayar</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[110px] whitespace-nowrap">Subtotal</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[90px] whitespace-nowrap">Diskon</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[110px] whitespace-nowrap">Total Bayar</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[110px] whitespace-nowrap">Uang Diterima</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[100px] whitespace-nowrap">Kembalian</TableHead>
                            <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[90px] whitespace-nowrap">Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="data in activeTransactions" :key="data.id">
                            <!-- Main Row -->
                            <TableRow @click="toggleRow(data.id)" class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors cursor-pointer group">
                                <TableCell class="py-2 px-3 align-middle text-center">
                                    <div class="w-5 h-5 rounded bg-muted/60 group-hover:bg-muted flex items-center justify-center text-muted-foreground group-hover:text-foreground transition-colors mx-auto">
                                        <svg :class="{'rotate-180': expandedRows[data.id]}" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-sm font-medium text-foreground tabular-nums">{{ formatDate(data.created_at) }}</TableCell>
                                <TableCell class="py-2 px-3 align-middle">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-foreground leading-tight">{{ data.sale_number }}</span>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-muted text-muted-foreground border border-border/60 uppercase">
                                                {{ data.sale_type }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-muted-foreground leading-tight mt-0.5">Kasir: {{ data.kasir_name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-center">
                                    <span v-if="data.payment_method === 'tunai'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Tunai
                                    </span>
                                    <span v-else-if="data.payment_method === 'qris'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                        QRIS
                                    </span>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 capitalize">
                                        {{ data.payment_method || 'Transfer' }}
                                    </span>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right font-medium text-foreground text-sm tabular-nums">{{ formatRupiah(data.subtotal) }}</TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right tabular-nums">
                                    <span v-if="data.discount_amount > 0" class="font-medium text-destructive text-sm tabular-nums">
                                        {{ formatRupiah(data.discount_amount) }}
                                    </span>
                                    <span v-else class="text-sm font-medium text-foreground tabular-nums">Rp0</span>
                                </TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right font-semibold text-foreground text-sm tabular-nums">{{ formatRupiah(data.total_amount) }}</TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right text-sm font-medium text-foreground tabular-nums">{{ formatRupiah(data.paid_amount) }}</TableCell>
                                <TableCell class="py-2 px-3 align-middle text-right text-sm font-medium text-foreground tabular-nums">{{ formatRupiah(data.change_amount) }}</TableCell>
                                <TableCell class="py-2 px-3 align-middle text-center">
                                    <span v-if="data.status === 'paid'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Lunas
                                    </span>
                                    <div v-else class="flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                            Void / Batal
                                        </span>
                                        <span v-if="data.notes" class="text-[10px] text-muted-foreground mt-0.5 max-w-[130px] truncate" :title="data.notes">
                                            {{ data.notes }}
                                        </span>
                                    </div>
                                </TableCell>
                            </TableRow>
                            
                            <!-- Expanded Details Row -->
                            <TableRow v-show="expandedRows[data.id]" class="bg-muted/20 border-b border-border/40">
                                <TableCell colspan="10" class="p-0 border-t-0">
                                    <div class="p-3 sm:p-4 border-y border-border/60">
                                        <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2.5">Rincian Item Nota Penjualan</h3>
                                        
                                        <div class="bg-card border border-border rounded-md overflow-x-auto">
                                            <Table class="w-full min-w-[630px]">
                                                <TableHeader>
                                                    <TableRow class="h-9 border-b border-border/60 hover:bg-transparent">
                                                        <TableHead class="py-1.5 px-3 font-semibold text-muted-foreground text-xs uppercase tracking-wider min-w-[200px] whitespace-nowrap">Item Terjual</TableHead>
                                                        <TableHead class="py-1.5 px-3 font-semibold text-muted-foreground text-xs uppercase tracking-wider text-center min-w-[110px] whitespace-nowrap">Kategori</TableHead>
                                                        <TableHead class="py-1.5 px-3 font-semibold text-muted-foreground text-xs uppercase tracking-wider text-center min-w-[90px] whitespace-nowrap">Qty Keluar</TableHead>
                                                        <TableHead class="py-1.5 px-3 font-semibold text-muted-foreground text-xs uppercase tracking-wider text-right min-w-[110px] whitespace-nowrap">Harga Satuan</TableHead>
                                                        <TableHead class="py-1.5 px-3 font-semibold text-muted-foreground text-xs uppercase tracking-wider text-right min-w-[120px] whitespace-nowrap">Subtotal</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <TableRow v-for="item in data.items" :key="item.id" class="h-9 border-b border-border/40 hover:bg-muted/30 transition-colors">
                                                        <TableCell class="py-1.5 px-3 align-middle font-medium text-sm text-foreground">{{ item.item_name }}</TableCell>
                                                        <TableCell class="py-1.5 px-3 align-middle text-center">
                                                            <span class="text-xs text-muted-foreground">{{ item.item_type === 'racikan' ? 'Racikan' : 'Obat Jadi' }}</span>
                                                        </TableCell>
                                                        <TableCell class="py-1.5 px-3 align-middle text-center">
                                                            <span class="font-semibold text-sm text-foreground tabular-nums">{{ item.quantity }}</span> <span class="text-xs text-muted-foreground">{{ item.unit }}</span>
                                                        </TableCell>
                                                        <TableCell class="py-1.5 px-3 align-middle text-right text-sm text-foreground tabular-nums font-medium">{{ formatRupiah(item.unit_price) }}</TableCell>
                                                        <TableCell class="py-1.5 px-3 align-middle text-right text-sm font-medium text-foreground tabular-nums">{{ formatRupiah(item.subtotal) }}</TableCell>
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-if="activeTransactions.length === 0">
                            <TableCell colspan="10" class="py-8 px-3 text-center text-muted-foreground font-medium text-xs">Belum ada aktivitas penjualan dalam rentang tanggal yang dipilih.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- PAGINATION -->
            <div v-if="activePagination && activePagination.data && activePagination.data.length > 0" class="px-6 py-4 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">
                    Halaman {{ activePagination.current_page }} dari {{ activePagination.last_page }} (Total {{ activePagination.total }} transaksi)
                </span>
                
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in activePagination.links" :key="i">
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
</template>
