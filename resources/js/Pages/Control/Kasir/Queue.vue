<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { useDebounceFn } from '@vueuse/core';

defineOptions({ layout: ControlLayout });

const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '';
const formatTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';

const props = defineProps({
    prescriptions: [Object, Array],
    sales: [Object, Array],
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
const date = ref(props.filters?.date || new Date().toISOString().split('T')[0]);
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(route(route().current()), {
        date: date.value || undefined,
        search: search.value || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        }
    });
};

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

watch(search, (newVal, oldVal) => {
    if (newVal !== oldVal) debouncedSearch();
});

watch(date, () => {
    applyFilters();
});

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const activePrescriptions = computed(() => {
    if (props.prescriptions?.data) return props.prescriptions.data;
    if (Array.isArray(props.prescriptions)) return props.prescriptions;
    return [];
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
    <Head title="Antrian Kasir" />

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <div class="lg:col-span-12 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 mb-1.5">
                        POINT OF SALE · KASIR UTAMA
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Antrian Pembayaran & Kasir
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Proses transaksi pembayaran resep siap bayar dan manajemen antrian pasien.
                    </p>
                </div>

                <!-- Right Integrated Control Panel -->
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                    <div class="relative w-full sm:w-56">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" type="text" placeholder="Cari no. resep / pasien..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                            <svg v-if="isSearching" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <button v-else-if="search" type="button" @click="clearSearch" class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer" title="Hapus pencarian">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <button class="h-9 px-3.5 inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm w-full sm:w-auto justify-center cursor-not-allowed opacity-80" title="Fitur scanner sedang dalam pengembangan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        Scan Barcode
                    </button>
                </div>
            </div>
        </div>

        <!-- Antrian Pembayaran -->
        <section class="bg-card rounded-lg border border-border shadow-sm overflow-hidden no-print">
            <div class="p-6 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">Antrian Resep Siap Bayar</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar resep yang sudah selesai diracik dan menunggu pembayaran.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-primary/10 border border-primary/20 text-primary font-medium h-9 px-3.5 rounded-md text-xs flex items-center justify-center shrink-0">
                        {{ totalPrescriptions }} Menunggu
                    </div>
                </div>
            </div>

            <div v-if="activePrescriptions.length === 0" class="p-12 text-center text-muted-foreground text-xs font-medium">
                Belum ada resep yang berstatus Siap Diambil saat ini.
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

        <!-- Ringkasan Shift Kasir (X-Report) -->
        <section id="shift-report-print" class="bg-card rounded-lg border border-border shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">
                        Rp
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">Ringkasan Kas Shift Berjalan</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Rekapitulasi transaksi kasir aktif pada tanggal {{ formatDate(date) }}.</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="printShiftReport"
                    class="no-print inline-flex items-center gap-2 bg-muted hover:bg-muted/80 text-foreground text-xs font-semibold h-9 px-3.5 rounded-md border border-border transition-colors shadow-sm self-start sm:self-auto"
                >
                    <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Cetak Slip Shift
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4">
                <div class="p-3.5 rounded-md bg-muted/40 border border-border/60">
                    <span class="text-xs text-muted-foreground font-medium block">Total Omzet Shift</span>
                    <span class="text-base font-bold text-foreground mt-1 block">{{ formatRupiah(shiftSummary.totalOmzet) }}</span>
                    <span class="text-[11px] text-muted-foreground mt-0.5 block">{{ shiftSummary.count }} transaksi lunas</span>
                </div>
                <div class="p-3.5 rounded-md bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/40">
                    <span class="text-xs text-emerald-700 dark:text-emerald-400 font-medium block">Penerimaan Tunai (Cash)</span>
                    <span class="text-base font-bold text-emerald-700 dark:text-emerald-300 mt-1 block">{{ formatRupiah(shiftSummary.totalTunai) }}</span>
                    <span class="text-[11px] text-emerald-600/80 dark:text-emerald-400/70 mt-0.5 block">Uang fisik laci kasir</span>
                </div>
                <div class="p-3.5 rounded-md bg-sky-50/50 dark:bg-sky-950/20 border border-sky-200/60 dark:border-sky-800/40">
                    <span class="text-xs text-sky-700 dark:text-sky-400 font-medium block">Non-Tunai (QRIS / Transfer)</span>
                    <span class="text-base font-bold text-sky-700 dark:text-sky-300 mt-1 block">{{ formatRupiah(shiftSummary.totalNonTunai) }}</span>
                    <span class="text-[11px] text-sky-600/80 dark:text-sky-400/70 mt-0.5 block">Settlement digital</span>
                </div>
                <div class="p-3.5 rounded-md bg-muted/40 border border-border/60">
                    <span class="text-xs text-muted-foreground font-medium block">Transaksi Batal (Void)</span>
                    <span class="text-base font-bold text-foreground mt-1 block">{{ shiftSummary.cancelledCount }} Nota</span>
                    <span class="text-[11px] text-rose-600 dark:text-rose-400 mt-0.5 block font-medium" v-if="shiftSummary.cancelledCount > 0">Stok telah diretur</span>
                    <span class="text-[11px] text-muted-foreground mt-0.5 block" v-else>Tidak ada pembatalan</span>
                </div>
            </div>
        </section>

        <!-- Riwayat Transaksi -->
        <section class="bg-card rounded-lg border border-border shadow-sm overflow-hidden">
            <div class="p-6 border-b border-border flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">Riwayat Transaksi</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar transaksi yang sudah Anda selesaikan berdasarkan tanggal.</p>
                </div>
                <div class="no-print shrink-0 flex items-center gap-2 bg-background rounded-md border border-input px-2 h-9">
                    <svg v-if="isSearching" class="animate-spin h-4 w-4 text-primary ml-1" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <input type="date" v-model="date" class="border-none bg-transparent focus:outline-none text-xs font-medium text-foreground h-full cursor-pointer" />
                </div>
            </div>

            <div v-if="activeSales.length === 0" class="p-12 text-center text-muted-foreground text-xs font-medium">
                Belum ada transaksi pada tanggal tersebut.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[800px]">
                    <thead class="bg-muted/50 border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[150px] whitespace-nowrap">No Invoice</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[150px] whitespace-nowrap">Pasien</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[120px] whitespace-nowrap">Total</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[90px] whitespace-nowrap">Metode</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[100px] whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[90px] whitespace-nowrap">Waktu</th>
                            <th class="no-print px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[100px] whitespace-nowrap">Aksi</th>
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
                                <span :class="sale.status === 'cancelled' ? 'line-through text-muted-foreground' : 'text-emerald-600 dark:text-emerald-400'" class="font-semibold text-sm">
                                    {{ formatRupiah(sale.total_amount) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3">
                                <span class="text-xs text-muted-foreground">{{ formatTime(sale.created_at) }}</span>
                            </td>
                            <td class="no-print px-4 py-3 text-right">
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
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
