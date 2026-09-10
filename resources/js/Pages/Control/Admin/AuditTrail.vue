<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: ControlLayout });
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';

const props = defineProps({ 
    audits: Object,
    filters: Object
});

const page = usePage();
const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || '');

// TanStack Vue Query: Instant initialData with reactive background fetch
const { data: queryAudits, isFetching } = useQuery({
    queryKey: computed(() => ['admin-audit-trails', search.value, roleFilter.value]),
    queryFn: async () => {
        if (!search.value.trim() && !roleFilter.value) {
            return props.audits;
        }
        const res = await axios.get(route('control.admin.audit-trails'), {
            params: {
                search: search.value || undefined,
                role: roleFilter.value || undefined,
            },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        return res.data?.props?.audits || props.audits;
    },
    initialData: () => props.audits,
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (previousData) => previousData,
});

const activeAudits = computed(() => queryAudits.value || props.audits);

const getActionClass = (a) =>
    a.includes('CREATE') ? 'bg-emerald-50 text-emerald-600 border-emerald-100' :
    a.includes('UPDATE') ? 'bg-blue-50 text-blue-600 border-blue-100' :
    a.includes('DELETE') ? 'bg-red-50 text-red-600 border-red-100' :
    a.includes('REJECT') ? 'bg-amber-50 text-amber-600 border-amber-100' :
    'bg-slate-50 text-slate-600 border-[#E8EEF6]';
</script>

<template>
<Head title="Audit Trail" />
<div class="p-4 lg:p-6 space-y-6 max-w-[1400px] mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-foreground tracking-tight">Audit Trail</h1>
            <p class="text-muted-foreground text-sm mt-1">Log aktivitas sistem untuk transparansi dan keamanan.</p>
        </div>
        
        <!-- Search & Filter Controls -->
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                    <svg v-if="!isFetching" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    <svg v-else class="animate-spin w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <input v-model="search" type="text" placeholder="Cari aktivitas atau user..." class="w-full h-9 bg-background border border-input rounded-md pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" />
            </div>
            
            <select v-model="roleFilter" class="w-full sm:w-auto h-9 bg-background border border-input rounded-md px-3 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors">
                <option value="">Semua User</option>
                <option value="admin">Admin</option>
                <option value="apoteker">Apoteker</option>
                <option value="gudang">Gudang</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 text-muted-foreground text-xs uppercase font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-center">Waktu</th>
                        <th class="px-4 py-3 text-center">User</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                        <th class="px-4 py-3 text-center">Deskripsi</th>
                        <th class="px-4 py-3 text-center">Model</th>
                        <th class="px-4 py-3 text-center">ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="!activeAudits.data?.length">
                        <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">Belum ada data audit trail.</td>
                    </tr>
                    <tr v-for="a in (activeAudits.data || [])" :key="a.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3 text-muted-foreground text-xs text-center">
                            {{ new Date(a.performed_at).toLocaleString('id-ID', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex flex-col items-center">
                                <span class="text-foreground font-medium">{{ a.user?.name || 'System' }}</span>
                                <span class="text-xs text-muted-foreground uppercase">{{ a.user?.role || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="getActionClass(a.action)" class="px-2 py-0.5 rounded-md text-xs font-semibold border uppercase tracking-wider">
                                {{ a.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-foreground font-normal text-center">{{ a.description }}</td>
                        <td class="px-4 py-3 text-muted-foreground text-xs text-center">{{ a.auditable_type?.split('\\').pop() || '-' }}</td>
                        <td class="px-4 py-3 text-muted-foreground text-center">{{ a.auditable_id || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="activeAudits.links?.length > 3" class="px-4 py-3 border-t border-border flex items-center justify-center gap-1 bg-muted/20">
            <template v-for="l in activeAudits.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" :class="l.active ? 'bg-emerald-600 text-white font-medium shadow-sm' : 'bg-background border border-input text-foreground hover:bg-muted'" class="px-3 py-1 rounded-md text-sm transition-colors" v-html="l.label" />
                <span v-else class="px-3 py-1 text-muted-foreground text-sm" v-html="l.label"></span>
            </template>
        </div>
    </div>
</div>
</template>
