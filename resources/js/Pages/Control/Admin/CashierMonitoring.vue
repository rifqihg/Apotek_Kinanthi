<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    filters: Object,
    kpis: Object,
    transactions: [Array, Object],
});

const page = usePage();
const activePage = ref(1);

const form = ref({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
});

// Reset page when date filter changes
watch(form, () => {
    activePage.value = 1;
}, { deep: true });

const expandedRows = ref({});

const toggleRow = (id) => { expandedRows.value[id] = !expandedRows.value[id]; };

const { data: queryMonitoring, isFetching } = useQuery({
    queryKey: computed(() => ['admin-cashier-monitoring', form.value.start_date, form.value.end_date, activePage.value]),
    queryFn: async () => {
        const res = await axios.get(route('control.admin.reports.cashier-monitoring'), {
            params: {
                start_date: form.value.start_date,
                end_date: form.value.end_date,
                page: activePage.value,
            },
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        return {
            kpis: res.data.props.kpis,
            transactions: res.data.props.transactions,
        };
    },
    initialData: () => ({
        kpis: props.kpis,
        transactions: props.transactions,
    }),
    staleTime: 60000,
    gcTime: 300000,
    refetchOnWindowFocus: false,
    placeholderData: (prev) => prev,
});

const activeKpis = computed(() => queryMonitoring.value?.kpis || props.kpis || {});

const rawTransactions = computed(() => queryMonitoring.value?.transactions || props.transactions || []);
const activeTransactions = computed(() => {
    const t = rawTransactions.value;
    if (Array.isArray(t)) return t;
    return t?.data || [];
});

const activePagination = computed(() => {
    const t = rawTransactions.value;
    return (t && !Array.isArray(t) && t.links) ? t : null;
});

const goToPage = (url) => {
    if (!url) return;
    try {
        const urlObj = new URL(url, window.location.origin);
        const p = urlObj.searchParams.get('page');
        if (p) activePage.value = Number(p);
    } catch (e) {
        // fallback
    }
};

const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
</script>

<template>
    <Head title="Monitoring Transaksi" />

    <div>

        <!-- Filter -->
        <div class="mb-6 bg-card rounded-lg border border-border p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Filter Rentang Waktu</span>
                <svg v-if="isFetching" class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Dari Tanggal</label>
                    <input type="date" v-model="form.start_date" class="w-full h-10 rounded-md border-input bg-background focus:border-emerald-500 focus:ring-emerald-500 transition-colors font-medium text-foreground text-sm">
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-semibold text-muted-foreground mb-1.5">Sampai Tanggal</label>
                    <input type="date" v-model="form.end_date" class="w-full h-10 rounded-md border-input bg-background focus:border-emerald-500 focus:ring-emerald-500 transition-colors font-medium text-foreground text-sm">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Transaksi</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ activeKpis.total_transactions }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Penjualan</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ formatRupiah(activeKpis.total_sales) }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Diskon</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-destructive">{{ formatRupiah(activeKpis.total_discount) }}</p>
            </div>
            <div class="bg-card p-5 rounded-lg border border-border shadow-sm">
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Total Laba</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600">{{ formatRupiah(activeKpis.total_laba) }}</p>
            </div>
        </div>

        <!-- Tabel Riwayat Transaksi -->
        <div class="bg-card rounded-lg border border-border shadow-sm mb-8 overflow-hidden">
            <div class="p-5 border-b border-border flex justify-between items-center">
                <h2 class="text-lg font-bold text-foreground">Laporan Transaksi & Pergerakan Stok</h2>
                
                <a :href="route('control.admin.reports.sales.export', { start_date: form.start_date, end_date: form.end_date })" 
                   class="inline-flex items-center justify-center h-9 px-4 rounded-md bg-emerald-600 text-white hover:bg-emerald-700 font-medium text-sm transition-colors shadow-sm gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Excel
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-muted/50 border-b border-border">
                            <th class="w-12 px-4 py-3 text-center"></th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Waktu</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">No Transaksi</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Modal (HPP)</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Subtotal</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Diskon</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Penjualan</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Laba</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <template v-for="data in activeTransactions" :key="data.id">
                            <!-- Main Row -->
                            <tr @click="toggleRow(data.id)" class="hover:bg-muted/40 transition-colors cursor-pointer group">
                                <td class="px-4 py-3 text-center">
                                    <div class="w-6 h-6 rounded-md bg-muted group-hover:bg-muted/80 flex items-center justify-center text-muted-foreground group-hover:text-foreground transition-colors mx-auto">
                                        <svg :class="{'rotate-180': expandedRows[data.id]}" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-muted-foreground">{{ formatDate(data.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-foreground text-sm">{{ data.sale_number }}</p>
                                    <p class="text-xs font-medium text-muted-foreground mt-0.5">Kasir: {{ data.kasir_name }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-muted-foreground text-sm">{{ formatRupiah(data.modal) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-foreground text-sm">{{ formatRupiah(data.subtotal) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span v-if="data.discount_amount > 0" class="font-semibold text-destructive text-sm">
                                        {{ formatRupiah(data.discount_amount) }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">Rp0</span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-foreground text-sm">{{ formatRupiah(data.total_amount) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-600 text-sm">{{ formatRupiah(data.laba) }}</td>
                            </tr>
                            
                            <!-- Expanded Details Row -->
                            <tr v-show="expandedRows[data.id]" class="bg-muted/20">
                                <td colspan="8" class="p-0 border-t-0">
                                    <div class="p-4 sm:p-6 border-y border-border">
                                        <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Rincian Obat & Stok Keluar</h3>
                                        
                                        <div class="bg-card border border-border rounded-lg overflow-hidden">
                                            <table class="w-full text-left text-sm">
                                                <thead class="bg-muted/50 border-b border-border">
                                                    <tr>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase">Item Terjual</th>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase text-center">Kategori</th>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase text-center">Qty Keluar</th>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase text-right">Modal/HPP Total</th>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase text-right">Harga Jual Total</th>
                                                        <th class="px-4 py-2.5 font-semibold text-muted-foreground text-xs uppercase text-center">Sisa Stok Real-time</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border">
                                                    <tr v-for="item in data.items" :key="item.id" class="hover:bg-muted/40 transition-colors">
                                                        <td class="px-4 py-2.5 font-medium text-foreground">{{ item.item_name }}</td>
                                                        <td class="px-4 py-2.5 text-center">
                                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-md" 
                                                                :class="item.item_type === 'racikan' ? 'bg-purple-500/10 text-purple-600 border border-purple-500/20' : 'bg-primary/10 text-primary border border-primary/20'">
                                                                {{ item.item_type === 'racikan' ? 'Racikan' : 'Obat Jadi' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-2.5 text-center font-semibold text-foreground">
                                                            {{ item.quantity }} <span class="text-xs text-muted-foreground font-normal ml-1">{{ item.unit }}</span>
                                                        </td>
                                                        <td class="px-4 py-2.5 text-right font-medium text-muted-foreground">{{ formatRupiah(item.modal) }}</td>
                                                        <td class="px-4 py-2.5 text-right font-medium text-foreground">{{ formatRupiah(item.subtotal) }}</td>
                                                        <td class="px-4 py-2.5 text-center">
                                                            <div v-if="item.item_type === 'obat_jadi'" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold" :class="item.sisa_stok <= 10 ? 'bg-destructive/10 text-destructive border border-destructive/20' : 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20'">
                                                                <span class="font-bold">{{ item.sisa_stok }}</span>
                                                                <span>{{ item.unit }}</span>
                                                            </div>
                                                            <span v-else class="text-xs text-muted-foreground">-</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="activeTransactions.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center text-muted-foreground font-medium text-sm">Belum ada aktivitas penjualan dalam rentang tanggal yang dipilih.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div v-if="activePagination && activePagination.data && activePagination.data.length > 0" class="px-6 py-4 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">
                    Halaman {{ activePagination.current_page }} dari {{ activePagination.last_page }} (Total {{ activePagination.total }} transaksi)
                </span>
                
                <div class="flex items-center gap-1">
                    <button v-for="(link, i) in activePagination.links" :key="i"
                        type="button"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                            link.active ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted',
                            !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                        ]"
                    />
                </div>
            </div>
        </div>

    </div>
</template>
