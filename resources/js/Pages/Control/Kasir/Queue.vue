<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';
import { useSmartSync } from '@/Composables/useSmartSync';

defineOptions({ layout: ControlLayout });

const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '';
const formatTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';

const props = defineProps({
    prescriptions: [Object, Array],
    sales: [Object, Array],
    filters: { type: Object, default: () => ({}) },
});

// Sinkronisasi antrean live: reload resep & penjualan setiap 10 detik atau seketika saat kasir membuka tab
useSmartSync({
    only: ['prescriptions', 'sales'],
    interval: 10000,
    throttle: 3000
});

const search = ref(props.filters?.search || '');
const startDate = ref(props.filters?.start_date || new Date().toISOString().split('T')[0]);
const endDate = ref(props.filters?.end_date || new Date().toISOString().split('T')[0]);

const applySearch = () => {
    router.get(route(route().current()), {
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        search: search.value || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        showProgress: false,
        only: ['prescriptions', 'filters'],
    });
};

const applyDateFilter = () => {
    router.get(route(route().current()), {
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        showProgress: false,
        only: ['sales', 'filters'],
    });
};

const debouncedSearch = useDebounceFn(() => {
    applySearch();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

watch([startDate, endDate], () => {
    applyDateFilter();
});

const clearSearch = () => {
    search.value = '';
    applySearch();
};

const activePrescriptions = computed(() => {
    let raw = [];
    if (props.prescriptions?.data) raw = props.prescriptions.data;
    else if (Array.isArray(props.prescriptions)) raw = props.prescriptions;

    if (search.value && search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        raw = raw.filter(rx => 
            rx.prescription_number?.toLowerCase().includes(q) ||
            rx.patient?.name?.toLowerCase().includes(q) ||
            rx.doctor_name?.toLowerCase().includes(q)
        );
    }
    return raw;
});

const totalPrescriptions = computed(() => {
    if (props.prescriptions?.total !== undefined) return props.prescriptions.total;
    return activePrescriptions.value.length;
});

const activeSales = computed(() => {
    if (props.sales?.data) return props.sales.data;
    if (Array.isArray(props.sales)) return props.sales;
    return [];
});

const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const getUsiaResep = (d) => Math.floor(Math.abs(new Date().setHours(0,0,0,0) - new Date(d).setHours(0,0,0,0)) / 86400000);

// Kalkulasi Rekap Shift Kasir (X-Report)
const shiftSummary = computed(() => {
    const list = activeSales.value || [];
    const validSales = list.filter(s => s.status !== 'cancelled');
    const cancelledSales = list.filter(s => s.status === 'cancelled');
    const totalTunai = validSales.filter(s => s.payment_method === 'tunai').reduce((acc, s) => acc + Number(s.total_amount || 0), 0);
    const totalNonTunai = validSales.filter(s => s.payment_method !== 'tunai').reduce((acc, s) => acc + Number(s.total_amount || 0), 0);
    const totalOmzet = totalTunai + totalNonTunai;
    return {
        totalTunai,
        totalNonTunai,
        totalOmzet,
        count: validSales.length,
        cancelledCount: cancelledSales.length,
    };
});

const printShiftReport = () => {
    window.print();
};

// Modal State Otorisasi Void Admin
const showVoidModal = ref(false);
const voidTargetSale = ref(null);
const voidForm = ref({
    admin_username: '',
    admin_password: '',
    reason: '',
});
const isSubmittingVoid = ref(false);
const voidErrorMessage = ref('');

const openVoidModal = (sale) => {
    voidTargetSale.value = sale;
    voidForm.value = {
        admin_username: '',
        admin_password: '',
        reason: '',
    };
    voidErrorMessage.value = '';
    showVoidModal.value = true;
};

const closeVoidModal = () => {
    if (isSubmittingVoid.value) return;
    showVoidModal.value = false;
    voidTargetSale.value = null;
    voidErrorMessage.value = '';
};

const submitVoid = () => {
    if (!voidTargetSale.value) return;
    if (!voidForm.value.admin_username || !voidForm.value.admin_password) {
        voidErrorMessage.value = 'Email/Nama dan password Admin wajib diisi.';
        return;
    }

    isSubmittingVoid.value = true;
    voidErrorMessage.value = '';

    router.post(route('control.kasir.sales.void', voidTargetSale.value.id), voidForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeVoidModal();
        },
        onError: (errs) => {
            voidErrorMessage.value = errs.admin_password || errs.error || Object.values(errs)[0] || 'Terjadi kesalahan saat membatalkan transaksi.';
        },
        onFinish: () => {
            isSubmittingVoid.value = false;
        }
    });
};
</script>

