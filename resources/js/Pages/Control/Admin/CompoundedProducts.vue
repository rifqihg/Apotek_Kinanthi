<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    <div class="p-6 bg-card rounded-lg border border-border shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 bg-muted text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">Master Data</span>
                <span class="px-2.5 py-0.5 bg-muted/50 text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">Compounding</span>
            </div>
            <h1 class="text-xl font-bold text-foreground tracking-tight leading-none">Data Obat Racikan</h1>
            <p class="text-muted-foreground text-sm">Produk hasil racikan Apoteker — dipantau berdasarkan Beyond Use Date (BUD)</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-background border border-border px-4 py-2.5 rounded-md shadow-sm flex items-center gap-3">
                <div class="text-right">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Produk Aktif</p>
                    <p class="text-xl font-semibold tracking-tight text-foreground">{{ products.total }}</p>
                </div>
                <div class="w-8 h-8 bg-muted rounded-md flex items-center justify-center text-sm border border-border">🧪</div>
            </div>
        </div>
    </div>

    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <table class="w-full text-sm text-left border-collapse">
            <thead>
                <tr class="bg-muted/50 border-b border-border">
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Produk</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Batch</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tgl Racik</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">BUD Date</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Qty</th>
                    <th class="text-center px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Status BUD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <template v-for="p in products.data" :key="p.id">
                    <tr class="hover:bg-muted/40 transition-colors group cursor-pointer" @click="toggle(p.id)">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-md bg-muted border border-border flex items-center justify-center text-muted-foreground group-hover:text-foreground">
                                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="expandedId===p.id?'rotate-90 text-primary':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>
                                <p class="font-semibold text-foreground text-sm">{{ p.nama_produk }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="px-2 py-0.5 bg-muted text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">
                                {{ p.batch_no }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center text-xs font-medium text-muted-foreground">{{ new Date(p.tanggal_racik).toLocaleDateString('id-ID') }}</td>
                        <td class="px-4 py-3.5 text-center font-semibold text-foreground text-xs">{{ new Date(p.bud_date).toLocaleDateString('id-ID') }}</td>
                        <td class="px-4 py-3.5 text-right font-semibold text-foreground text-sm">{{ Number(p.qty_hasil).toLocaleString('id-ID') }} {{ p.satuan }}</td>
                        <td class="px-5 py-3.5 text-center">
                            <span :class="budClass(p.bud_date)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block">
                                {{ budLabel(p.bud_date) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="expandedId===p.id" class="bg-muted/20">
                        <td colspan="6" class="p-4 sm:p-6 border-b border-border">
                            <div class="bg-card p-4 rounded-lg border border-border shadow-sm grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">Apoteker</p><p class="font-semibold text-foreground text-sm">{{ p.submission?.user?.name || '-' }}</p></div>
                                <div><p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">Racikan Asal</p><p class="font-semibold text-foreground text-sm">{{ p.submission?.nama_racikan || '-' }}</p></div>
                                <div><p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">Harga Jual</p><p class="font-semibold text-emerald-600 text-sm">Rp {{ Number(p.harga_jual).toLocaleString('id-ID') }}</p></div>
                                <div><p class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">Dibuat Oleh</p><p class="font-semibold text-foreground text-sm">{{ p.creator?.name || '-' }}</p></div>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

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
