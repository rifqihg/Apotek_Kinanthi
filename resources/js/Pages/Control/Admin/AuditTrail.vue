<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ 
    audits: Object,
    filters: Object
});

const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || '');

const handleFilter = debounce(() => {
    router.get(route('control.admin.audit-trails'), { 
        search: search.value, 
        role: roleFilter.value 
    }, { 
        preserveState: true, 
        replace: true 
    });
}, 300);

watch([search, roleFilter], () => {
    handleFilter();
});

const getActionClass = (action) => {
    if (action.includes('CREATE')) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
    if (action.includes('UPDATE')) return 'bg-blue-50 text-blue-600 border-blue-100';
    if (action.includes('DELETE')) return 'bg-red-50 text-red-600 border-red-100';
    if (action.includes('REJECT')) return 'bg-amber-50 text-amber-600 border-amber-100';
    return 'bg-slate-50 text-slate-600 border-[#E8EEF6]';
};
</script>

<template>
<Head title="Audit Trail" />
<ControlLayout title="Log Aktivitas User">
<div class="p-4 lg:p-6 space-y-6 max-w-[1400px] mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Audit Trail</h1>
            <p class="text-slate-500 text-sm mt-1">Log aktivitas sistem untuk transparansi dan keamanan.</p>
        </div>
        
        <!-- Search & Filter Controls -->
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative w-full sm:w-64 group">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input v-model="search" type="text" placeholder="Cari aktivitas atau user..." class="w-full bg-white border border-[#E8EEF6] rounded-[24px] pl-10 pr-4 py-2.5 text-sm font-medium focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)]" />
            </div>
            
            <select v-model="roleFilter" class="bg-white border border-[#E8EEF6] rounded-[24px] h-[52px] px-6.5 text-sm font-bold text-slate-700 focus:ring-4 focus:ring-blue-500/5 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <option value="">Semua User</option>
                <option value="admin">Admin</option>
                <option value="apoteker">Apoteker</option>
                <option value="gudang">Gudang</option>
            </select>

            <div class="hidden lg:flex items-center gap-3 bg-white border border-[#E8EEF6] p-2 rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div class="w-8 h-8 bg-blue-500 rounded-[16px] flex items-center justify-center text-white text-sm shadow-lg shadow-blue-500/20">🛡️</div>
                <div class="pr-2">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none">System</p>
                    <p class="text-[10px] font-black text-slate-700 mt-0.5">Integrity</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 text-slate-500 text-xs uppercase font-bold border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-6 py-4 text-center">Waktu</th>
                        <th class="px-6 py-4 text-center">User</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                        <th class="px-6 py-4 text-center">Deskripsi</th>
                        <th class="px-6 py-4 text-center">Model</th>
                        <th class="px-6 py-4 text-center">ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!audits.data.length">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">Belum ada data audit trail.</td>
                    </tr>
                    <tr v-for="a in audits.data" :key="a.id" class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4 text-slate-500 text-xs text-center">
                            {{ new Date(a.performed_at).toLocaleString('id-ID', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-col items-center">
                                <span class="text-slate-900 font-bold">{{ a.user?.name || 'System' }}</span>
                                <span class="text-[10px] text-slate-400 uppercase font-bold">{{ a.user?.role || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="getActionClass(a.action)" class="px-2.5 py-1 rounded-[16px] text-[10px] font-black border uppercase tracking-wider">
                                {{ a.action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 font-medium text-center">{{ a.description }}</td>
                        <td class="px-6 py-4 text-slate-400 text-[10px] font-mono text-center">{{ a.auditable_type?.split('\\').pop() || '-' }}</td>
                        <td class="px-6 py-4 text-slate-400 font-mono text-center">{{ a.auditable_id || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="audits.links.length > 3" class="px-6 py-4 border-t border-[#E8EEF6] flex items-center justify-center gap-1 bg-slate-50/30">
            <template v-for="l in audits.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" :class="l.active ? 'bg-blue-600 text-white font-medium shadow-[0_8px_24px_rgba(15,23,42,0.04)]' : 'bg-white border border-[#E8EEF6] text-slate-600 hover:bg-slate-50'" class="px-3.5 py-1.5 rounded-[16px] text-sm transition-all" v-html="l.label" />
                <span v-else class="px-3.5 py-1.5 text-slate-400 text-sm" v-html="l.label"></span>
            </template>
        </div>
    </div>
</div>
</ControlLayout>
</template>