<template>
    <Head title="Antrean Kasir" />

    <div class="space-y-8">
        <!-- BENTO COCKPIT HEADER (OPSI A) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- SISI KIRI: KONSOL OPERASIONAL KASIR & ACTION BAR (lg:col-span-8) -->
            <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3.5 sm:p-4 shadow-2xs flex flex-col justify-between gap-3">
                <!-- Baris 1: Status Badge & Judul Layar Kasir -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60 mb-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            POINT OF SALE · KASIR APOTEK
                        </div>
                        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                            Antrean Pembayaran & Kasir
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-0.5 leading-relaxed">
                            Pusat validasi dan penuntasan transaksi resep dokter serta kasir mandiri apotek.
                        </p>
                    </div>
                    
                    <div class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium text-slate-500 dark:text-muted-foreground bg-slate-100/80 dark:bg-muted/60 shrink-0 self-start sm:self-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>Kasir Aktif</span>
                    </div>
                </div>

                <!-- Baris 2: Search Input & Tombol Pintas Penjualan Bebas (OTC) -->
                <div class="flex flex-col sm:flex-row items-center gap-2.5 w-full pt-1">
                    <div class="relative flex-1 w-full">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari no. resep, nama pasien, atau dokter..." 
                            class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <button 
                                v-if="search" 
                                type="button" 
                                @click="clearSearch" 
                                class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                                title="Hapus pencarian"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <Link 
                        :href="route('control.kasir.otc.index')"
                        class="h-9 px-3.5 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-xs font-semibold shadow-2xs transition-colors shrink-0 w-full sm:w-auto cursor-pointer"
                        title="Buka Kasir Penjualan Langsung OTC"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Kasir Obat Bebas (OTC)</span>
                    </Link>
                </div>
            </div>

            <!-- SISI KANAN: 2 LIVE METRIK KARTU BERDAMPINGAN (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
                <!-- Card 1: Antrean Resep Menunggu Bayar -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            ANTREAN RESEP
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-1 truncate">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ totalPrescriptions }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Resep</span>
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                        Siap diproses bayar
                    </p>
                </div>

                <!-- Card 2: Kas Laci Fisik (Tunai) -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            KAS LACI FISIK
                        </span>
                        <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-1 truncate">
                        <span class="text-base sm:text-lg lg:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                            {{ formatRupiah(shiftSummary.totalTunai) }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                        Uang fisik shift ini
                    </p>
                </div>
            </div>
        </div>

        <!-- Antrean Resep Siap Bayar -->
        <section class="bg-card rounded-xl border border-border shadow-2xs overflow-hidden no-print">
            <div class="p-4 sm:p-5 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold tracking-tight text-foreground">Antrean Resep Siap Bayar</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar resep yang sudah selesai diracik dan menunggu penyelesaian pembayaran pasien.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 font-semibold h-8 px-3 rounded-md text-xs flex items-center justify-center shrink-0">
                        {{ totalPrescriptions }} Antrean Aktif
                    </div>
                </div>
            </div>

            <!-- Zen Empty State -->
            <div v-if="activePrescriptions.length === 0" class="py-12 px-6 flex flex-col items-center justify-center text-center">
                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-800/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-3 shadow-2xs">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-foreground">Semua Antrean Resep Selesai</h3>
                <p class="text-xs text-muted-foreground mt-1 max-w-sm">
                    Tidak ada resep dokter yang menunggu pembayaran saat ini. Kasir siap melayani antrean resep baru atau penjualan obat bebas (OTC).
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[790px]">
                    <thead class="bg-muted/50 border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[140px] whitespace-nowrap">No Resep</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[160px] whitespace-nowrap">Pasien</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[180px] whitespace-nowrap">Dokter & Tgl</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[80px] whitespace-nowrap">Jml Item</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[120px] whitespace-nowrap">Usia Resep</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[110px] whitespace-nowrap">Aksi</th>
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

            <!-- Prescriptions Pagination -->
            <div v-if="prescriptions?.links?.length > 3" class="px-4 py-3 bg-muted/20 border-t border-border flex items-center justify-between">
                <span class="text-xs text-muted-foreground font-medium">Halaman {{ prescriptions?.current_page || 1 }} dari {{ prescriptions?.last_page || 1 }} (Total {{ prescriptions?.total || 0 }} antrean)</span>
                <div class="flex items-center gap-1">
                    <template v-for="(l, i) in (prescriptions?.links || [])" :key="i">
                        <Link 
                            v-if="l.url" 
                            :href="l.url"
                            preserve-state 
                            preserve-scroll 
                            :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                            class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                            v-html="l.label"
                        />
                        <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium border border-input opacity-50 rounded-md" v-html="l.label"></span>
                    </template>
                </div>
            </div>
        </section>

        <!-- BUKU KAS & RIWAYAT TRANSAKSI SHIFT (INTEGRATED & BEBAS DUPLIKASI) -->
        <section id="shift-report-print" class="bg-card rounded-xl border border-border shadow-2xs overflow-hidden">
            <!-- Header Bar: Judul, Date Range Picker, & Tombol Cetak Slip -->
            <div class="p-4 sm:p-5 border-b border-border flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-muted/20">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold tracking-tight text-foreground">Buku Kas & Riwayat Transaksi</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground border border-slate-200/60 dark:border-border">Shift Berjalan</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">Rekapitulasi omzet penjualan dan riwayat nota kasir periode {{ startDate === endDate ? formatDate(startDate) : formatDate(startDate) + ' s/d ' + formatDate(endDate) }}.</p>
                    </div>
                </div>

                <div class="no-print flex flex-wrap items-center gap-2.5 self-start lg:self-center">
                    <!-- Date Picker Filter -->
                    <div class="flex items-center gap-1.5 bg-background rounded-lg border border-input px-2.5 h-9 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <input type="date" v-model="startDate" class="border-none bg-transparent focus:outline-none text-xs font-medium text-foreground h-full cursor-pointer" title="Tanggal Awal" />
                        <span class="text-muted-foreground text-xs font-medium">-</span>
                        <input type="date" v-model="endDate" class="border-none bg-transparent focus:outline-none text-xs font-medium text-foreground h-full cursor-pointer" title="Tanggal Akhir" />
                    </div>

                    <!-- Cetak Slip Shift Button -->
                    <button
                        type="button"
                        @click="printShiftReport"
                        class="inline-flex items-center gap-1.5 bg-background hover:bg-muted text-foreground text-xs font-medium h-9 px-3.5 rounded-lg border border-input transition-colors shadow-2xs cursor-pointer"
                        title="Cetak X-Report / Slip Rekap Shift"
                    >
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Cetak Slip Shift</span>
                    </button>
                </div>
            </div>

            <!-- Integrated Micro Stat Ribbon (Fintech-Grade, Zero Duplikasi) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-border border-b border-border bg-card">
                <!-- Stat 1: Total Omzet Penjualan -->
                <div class="p-3.5 sm:px-5 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-semibold text-muted-foreground block uppercase tracking-wider font-sans">Total Omzet Shift</span>
                        <span class="text-base sm:text-lg font-bold font-sans tracking-tight text-foreground mt-0.5 block">{{ formatRupiah(shiftSummary.totalOmzet) }}</span>
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-muted text-muted-foreground border border-border font-sans">
                        {{ shiftSummary.count }} Lunas
                    </span>
                </div>

                <!-- Stat 2: Non-Tunai (QRIS / EDC) -->
                <div class="p-3.5 sm:px-5 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-semibold text-sky-600 dark:text-sky-400 block uppercase tracking-wider font-sans">Non-Tunai (QRIS / Transfer)</span>
                        <span class="text-base sm:text-lg font-bold font-sans tracking-tight text-sky-700 dark:text-sky-300 mt-0.5 block">{{ formatRupiah(shiftSummary.totalNonTunai) }}</span>
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 font-sans">
                        Digital
                    </span>
                </div>

                <!-- Stat 3: Transaksi Batal (Void) -->
                <div class="p-3.5 sm:px-5 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-semibold text-muted-foreground block uppercase tracking-wider font-sans">Transaksi Batal (Void)</span>
                        <span class="text-base sm:text-lg font-bold font-sans tracking-tight text-foreground mt-0.5 block">{{ shiftSummary.cancelledCount }} Nota</span>
                    </div>
                    <span 
                        class="text-xs font-medium px-2 py-0.5 rounded-md border font-sans"
                        :class="shiftSummary.cancelledCount > 0 ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800' : 'bg-muted text-muted-foreground border-border'"
                    >
                        {{ shiftSummary.cancelledCount > 0 ? 'Stok Diretur' : 'Nihil' }}
                    </span>
                </div>
            </div>

            <!-- Tabel Riwayat Transaksi -->
            <div v-if="activeSales.length === 0" class="py-12 px-6 text-center text-muted-foreground text-xs font-medium">
                Belum ada transaksi penjualan pada rentang tanggal yang dipilih.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[800px]">
                    <thead class="bg-muted/50 border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[150px] whitespace-nowrap">No Invoice</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[120px] whitespace-nowrap">Pasien</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[150px] whitespace-nowrap">Total Pembelian</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Metode</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[90px] whitespace-nowrap">Waktu</th>
                            <th class="no-print px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="sale in activeSales" :key="sale.id" class="hover:bg-muted/50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-foreground text-sm">{{ sale.sale_number }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-medium text-foreground text-sm">{{ sale.prescription?.patient?.name || 'OTC' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span :class="sale.status === 'cancelled' ? 'line-through text-muted-foreground' : 'text-emerald-600 dark:text-emerald-400'" class="font-semibold text-sm">
                                    {{ formatRupiah(sale.total_amount) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="uppercase font-medium text-xs bg-muted border border-border text-muted-foreground px-2 py-0.5 rounded-md">
                                    {{ sale.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="sale.status === 'cancelled'" class="inline-flex items-center text-[11px] font-semibold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 px-2 py-0.5 rounded-md">
                                    Batal (Void)
                                </span>
                                <span v-else class="inline-flex items-center text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 px-2 py-0.5 rounded-md">
                                    Lunas
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-medium text-foreground text-sm">{{ formatTime(sale.created_at) }}</span>
                            </td>
                            <td class="no-print px-4 py-3 text-center">
                                <div class="inline-flex items-center gap-3">
                                    <Link :href="route('control.kasir.sales.receipt', sale.id)" class="text-primary hover:underline font-medium text-xs">
                                        Lihat Struk
                                    </Link>
                                    <button
                                        v-if="sale.status !== 'cancelled'"
                                        type="button"
                                        @click="openVoidModal(sale)"
                                        class="text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:underline font-medium text-xs"
                                    >
                                        Void
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Modal Otorisasi Void Admin -->
        <Teleport to="body">
            <div v-if="showVoidModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
                <div class="bg-card w-full max-w-md rounded-lg border border-border shadow-lg p-6 space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-bold text-foreground">Otorisasi Pembatalan (Void)</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Wajib diverifikasi oleh Admin/Supervisor.</p>
                        </div>
                        <button @click="closeVoidModal" :disabled="isSubmittingVoid" class="text-muted-foreground hover:text-foreground text-sm font-medium">✕</button>
                    </div>

                    <div class="bg-muted/40 rounded-md p-3 text-xs space-y-1 border border-border/60">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Faktur Penjualan:</span>
                            <span class="font-bold text-foreground">{{ voidTargetSale?.sale_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total Transaksi:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ formatRupiah(voidTargetSale?.total_amount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Metode Bayar:</span>
                            <span class="uppercase font-medium text-foreground">{{ voidTargetSale?.payment_method }}</span>
                        </div>
                    </div>

                    <div v-if="voidErrorMessage" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 px-3 py-2 rounded-md text-xs font-medium">
                        {{ voidErrorMessage }}
                    </div>

                    <form @submit.prevent="submitVoid" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Email / Nama Admin</label>
                            <input
                                type="text"
                                v-model="voidForm.admin_username"
                                required
                                placeholder="Masukkan email atau nama lengkap admin"
                                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Password Admin</label>
                            <input
                                type="password"
                                v-model="voidForm.admin_password"
                                required
                                placeholder="Masukkan password admin"
                                class="w-full h-9 px-3 rounded-md border border-input bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Alasan Pembatalan (Opsional)</label>
                            <textarea
                                v-model="voidForm.reason"
                                rows="2"
                                placeholder="Misal: Salah input obat / Pasien membatalkan pembelian"
                                class="w-full px-3 py-2 rounded-md border border-input bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="closeVoidModal"
                                :disabled="isSubmittingVoid"
                                class="h-9 px-4 rounded-md border border-input bg-background hover:bg-muted text-foreground text-xs font-medium transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmittingVoid"
                                class="h-9 px-4 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition-colors flex items-center gap-2 shadow-sm disabled:opacity-50"
                            >
                                <svg v-if="isSubmittingVoid" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isSubmittingVoid ? 'Memproses...' : 'Konfirmasi & Batalkan Nota' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
