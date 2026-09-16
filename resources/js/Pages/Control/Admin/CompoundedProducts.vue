<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ products: Object });
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

<div class="space-y-6">
    <!-- HEADER SECTION: HERO BANNER + STAT CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- HERO BANNER CARD (lg:col-span-8: diperlebar dan dipertinggi proporsional) -->
        <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
            <!-- Left Text Column -->
            <div class="relative z-10 max-w-sm sm:max-w-md">
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                    Katalog Formula & Obat Racikan
                </h1>
                <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                    Manajemen master formula peracikan, takaran bahan baku aktif, dan standarisasi sediaan racikan apotek.
                </p>
            </div>

            <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
            <div class="relative z-10 flex items-center justify-center shrink-0">
                <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                    <!-- SVG RACIKAN (Lab Flask Erlenmeyer + Kapsul Interlocking) -->
                    <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="12" y="4" width="14" height="4.5" rx="1.8" stroke="#059669" stroke-width="2" fill="#ECFDF5" />
                        <path d="M15 8.5v11L5 46.5a4 4 0 0 0 3.5 5.5h23a4 4 0 0 0 3.5-5.5L25 19.5V8.5" stroke="#059669" stroke-width="2.2" stroke-linejoin="round" fill="#FFFFFF" />
                        <path d="M8 39c3-1.5 6 1.5 10 0s6-.5 10 1l2 6.5a3 3 0 0 1-2.5 4H10a3 3 0 0 1-2.8-4l.8-7.5z" fill="#A7F3D0" fill-opacity="0.9" />
                        <line x1="16" y1="26" x2="20" y2="26" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                        <line x1="14" y1="31" x2="20" y2="31" stroke="#059669" stroke-width="1.4" stroke-linecap="round" />
                        <g transform="translate(37, 33) rotate(28)">
                            <rect x="-7.5" y="-18" width="15" height="36" rx="7.5" stroke="#059669" stroke-width="2.2" fill="#FFFFFF" />
                            <path d="M-7.5 -10.5 A7.5 7.5 0 0 1 7.5 -10.5 V0 H-7.5 Z" fill="#10B981" />
                            <line x1="-7.5" y1="0" x2="7.5" y2="0" stroke="#059669" stroke-width="1.8" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- 2 STAT CARDS (lg:col-span-4: diperkecil lebarnya, tinggi mengikuti hero banner) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-3">
            <!-- Card 1: Link ke Katalog Faktur -->
            <Link 
                :href="route('control.admin.medicines.faktur')" 
                class="bg-white dark:bg-card border border-slate-200/80 dark:border-border hover:border-slate-300 dark:hover:border-slate-700 rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all group cursor-pointer h-full"
            >
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground group-hover:text-blue-600 transition-colors truncate">
                        TOTAL DATA FAKTUR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-950/50 border border-blue-100 dark:border-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                        &rarr;
                    </span>
                </div>
                <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground leading-tight truncate">
                    Item sediaan faktur masuk
                </p>
            </Link>

            <!-- Card 2: Total Data Racikan (Active) -->
            <div 
                class="bg-white dark:bg-card border border-emerald-400/90 ring-2 ring-emerald-500/10 rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between transition-all h-full"
            >
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        TOTAL DATA RACIKAN
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 2v7.31L4.41 19.59A2 2 0 0 0 6 22h12a2 2 0 0 0 1.59-2.41L14 9.31V2"></path>
                            <line x1="8.5" y1="2" x2="15.5" y2="2"></line>
                            <path d="M7 16h10"></path>
                        </svg>
                    </div>
                </div>
                <div class="my-0.5">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                        {{ products.total || 0 }}
                    </span>
                </div>
                <p class="text-[10px] font-normal text-slate-400 dark:text-muted-foreground leading-tight truncate">
                    Item racikan aktif apoteker
                </p>
            </div>
        </div>
    </div>

    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <Table>
            <TableHeader>
                <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                    <TableHead class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Produk</TableHead>
                    <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Batch</TableHead>
                    <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tgl Racik</TableHead>
                    <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">BUD Date</TableHead>
                    <TableHead class="py-2 px-3 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">Qty</TableHead>
                    <TableHead class="py-2 px-3 text-center text-xs font-semibold text-muted-foreground uppercase tracking-wider">Status BUD</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-for="p in products.data" :key="p.id">
                    <TableRow class="h-10 border-b border-border/40 hover:bg-muted/30 transition-colors group cursor-pointer" @click="toggle(p.id)">
                        <TableCell class="py-2 px-3 align-middle">
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0 transition-transform duration-200" :class="expandedId===p.id?'rotate-90 text-primary':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="font-medium text-foreground text-sm leading-tight">{{ p.nama_produk }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="py-2 px-3 text-center align-middle font-mono text-sm font-normal text-foreground tracking-tight">
                            {{ p.batch_no }}
                        </TableCell>
                        <TableCell class="py-2 px-3 text-center text-xs font-mono text-muted-foreground tabular-nums align-middle">{{ new Date(p.tanggal_racik).toLocaleDateString('id-ID') }}</TableCell>
                        <TableCell class="py-2 px-3 text-center font-mono text-foreground text-xs tabular-nums align-middle">{{ new Date(p.bud_date).toLocaleDateString('id-ID') }}</TableCell>
                        <TableCell class="py-2 px-3 text-right font-medium text-foreground text-sm tabular-nums align-middle">{{ Number(p.qty_hasil).toLocaleString('id-ID') }} <span class="text-xs text-muted-foreground">{{ p.satuan }}</span></TableCell>
                        <TableCell class="py-2 px-3 text-center align-middle">
                            <span :class="getDiff(p.bud_date) < 30 ? 'text-destructive' : 'text-emerald-600'" class="text-xs font-medium inline-flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full" :class="getDiff(p.bud_date) < 30 ? 'bg-destructive' : 'bg-emerald-500'"></span>
                                {{ budLabel(p.bud_date) }}
                            </span>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="expandedId===p.id" class="bg-muted/20">
                        <TableCell colspan="6" class="p-3 sm:p-4 border-b border-border">
                            <div class="bg-card p-3 rounded-md border border-border shadow-2xs grid grid-cols-2 md:grid-cols-4 gap-3">
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

        <!-- Empty State -->
        <div v-if="!products.data?.length" class="p-12 flex flex-col items-center justify-center text-center space-y-4">
            <div class="w-12 h-12 bg-muted rounded-md flex items-center justify-center text-xl border border-border text-muted-foreground">🧪</div>
            <div class="space-y-1">
                <h4 class="text-base font-semibold text-foreground">Belum Ada Produk Racikan</h4>
                <p class="text-muted-foreground text-xs max-w-sm mx-auto">Produk racikan akan muncul di sini setelah proses peracikan diselesaikan oleh Apoteker dan dikonfirmasi oleh sistem.</p>
            </div>
        </div>
    </div>
</div>
</template>
