<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    products: Object,
    filters: { type: Object, default: () => ({}) }
});

const activeFilterTab = ref('all');

const stats = computed(() => {
    const list = props.products?.data || [];
    const total = props.products?.total ?? list.length;
    const nearBud = list.filter(p => getDiff(p.beyond_use_date) <= 30).length;
    const safeBud = list.filter(p => getDiff(p.beyond_use_date) > 30).length;
    return { total, nearBud, safeBud };
});

const filteredProducts = computed(() => {
    let list = props.products?.data || [];
    if (activeFilterTab.value === 'near_bud') {
        list = list.filter(p => getDiff(p.beyond_use_date) <= 30);
    } else if (activeFilterTab.value === 'safe') {
        list = list.filter(p => getDiff(p.beyond_use_date) > 30);
    }
    if (search.value && search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter(p => 
            p.nama_produk?.toLowerCase().includes(q) ||
            p.nomor_batch?.toLowerCase().includes(q) ||
            p.tipe_racikan?.toLowerCase().includes(q)
        );
    }
    return list;
});

const search = ref(props.filters?.search || '');

const clearSearch = () => {
    search.value = '';
};
const expandedId = ref(null);
const toggle = (id) => { expandedId.value = expandedId.value === id ? null : id; };

const getDiff = (bud) => (new Date(bud) - new Date()) / 86400000;
const budClass = (bud) => {
    const d = getDiff(bud);
    return d < 0 ? 'bg-gray-100 text-gray-500 border-gray-200' : d < 30 ? 'bg-red-50 text-red-600 border-red-200' : d < 90 ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-teal-50 text-teal-600 border-teal-200';
};
const budLabel = (bud) => {
    const d = getDiff(bud);
    return d < 0 ? 'Expired' : d < 30 ? '< 1 Bulan' : d < 90 ? '< 3 Bulan' : 'Aman';
};
</script>

<template>
<Head title="Data Obat Racikan" />

