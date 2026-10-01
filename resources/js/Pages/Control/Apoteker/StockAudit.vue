<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ logs: Object });

const search = ref('');
const dateRange = ref('');

const stats = computed(() => {
    const list = props.logs?.data || [];
    const adjustments = list.filter(l => l.action === 'Adjustment' || l.action === 'Pemusnahan').length;
    const restocks = list.filter(l => l.action === 'Restock' || l.action === 'Initial').length;
    return { adjustments, restocks };
});

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
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-900 mb-1.5">
                        MONITORING · KARTU STOK
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Log Audit & Mutasi Inventaris
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pencatatan transparan seluruh penyesuaian fisik (stock opname), pemusnahan sediaan rusak, dan mutasi internal apotek.
                    </p>
                </div>

                <!-- Right Integrated Control Panel -->
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                    <div class="relative w-full sm:w-48">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" type="text" placeholder="Cari entitas..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button v-if="search" type="button" @click="search=''" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="relative w-full sm:w-40">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <input v-model="dateRange" type="date" class="w-full h-9 pl-9 pr-3 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Penyesuaian Fisik -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            PENYESUAIAN FISIK
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.adjustments > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.adjustments > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.adjustments }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.adjustments > 0 ? 'Koreksi saldo audit' : 'Tidak ada deviasi' }}
                    </p>
                </div>

                <!-- Card 2: Mutasi Restock -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            MUTASI RESTOCK
                        </span>
                        <div class="w-6 h-6 rounded-full bg-cyan-50 dark:bg-cyan-950/50 border border-cyan-100 dark:border-cyan-900 flex items-center justify-center text-cyan-600 dark:text-cyan-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-cyan-600 dark:text-cyan-400 leading-none">
                            {{ stats.restocks }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Penambahan stok aktif
                    </p>
                </div>
            </div>
        </div>

        <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
            Daftar Riwayat Pergerakan Fisik
        </h2>

        <!-- Table View -->
        <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left min-w-[1000px]">
                    <thead class="bg-muted/50 text-muted-foreground text-xs font-semibold uppercase tracking-wider border-b border-border">
                        <tr>
                            <th class="px-4 py-3 min-w-[140px] whitespace-nowrap">Timestamp</th>
                            <th class="px-4 py-3 min-w-[200px] whitespace-nowrap">Entitas / Substrat</th>
                            <th class="px-4 py-3 min-w-[120px] whitespace-nowrap">Aksi Audit</th>
                            <th class="px-4 py-3 text-right min-w-[110px] whitespace-nowrap">Delta (Perubahan)</th>
                            <th class="px-4 py-3 text-right min-w-[110px] whitespace-nowrap">Saldo Akhir</th>
                            <th class="px-4 py-3 text-center min-w-[140px] whitespace-nowrap">PIC / Verifikator</th>
                            <th class="px-4 py-3 min-w-[180px] whitespace-nowrap">Keterangan</th>
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
                    <Link 
                        v-if="l.url" 
                        :href="l.url"
                        preserve-scroll 
                        preserve-state 
                        :class="l.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-muted-foreground hover:bg-muted hover:text-foreground'" 
                        class="h-8 w-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                        v-html="l.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
