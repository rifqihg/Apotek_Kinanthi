<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });
import { ref, computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import axios from 'axios';

const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '';
const formatTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';

const props = defineProps({
    prescriptions: Array,
    sales: Array,
    filters: Object,
});

const page = usePage();

const form = ref({
    date: props.filters?.date || new Date().toISOString().split('T')[0],
});

const { data: queryQueue, isFetching } = useQuery({
    queryKey: computed(() => ['kasir-queue', form.value.date]),
    queryFn: async () => {
        const res = await axios.get(route('control.kasir.queue'), {
            params: form.value,
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': page.version,
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        return {
            prescriptions: res.data.props.prescriptions,
            sales: res.data.props.sales,
        };
    },
    initialData: () => ({
        prescriptions: props.prescriptions,
        sales: props.sales,
    }),
    placeholderData: (prev) => prev,
});

const activePrescriptions = computed(() => queryQueue.value?.prescriptions || props.prescriptions || []);
const activeSales = computed(() => queryQueue.value?.sales || props.sales || []);

const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const getUsiaResep = (d) => Math.floor(Math.abs(new Date().setHours(0,0,0,0) - new Date(d).setHours(0,0,0,0)) / 86400000);
</script>

<template>
    <Head title="Antrian Kasir" />

    <div class="space-y-6">
        <!-- Antrian Pembayaran -->
        <section class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
            <div class="p-6 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">Antrian Resep Siap Bayar</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar resep yang sudah selesai diracik dan menunggu pembayaran.</p>
                </div>
                <div class="bg-primary/10 border border-primary/20 text-primary font-medium h-9 px-3.5 rounded-md text-xs flex items-center justify-center shrink-0">
                    {{ activePrescriptions.length }} Menunggu
                </div>
            </div>

            <div v-if="activePrescriptions.length === 0" class="p-12 text-center text-muted-foreground text-xs font-medium">
                Belum ada resep yang berstatus Siap Diambil saat ini.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">No Resep</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Pasien</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Dokter & Tgl</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jml Item</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Usia Resep</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="rx in activePrescriptions" :key="rx.id" class="hover:bg-muted/50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-foreground text-sm">{{ rx.prescription_number }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-foreground text-sm">{{ rx.patient?.name || 'Tidak Diketahui' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground text-sm">{{ rx.doctor_name }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ formatDate(rx.prescription_date) }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center bg-muted border border-border text-foreground font-medium w-7 h-7 rounded-md text-xs">
                                    {{ rx.prescription_items?.length || 0 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-foreground text-sm">{{ getUsiaResep(rx.prescription_date) }} Hari</span>
                                    <span v-if="getUsiaResep(rx.prescription_date) > 30" class="mt-1 font-semibold text-xs bg-rose-50 border border-rose-200 text-rose-700 px-2 py-0.5 rounded-md">Kadaluarsa</span>
                                    <span v-else-if="getUsiaResep(rx.prescription_date) > 25" class="mt-1 font-semibold text-xs bg-amber-50 border border-amber-200 text-amber-700 px-2 py-0.5 rounded-md">Segera</span>
                                    <span v-else class="mt-1 font-semibold text-xs bg-emerald-50 border border-emerald-200 text-emerald-700 px-2 py-0.5 rounded-md">Aman</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('control.kasir.checkout', rx.id)" class="inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium h-8 px-3.5 rounded-md text-xs transition-colors shadow-sm">
                                    Proses Bayar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Riwayat Transaksi -->
        <section class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
            <div class="p-6 border-b border-border flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">Riwayat Transaksi</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar transaksi yang sudah Anda selesaikan berdasarkan tanggal.</p>
                </div>
                <div class="shrink-0 flex items-center gap-2 bg-background rounded-md border border-input px-2 h-9">
                    <svg v-if="isFetching" class="animate-spin h-4 w-4 text-primary ml-1" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <input type="date" v-model="form.date" class="border-none bg-transparent focus:outline-none text-xs font-medium text-foreground h-full cursor-pointer" />
                </div>
            </div>

            <div v-if="activeSales.length === 0" class="p-12 text-center text-muted-foreground text-xs font-medium">
                Belum ada transaksi pada tanggal tersebut.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">No Invoice</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Pasien</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Metode</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Waktu</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="sale in activeSales" :key="sale.id" class="hover:bg-muted/50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-foreground text-sm">{{ sale.sale_number }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-foreground text-sm">{{ sale.prescription?.patient?.name || 'OTC' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400 text-sm">{{ formatRupiah(sale.total_amount) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="uppercase font-medium text-xs bg-muted border border-border text-muted-foreground px-2 py-0.5 rounded-md">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs text-muted-foreground">{{ formatTime(sale.created_at) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('control.kasir.sales.receipt', sale.id)" class="text-primary hover:underline font-medium text-xs">Lihat Struk</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