<div class="space-y-8">
    <!-- BENTO COCKPIT HEADER -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
            <!-- Baris 1: Filter Tabs (Pills) -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    <button
                        type="button"
                        @click="activeFilterTab = 'all'"
                        class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'all' 
                            ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span>Semua Racikan</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'all' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                        >{{ stats.total }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'near_bud'"
                        class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-auto sm:flex-none sm:w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'near_bud' 
                            ? 'bg-amber-500 text-white shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="activeFilterTab === 'near_bud' ? 'bg-white' : 'bg-amber-500'"></span>
                        <span>Dekat Batas BUD</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'near_bud' ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
                        >{{ stats.nearBud }}</span>
                    </button>

                    <button
                        type="button"
                        @click="activeFilterTab = 'safe'"
                        class="h-9 px-2.5 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-none w-auto whitespace-nowrap cursor-pointer"
                        :class="activeFilterTab === 'safe' 
                            ? 'bg-emerald-600 text-white shadow-xs' 
                            : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="activeFilterTab === 'safe' ? 'bg-white' : 'bg-emerald-500'"></span>
                        <span>Aman</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeFilterTab === 'safe' ? 'bg-white/20 text-white' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
                        >{{ stats.safeBud }}</span>
                    </button>
                </div>

                <div class="hidden sm:inline-flex items-center gap-1.5 px-3 h-9 rounded-lg text-xs font-medium text-slate-500 dark:text-muted-foreground bg-slate-100/80 dark:bg-muted/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Formula Resmi Apotek</span>
                </div>
            </div>

            <!-- Baris 2: Search Input -->
            <div class="relative w-full">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Cari nama produk, batch, formula..." 
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
        </div>

        <!-- SISI KANAN: 2 METRIK KARTU BERDAMPINGAN (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
            <!-- Card 1: Link ke Katalog Faktur -->
            <Link 
                :href="route('control.admin.medicines.faktur')" 
                class="bg-white dark:bg-card border border-slate-200/80 dark:border-border hover:border-slate-300 dark:hover:border-slate-700 rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all group cursor-pointer h-full"
            >
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground group-hover:text-blue-600 transition-colors truncate">
                        KATALOG FAKTUR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                </div>
                <div class="my-1 flex items-center gap-1.5">
                    <span class="text-base sm:text-lg font-bold text-blue-600 dark:text-blue-400 group-hover:underline">Buka Faktur</span>
                    <span class="text-lg font-bold text-slate-400 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Item obat pabrikan
                </p>
            </Link>

            <!-- Card 2: Total Data Racikan (Active) -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        DATA RACIKAN
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 2v7.31L4.41 19.59A2 2 0 0 0 6 22h12a2 2 0 0 0 1.59-2.41L14 9.31V2"></path>
                            <line x1="8.5" y1="2" x2="15.5" y2="2"></line>
                            <path d="M7 16h10"></path>
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ stats.total }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Formula</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Item racikan apoteker
                </p>
            </div>
        </div>
    </div>

    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm mt-2">
        <Table class="w-full min-w-[930px]">
            <TableHeader>
                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                    <TableHead class="w-12 min-w-12 text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">NO</TableHead>
                    <TableHead class="min-w-[220px] text-left py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Nama Produk</TableHead>
                    <TableHead class="min-w-[130px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">No Batch</TableHead>
                    <TableHead class="min-w-[110px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Tgl Racik</TableHead>
                    <TableHead class="min-w-[110px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">BUD</TableHead>
                    <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Qty</TableHead>
                    <TableHead class="min-w-[120px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Status BUD</TableHead>
                    <TableHead class="min-w-[90px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-if="!filteredProducts || filteredProducts.length === 0">
                    <TableCell colspan="8" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center justify-center space-y-3">
                            <div class="w-10 h-10 bg-muted rounded-full flex items-center justify-center text-lg border border-border text-muted-foreground">🧪</div>
                            <div class="space-y-1">
                                <h4 class="text-sm font-semibold text-foreground">
                                    <span v-if="activeFilterTab !== 'all'">Tidak ada racikan yang sesuai filter</span>
                                    <span v-else>Belum Ada Produk Racikan</span>
                                </h4>
                                <p class="text-xs text-muted-foreground max-w-sm mx-auto">Produk racikan akan muncul di sini setelah proses peracikan diselesaikan oleh Apoteker dan dikonfirmasi oleh sistem.</p>
                            </div>
                        </div>
                    </TableCell>
                </TableRow>
                <template v-for="(p, index) in filteredProducts" :key="p.id">
                    <TableRow class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors group cursor-pointer" @click="toggle(p.id)">
                        <!-- NO -->
                        <TableCell class="py-2.5 px-3 text-center align-middle text-xs font-medium text-muted-foreground tabular-nums">
                            {{ ((products.current_page || 1) - 1) * (products.per_page || 15) + index + 1 }}
                        </TableCell>
                        <!-- NAMA PRODUK -->
                        <TableCell class="py-2.5 px-3 align-middle text-left break-words whitespace-normal">
                            <span class="font-medium text-foreground text-sm leading-snug break-words">{{ p.nama_produk }}</span>
                        </TableCell>
                        <!-- NO BATCH -->
                        <TableCell class="py-2.5 px-3 text-center align-middle text-sm font-medium text-foreground tabular-nums whitespace-nowrap">
                            {{ p.batch_no }}
                        </TableCell>
                        <!-- TGL RACIK -->
                        <TableCell class="py-2.5 px-3 text-center text-sm font-medium text-foreground tabular-nums align-middle whitespace-nowrap">{{ new Date(p.tanggal_racik).toLocaleDateString('id-ID') }}</TableCell>
                        <!-- BUD -->
                        <TableCell class="py-2.5 px-3 text-center text-foreground font-medium text-sm tabular-nums align-middle whitespace-nowrap">{{ new Date(p.bud_date).toLocaleDateString('id-ID') }}</TableCell>
                        <!-- QTY (RATA TENGAH) -->
                        <TableCell class="py-2.5 px-3 text-center font-medium text-foreground text-sm tabular-nums align-middle whitespace-nowrap">{{ Number(p.qty_hasil).toLocaleString('id-ID') }} <span class="text-xs text-muted-foreground uppercase">{{ p.satuan }}</span></TableCell>
                        <!-- STATUS BUD -->
                        <TableCell class="py-2.5 px-3 text-center align-middle whitespace-nowrap">
                            <span :class="getDiff(p.bud_date) < 30 ? 'text-destructive' : 'text-emerald-600'" class="text-xs font-medium inline-flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full" :class="getDiff(p.bud_date) < 30 ? 'bg-destructive' : 'bg-emerald-500'"></span>
                                {{ budLabel(p.bud_date) }}
                            </span>
                        </TableCell>
                        <!-- AKSI -->
                        <TableCell class="py-2.5 px-3 text-center align-middle whitespace-nowrap">
                            <div class="inline-flex items-center justify-center gap-1 text-xs font-medium text-primary group-hover:underline">
                                <span>Rincian</span>
                                <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="expandedId===p.id?'rotate-180 text-primary':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="expandedId===p.id" class="bg-muted/20">
                        <TableCell colspan="8" class="p-3.5 sm:p-4 bg-slate-50/80 dark:bg-muted/20 border-b border-border">
                            <div class="bg-card p-3.5 rounded-lg border border-slate-200/80 dark:border-border/80 shadow-2xs grid grid-cols-2 md:grid-cols-4 gap-3 max-w-3xl">
                                <div><p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Apoteker</p><p class="font-medium text-foreground text-xs">{{ p.submission?.user?.name || '-' }}</p></div>
                                <div><p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Racikan Asal</p><p class="font-medium text-foreground text-xs">{{ p.submission?.nama_racikan || '-' }}</p></div>
                                <div><p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Harga Jual</p><p class="font-semibold text-emerald-600 text-xs tabular-nums">Rp {{ Number(p.harga_jual).toLocaleString('id-ID') }}</p></div>
                                <div><p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-0.5">Dibuat Oleh</p><p class="font-medium text-foreground text-xs">{{ p.creator?.name || '-' }}</p></div>
                            </div>
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>

        <!-- Pagination Footer -->
        <div v-if="products.links && products.links.length > 3" class="px-5 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
            <p class="text-xs font-medium text-muted-foreground">Halaman {{ products.current_page || 1 }} dari {{ products.last_page || 1 }} (Total {{ products.total || 0 }} produk racikan)</p>
            <div class="flex items-center gap-1">
                <template v-for="(link, i) in products.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll 
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" 
                        class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label" />
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
                </template>
            </div>
        </div>
    </div>
</div>
</template>
