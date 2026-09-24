<script setup>
import { Head } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

defineProps({
    queueCount: { type: Number, default: 0 },
    activeSubmissions: { type: Array, default: () => [] },
    rawMaterialAlerts: { type: Array, default: () => [] }
});
</script>

<template>
    <Head title="Dashboard Apoteker" />

    <div class="space-y-6">
        <!-- 4 KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- KPI: Antrean Racikan Aktif -->
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Antrean Racikan</span>
                    <div class="w-8 h-8 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold tracking-tight text-foreground">{{ queueCount }}</span>
                    <span class="text-xs text-muted-foreground uppercase">Proses</span>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Pengerjaan sediaan di tahap penimbangan gudang</p>
            </div>

            <!-- KPI: Bahan Baku Kritis -->
            <div class="bg-card p-5 rounded-lg border border-red-500/20 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Bahan Baku Kritis</span>
                    <div class="w-8 h-8 bg-red-500/10 rounded-md flex items-center justify-center text-red-600 dark:text-red-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ rawMaterialAlerts.length }}</span>
                    <span class="text-xs text-red-500 font-medium uppercase">Bahan</span>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Di bawah ambang batas minimum</p>
            </div>

            <!-- KPI: Racikan Selesai -->
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Racikan Selesai</span>
                    <div class="w-8 h-8 bg-emerald-500/10 rounded-md flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 tracking-tight">{{ activeSubmissions.filter(s => s.status === 'approved').length }}</span>
                    <span class="text-xs text-muted-foreground uppercase">Batch</span>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-xs text-muted-foreground">Tervalidasi hari ini</span>
                </div>
            </div>

            <!-- KPI: Verifikasi Admin -->
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Verifikasi Admin</span>
                    <div class="w-8 h-8 bg-amber-500/10 rounded-md flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-amber-600 dark:text-amber-400 tracking-tight">{{ activeSubmissions.filter(s => s.status === 'pending_final_verification').length }}</span>
                    <span class="text-xs text-muted-foreground uppercase">Draf</span>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Menunggu approval finansial</p>
            </div>
        </div>

        <!-- Tabel: Monitor Antrean Compounding -->
        <div class="bg-card rounded-lg border border-border overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-muted/30">
                <div>
                    <h3 class="font-semibold text-foreground tracking-tight text-base">Monitor Antrean Compounding</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar batch racikan yang sedang diproses farmasi.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-muted/50 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                            <th class="px-4 py-3">Nama Racikan</th>
                            <th class="px-4 py-3">Resep / Formula</th>
                            <th class="px-4 py-3">Komposisi Bahan</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in activeSubmissions" :key="item.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-medium text-foreground text-sm block">{{ item.nama_racikan }}</span>
                                <span class="text-xs text-muted-foreground tracking-wide mt-0.5 block">ID-{{ item.id }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2.5 py-0.5 bg-muted text-foreground rounded-md border border-border uppercase">
                                    {{ item.formula?.nama_resep || 'Non-Formula' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="detail in (item.details || []).slice(0, 3)"
                                        :key="detail.id"
                                        class="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-md border border-border"
                                    >
                                        {{ detail.medicine?.nama_obat || detail.raw_material?.nama_bahan || '-' }}
                                    </span>
                                    <span v-if="(item.details || []).length > 3" class="text-xs text-muted-foreground">
                                        +{{ item.details.length - 3 }} lainnya
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider border"
                                    :class="{
                                        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': item.status === 'pending_final_verification',
                                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': item.status === 'active',
                                        'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20': item.status === 'rejected',
                                        'bg-muted text-muted-foreground border-border': !['pending_final_verification','active','rejected'].includes(item.status)
                                    }"
                                >
                                    {{ item.status === 'pending_final_verification' ? 'Menunggu Harga' :
                                       item.status === 'active' ? 'Aktif' :
                                       item.status === 'rejected' ? 'Ditolak' : item.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="activeSubmissions.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-12 h-12 bg-muted rounded-full flex items-center justify-center text-muted-foreground border border-border">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    </div>
                                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tidak Ada Antrean Racikan Aktif</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Peringatan Bahan Baku Kritis -->
        <div v-if="rawMaterialAlerts.length > 0" class="bg-card rounded-lg border border-red-500/20 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-border flex items-center gap-3 bg-red-500/5">
                <div class="w-8 h-8 bg-red-500/10 rounded-md flex items-center justify-center text-red-600 dark:text-red-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground tracking-tight text-base">Peringatan Stok Bahan Baku</h3>
                    <p class="text-xs text-muted-foreground">Bahan baku di bawah ambang batas aman operasional.</p>
                </div>
            </div>
            <div class="divide-y divide-border">
                <div v-for="material in rawMaterialAlerts" :key="material.id" class="px-6 py-3.5 flex items-center justify-between hover:bg-muted/30 transition-colors">
                    <div>
                        <span class="font-medium text-sm text-foreground">{{ material.nama_bahan }}</span>
                        <span class="block text-xs text-muted-foreground mt-0.5">Sediaan: {{ material.bentuk_sediaan || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-red-600 dark:text-red-400 text-base">{{ material.stok_total }}</span>
                        <span class="text-xs text-muted-foreground font-medium uppercase">Unit</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
