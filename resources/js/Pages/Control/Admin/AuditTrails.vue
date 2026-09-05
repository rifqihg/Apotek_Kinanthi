<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, router } from '@inertiajs/vue3';
defineProps({ trails: Object, users: Array, filters: Object });
</script>

<template>
<Head title="Audit Trail" />
<ControlLayout title="Audit Trail Sistem">
<div class="p-4 lg:p-6 space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Audit Trail</h1>
            <p class="text-slate-500 text-sm mt-1">Laporan riwayat aktivitas dan log keamanan sistem</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <!-- Filters -->
            <div class="flex items-center gap-2 bg-white border border-[#E8EEF6] rounded-[24px] px-3 py-1.5 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                <select @change="router.get(route('control.admin.audit-trails'), { ...filters, user_id: $event.target.value }, { preserveState: true })" class="bg-transparent border-none text-slate-700 text-sm focus:ring-0 outline-none font-medium cursor-pointer pl-1 pr-6 py-1">
                    <option value="">Semua Pengguna</option>
                    <option v-for="u in users" :key="u.id" :value="u.id" :selected="filters?.user_id == u.id">{{ u.name }}</option>
                </select>
            </div>
            <!-- Export -->
            <a :href="route('control.admin.audit-trails.export', filters)" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white h-[52px] px-6 rounded-[24px] text-sm font-semibold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 text-slate-500 text-xs uppercase font-semibold border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-6 py-4">Waktu Kejadian</th>
                        <th class="px-6 py-4">Pengguna</th>
                        <th class="px-6 py-4">Tipe Aksi</th>
                        <th class="px-6 py-4">Deskripsi Aktivitas</th>
                        <th class="px-6 py-4">Alamat IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="trails.data.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada riwayat aktivitas yang tercatat.</td>
                    </tr>
                    <tr v-for="t in trails.data" :key="t.id" class="hover:bg-slate-50/80 transition">
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ new Date(t.performed_at).toLocaleString('id-ID', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-900 font-bold">{{ t.user?.name || 'Sistem' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex bg-slate-100 text-slate-700 font-mono font-bold px-2 py-0.5 rounded text-xs border border-[#E8EEF6]">
                                {{ t.action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 text-sm max-w-[300px] xl:max-w-md truncate" :title="t.description">{{ t.description }}</td>
                        <td class="px-6 py-4 text-slate-400 font-mono text-xs">{{ t.ip_address || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-if="trails.links?.length > 3" class="px-6 py-4 border-t border-[#E8EEF6] flex items-center justify-center gap-1 bg-slate-50/30">
            <template v-for="l in trails.links" :key="l.label">
                <button v-if="l.url" @click="router.get(l.url)" :class="l.active ? 'bg-teal-600 text-white font-medium shadow-[0_8px_24px_rgba(15,23,42,0.04)]' : 'bg-white border border-[#E8EEF6] text-slate-600 hover:bg-slate-50'" class="px-3.5 py-1.5 rounded-[16px] text-sm transition-colors" v-html="l.label"></button>
                <span v-else class="px-3.5 py-1.5 text-slate-400 text-sm" v-html="l.label"></span>
            </template>
        </div>
    </div>
</div>
</ControlLayout>
</template>

