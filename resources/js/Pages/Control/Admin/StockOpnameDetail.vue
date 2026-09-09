<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const formatDateTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';

const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '';

const props = defineProps({
    opname: Object,
});

const adjustStock = () => {
    if (confirm('Anda yakin ingin memfinalisasi opname ini dan menyesuaikan stok aktual di sistem? Tindakan ini tidak dapat dibatalkan.')) {
        router.post(route('control.admin.stock-opname.adjust', props.opname.id));
    }
};
</script>

<template>
    <Head title="Detail Stock Opname" />

    <div class="space-y-6">
        <!-- Header Info -->
        <div class="bg-card rounded-lg border border-border shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-foreground tracking-tight">
                        Opname {{ formatDate(opname.opname_date) }}
                    </h2>
                    <span v-if="opname.status === 'completed'" class="bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">Selesai / Disesuaikan</span>
                    <span v-else class="bg-amber-500/10 text-amber-600 border border-amber-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">Draft</span>
                </div>
                <div class="text-sm text-muted-foreground space-y-0.5">
                    <p><span class="font-medium text-foreground">Dibuat Oleh:</span> {{ opname.creator?.name }}</p>
                    <p v-if="opname.catatan"><span class="font-medium text-foreground">Keterangan:</span> {{ opname.catatan }}</p>
                    <p v-if="opname.status === 'completed' && opname.finalizer"><span class="font-medium text-foreground">Difinalisasi Oleh:</span> {{ opname.finalizer.name }} ({{ formatDateTime(opname.finalized_at) }})</p>
                </div>
            </div>
            
            <div v-if="opname.status === 'draft'">
                <button @click="adjustStock" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium h-9 px-4 rounded-md text-sm transition-colors shadow-sm w-full md:w-auto">
                    Finalisasi & Sesuaikan Stok
                </button>
                <p class="text-xs text-muted-foreground mt-1.5 text-center">Tindakan ini akan mengubah qty di sistem.</p>
            </div>
        </div>

        <!-- Detail Items Table -->
        <div class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Item</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jenis</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Qty Sistem</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Qty Fisik</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="item in opname.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                        <td class="px-5 py-3.5 font-semibold text-foreground text-sm">
                            <template v-if="item.medicine_id">{{ item.medicine?.nama_obat }}</template>
                            <template v-else-if="item.raw_material_id">{{ item.raw_material?.nama_bahan }}</template>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span v-if="item.medicine_id" class="uppercase font-semibold text-xs bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-md tracking-wider">Obat Jadi</span>
                            <span v-else-if="item.raw_material_id" class="uppercase font-semibold text-xs bg-amber-500/10 text-amber-600 border border-amber-500/20 px-2 py-0.5 rounded-md tracking-wider">Bahan Baku</span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-medium text-muted-foreground text-sm">
                            {{ Number(item.system_stock).toLocaleString('id-ID') }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-semibold text-foreground text-sm">
                            {{ Number(item.physical_stock).toLocaleString('id-ID') }}
                        </td>
                        <td class="px-5 py-3.5 text-right font-semibold text-sm">
                            <span v-if="item.difference < 0" class="text-destructive bg-destructive/10 border border-destructive/20 px-2 py-0.5 rounded-md">{{ Number(item.difference).toLocaleString('id-ID') }}</span>
                            <span v-else-if="item.difference > 0" class="text-emerald-600 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-md">+{{ Number(item.difference).toLocaleString('id-ID') }}</span>
                            <span v-else class="text-muted-foreground">0</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
