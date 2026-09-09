<script setup>
import { Head } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

defineProps({ logs: Object });

const actionClass = (a) => ({
    'Adjustment': 'text-amber-600 bg-amber-50 border-amber-100',
    'Pemusnahan': 'text-rose-600 bg-rose-50 border-rose-100',
    'Restock': 'text-emerald-600 bg-emerald-50 border-emerald-100',
    'Initial': 'text-blue-600 bg-blue-50 border-blue-100'
}[a] || 'text-slate-600 bg-slate-50 border-[#E8EEF6]');
</script>

<template>
    <Head title="Audit Stok" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">Log Audit & Mutasi Inventaris</h1>
                <p class="text-xs text-muted-foreground mt-0.5">Transparansi penuh pergerakan stok non-transaksional</p>
            </div>
        </div>

        <!-- Table View -->
        <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground text-xs font-semibold uppercase tracking-wider border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Timestamp</th>
                            <th class="px-4 py-3">Entitas / Substrat</th>
                            <th class="px-4 py-3">Aksi Audit</th>
                            <th class="px-4 py-3 text-right">Delta (Perubahan)</th>
                            <th class="px-4 py-3 text-right">Saldo Akhir</th>
                            <th class="px-4 py-3 text-center">PIC / Verifikator</th>
                            <th class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-foreground">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground text-xs font-medium">
                                Belum Ada Log Audit
                            </td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-muted/50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-muted-foreground">
                                {{ new Date(log.created_at).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-foreground text-sm block leading-tight">{{ log.raw_material?.nama_bahan || log.medicine?.nama_obat }}</span>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ log.raw_material_id ? 'Materia / Bahan Baku' : 'Sediaan Jadi / Obat' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="actionClass(log.action)" class="px-2 py-0.5 rounded-md text-xs font-semibold border">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-sm tabular-nums" :class="log.change_amount > 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ log.change_amount > 0 ? '+' : '' }}{{ Number(log.change_amount).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-foreground text-sm tabular-nums">
                                {{ Number(log.qty_after).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-6 h-6 bg-muted rounded-full flex items-center justify-center text-xs font-medium text-muted-foreground border border-border uppercase">{{ log.user?.name?.charAt(0) }}</div>
                                    <span class="text-xs font-medium text-foreground">{{ log.user?.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="bg-muted text-muted-foreground px-2.5 py-1 rounded-md text-xs block w-fit truncate max-w-[200px]" :title="log.reason">
                                    {{ log.reason || '-' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="logs.links?.length > 3" class="flex justify-center gap-1.5 p-4 border-t border-border">
                <template v-for="l in logs.links" :key="l.label">
                    <button 
                        v-if="l.url" 
                        @click="router.get(l.url)" 
                        :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-muted-foreground hover:bg-muted hover:text-foreground'" 
                        class="h-8 w-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                        v-html="l.label"
                    ></button>
                </template>
            </div>
        </div>
    </div>
</template>
