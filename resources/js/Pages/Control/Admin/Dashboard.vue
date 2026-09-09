<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import DashboardCharts from '@/Components/DashboardCharts.vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';

defineOptions({ layout: ControlLayout });

const filterDropdownOpen = ref(false);
const page = usePage();

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_obat: 0,
            low_stock_count: 0,
            low_stock_percentage: 0,
            pending_admin_count: 0,
            pending_compounding: 0,
            today_sales_count: 0,
            today_sales_total: 0,
            active_prescriptions_count: 0,
            pending_pricing_count: 0
        })
    },
    tableStocks: {
        type: Object,
        default: () => ({ data: [], links: [] })
    },
    currentFilter: {
        type: String,
        default: 'abjad'
    },
    title: { type: String, default: 'Dashboard Strategis' }
});

const activeFilter = ref(props.currentFilter || '');

const { data: queryStocks, isFetching } = useQuery({
    queryKey: computed(() => ['admin-dashboard-stocks', activeFilter.value]),
    queryFn: async () => {
        const res = await axios.get(route('control.admin.dashboard'), {
            params: { filter: activeFilter.value },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Inertia-Partial-Component': 'Control/Admin/Dashboard',
                'X-Inertia-Partial-Data': 'tableStocks,currentFilter',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        return res.data.props.tableStocks;
    },
    initialData: () => props.tableStocks,
    placeholderData: (prev) => prev,
});

const activeTableStocks = computed(() => queryStocks.value || props.tableStocks || { data: [], links: [] });

const applyFilter = (filterName) => {
    activeFilter.value = filterName;
};

// Helpers for dynamic stock display based on qty_converted (Source of Truth)
const getLevel1Qty = (item) => {
    if (!item.qty_converted || item.qty_converted <= 0) return 0;
    
    // Default factor if mid unit doesn't exist
    let totalSmallInBig = item.unit_conversion_factor || 1;
    
    // If mid unit exists, total small in big = small_to_mid * mid_to_big
    if (item.mid_to_big_ratio > 0 && item.small_to_mid_ratio > 0) {
        totalSmallInBig = item.mid_to_big_ratio * item.small_to_mid_ratio;
    }
    
    return Math.floor(item.qty_converted / totalSmallInBig);
};

const getLevel2Qty = (item) => {
    if (!item.qty_converted || item.qty_converted <= 0 || !item.small_to_mid_ratio) return 0;
    
    return Math.floor(item.qty_converted / item.small_to_mid_ratio);
};
</script>

<template>
    <Head title="Dashboard Admin" />

    <div class="space-y-6">
        <!-- Ringkasan Hari Ini -->
        <div class="space-y-3">
            <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ringkasan Hari Ini</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Penjualan Kasir (Blue) -->
                <Card class="bg-card border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Transaksi Kasir</CardTitle>
                        <div class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ stats?.today_sales_count || 0 }}</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase">Trx</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Transaksi penjualan kasir hari ini</p>
                    </CardContent>
                </Card>

                <!-- Total Omset (Green) -->
                <Card class="bg-card border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Pendapatan</CardTitle>
                        <div class="w-8 h-8 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">
                            Rp {{ new Intl.NumberFormat('id-ID').format(stats?.today_sales_total || 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Total omset kasir terbukukan</p>
                    </CardContent>
                </Card>

                <!-- Resep Aktif (Blue) -->
                <Card class="bg-card border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Resep Aktif</CardTitle>
                        <div class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ stats?.active_prescriptions_count || 0 }}</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase">Resep</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Status diproses / siap diserahkan</p>
                    </CardContent>
                </Card>

                <!-- Menunggu Harga (Amber) -->
                <Card class="bg-card border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Menunggu Harga</CardTitle>
                        <div class="w-8 h-8 rounded-md bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ stats?.pending_pricing_count || 0 }}</span>
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold uppercase">Racikan</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Antrean penetapan tarif racikan</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- 4 KPI Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Metric 1: Total SKU (Slate/Blue) -->
            <Card class="bg-card border-border shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Katalog Inventaris</CardTitle>
                    <div class="w-8 h-8 rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ stats?.total_obat ?? 0 }}</span>
                        <span class="text-xs text-slate-600 dark:text-slate-400 font-semibold uppercase">SKU</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">Master data obat terdaftar</p>
                </CardContent>
            </Card>

            <!-- Metric 2: Low Stock Alert (Red) -->
            <Card class="bg-card shadow-sm" :class="stats?.low_stock_count > 0 ? 'border-red-200 dark:border-red-900/50' : 'border-border'">
                <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider" :class="stats?.low_stock_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">Alert Stok Kritis</CardTitle>
                    <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0" :class="stats?.low_stock_count > 0 ? 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400' : 'bg-slate-100 text-slate-400'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl lg:text-3xl font-semibold tracking-tight" :class="stats?.low_stock_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">{{ stats?.low_stock_count ?? 0 }}</span>
                        <span class="text-xs font-semibold uppercase" :class="stats?.low_stock_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">SKU Menipis</span>
                    </div>
                    <div class="mt-2 space-y-1">
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Rasio Defisit</span>
                            <span class="font-medium tracking-wide" :class="stats?.low_stock_count > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : ''">{{ stats?.low_stock_percentage ?? 0 }}%</span>
                        </div>
                        <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-red-600 transition-all" :style="{ width: `${stats?.low_stock_percentage || 0}%` }"></div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Metric 3: FEFO Alert (Amber jika warning / Blue jika aman) -->
            <Card class="bg-card shadow-sm" :class="stats?.fefo_alert_count > 0 ? 'border-amber-200 dark:border-amber-900/50' : 'border-border'">
                <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider" :class="stats?.fefo_alert_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">Prioritas FEFO</CardTitle>
                    <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0" :class="stats?.fefo_alert_count > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' : 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="stats?.fefo_alert_count > 0">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-amber-600 dark:text-amber-400">{{ stats?.fefo_alert_count }}</span>
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold uppercase">Batch Kritis</span>
                        </div>
                        <p class="text-xs text-amber-700 dark:text-amber-300 font-medium mt-1">Kedaluwarsa: {{ stats?.fefo_nearest_date }}</p>
                    </div>
                    <div v-else>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">0</span>
                            <span class="text-xs text-muted-foreground font-medium uppercase">Batch</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Semua batch aktif dalam batas aman</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Metric 4: Lab Queue (Blue / Amber sesuai status) -->
            <Card class="bg-card shadow-sm" :class="stats?.pending_compounding > 0 ? 'border-amber-200 dark:border-amber-900/50' : 'border-border'">
                <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider" :class="stats?.pending_compounding > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">Antrean Racikan Lab</CardTitle>
                    <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0" :class="stats?.pending_compounding > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' : 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ stats?.pending_compounding ?? 0 }}</span>
                        <span class="text-xs font-semibold uppercase" :class="stats?.pending_compounding > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">Batch</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">Racikan menunggu / dalam proses lab</p>
                </CardContent>
            </Card>
        </div>

        <!-- Dashboard Charts -->
            <DashboardCharts />

            <!-- Inventory Monitoring List -->
            <Card class="overflow-hidden">
                <CardHeader class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div>
                        <CardTitle class="text-base font-semibold tracking-tight text-foreground">Monitoring Persediaan Obat</CardTitle>
                        <CardDescription class="text-sm text-muted-foreground mt-1">
                            Pantau stok fisik terkini, nomor batch, dan batas kedaluwarsa secara real-time.
                        </CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Filter Dropdown -->
                        <div class="relative">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                @click="filterDropdownOpen = !filterDropdownOpen"
                                @blur="setTimeout(() => filterDropdownOpen = false, 200)"
                                class="h-8 text-sm font-medium gap-1.5"
                            >
                                <svg v-if="!isFetching" class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                <svg v-else class="animate-spin w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="activeFilter === 'qty'">Stok Kritis</span>
                                <span v-else-if="activeFilter === 'exp'">EXP Terdekat</span>
                                <span v-else>Semua Stok</span>
                                <svg class="w-3 h-3 text-muted-foreground transition-transform" :class="{'rotate-180': filterDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </Button>

                            <div v-show="filterDropdownOpen" class="absolute right-0 top-full mt-1 w-44 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md z-30">
                                <button 
                                    v-if="activeFilter" 
                                    @click="applyFilter('')" 
                                    class="w-full text-left px-2 py-1.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground rounded-sm flex items-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Reset Filter
                                </button>
                                <div v-if="activeFilter" class="h-px bg-border my-1"></div>
                                <button 
                                    @click="applyFilter('qty')" 
                                    class="w-full text-left px-2 py-1.5 text-sm font-medium rounded-sm flex items-center justify-between hover:bg-accent hover:text-accent-foreground"
                                    :class="{'bg-accent text-accent-foreground font-semibold': activeFilter === 'qty'}"
                                >
                                    Stok Kritis
                                    <span v-if="activeFilter === 'qty'" class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                </button>
                                <button 
                                    @click="applyFilter('exp')" 
                                    class="w-full text-left px-2 py-1.5 text-sm font-medium rounded-sm flex items-center justify-between hover:bg-accent hover:text-accent-foreground"
                                    :class="{'bg-accent text-accent-foreground font-semibold': activeFilter === 'exp'}"
                                >
                                    EXP Terdekat
                                    <span v-if="activeFilter === 'exp'" class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Link Lihat Semua -->
                        <Button variant="outline" size="sm" as-child class="h-8 text-sm font-medium">
                            <Link :href="route('control.admin.incoming-stocks')">Lihat Semua</Link>
                        </Button>
                    </div>
                </CardHeader>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[300px] text-sm font-semibold text-foreground">Nama Obat & Kategori</TableHead>
                            <TableHead class="text-center text-sm font-semibold text-foreground">Nomor Batch</TableHead>
                            <TableHead class="text-center text-sm font-semibold text-foreground">Stok Fisik</TableHead>
                            <TableHead class="text-center text-sm font-semibold text-foreground">Batas Aman</TableHead>
                            <TableHead class="text-center text-sm font-semibold text-foreground">Tgl Kedaluwarsa</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in activeTableStocks.data" :key="item.id">
                            <TableCell>
                                <div class="font-semibold text-foreground text-base leading-tight">{{ item.nama_obat }}</div>
                                <div class="text-sm text-muted-foreground mt-0.5">{{ item.kategori }}</div>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="inline-flex items-center text-sm px-2.5 py-1 rounded border border-border bg-muted/40 text-muted-foreground tracking-wide">
                                    {{ item.nomor_batch || 'NO-BATCH' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                    <!-- LEVEL 1 -->
                                    <template v-if="getLevel1Qty(item) > 0 && item.satuan_input">
                                        <span class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-border bg-muted/30 text-foreground tracking-wide">
                                            <span class="font-bold">{{ getLevel1Qty(item) }}</span>
                                            <span class="text-xs text-muted-foreground font-medium uppercase">{{ item.satuan_input }}</span>
                                        </span>
                                    </template>

                                    <!-- LEVEL 2 -->
                                    <template v-if="item.mid_to_big_ratio > 0 && item.mid_unit && getLevel2Qty(item) > 0">
                                        <span class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-border bg-muted/30 text-foreground tracking-wide">
                                            <span class="font-bold">{{ getLevel2Qty(item) }}</span>
                                            <span class="text-xs text-muted-foreground font-medium uppercase">{{ item.mid_unit }}</span>
                                        </span>
                                    </template>

                                    <!-- LEVEL 3 (Base Unit) -->
                                    <template v-if="item.qty_converted > 0 && item.satuan_stok">
                                        <span class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-border bg-primary/5 text-foreground tracking-wide">
                                            <span class="font-bold">{{ item.qty_converted }}</span>
                                            <span class="text-xs text-muted-foreground font-medium uppercase">{{ item.satuan_stok }}</span>
                                        </span>
                                    </template>
                                    
                                    <!-- ZERO STOCK -->
                                    <template v-if="!item.qty_converted || item.qty_converted <= 0">
                                        <span class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border border-destructive/30 bg-destructive/10 text-destructive font-bold tracking-wide">
                                            0.00 {{ item.satuan_stok }}
                                        </span>
                                    </template>
                                </div>
                            </TableCell>
                            <TableCell class="text-center text-sm text-muted-foreground tracking-wide">
                                {{ item.batas_aman }} Unit
                            </TableCell>
                            <TableCell class="text-center">
                                <span 
                                    class="inline-flex items-center text-sm px-3 py-1 rounded border tracking-wide"
                                    :class="item.is_expiring_soon 
                                        ? 'border-destructive/30 bg-destructive/10 text-destructive font-semibold' 
                                        : 'border-border bg-background text-foreground font-medium'"
                                >
                                    {{ item.expired_date }}
                                </span>
                            </TableCell>
                        </TableRow>

                        <!-- Empty State -->
                        <TableRow v-if="!activeTableStocks.data || activeTableStocks.data.length === 0">
                            <TableCell colspan="5" class="h-32 text-center text-muted-foreground text-sm">
                                Seluruh persediaan obat dalam kondisi aman. Tidak ada stok kritis saat ini.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- PAGINATION -->
                <div v-if="activeTableStocks.links && activeTableStocks.links.length > 3" class="p-4 border-t border-border flex flex-wrap justify-center gap-1">
                    <template v-for="(link, k) in activeTableStocks.links" :key="k">
                        <Button 
                            v-if="link.url"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                            as-child
                            class="h-8 min-w-[2rem] px-3 text-sm"
                        >
                            <Link :href="link.url" preserve-scroll v-html="link.label" />
                        </Button>
                        <Button 
                            v-else
                            variant="outline"
                            size="sm"
                            disabled
                            class="h-8 min-w-[2rem] px-3 text-sm opacity-50 cursor-not-allowed"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </Card>
        </div>
</template>
