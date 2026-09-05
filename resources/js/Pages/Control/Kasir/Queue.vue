<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref, watch } from 'vue';

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateString));
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(dateString));
};

const props = defineProps({
    prescriptions: Array,
    sales: Array,
    filters: Object,
});

const form = ref({
    date: props.filters?.date || new Date().toISOString().split('T')[0],
});

const filterData = () => {
    router.get(route('control.kasir.queue'), form.value, { preserveState: true });
};

watch(() => form.value.date, filterData);

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
};

const getUsiaResep = (date) => {
    const today = new Date();
    const rxDate = new Date(date);
    today.setHours(0,0,0,0);
    rxDate.setHours(0,0,0,0);
    const diffTime = Math.abs(today - rxDate);
    return Math.floor(diffTime / (1000 * 60 * 60 * 24));
};
</script>

<template>
    <Head title="Antrian Kasir" />

    <ControlLayout>
        <template #header-title>Kasir - Antrian Pembayaran</template>

        <div class="space-y-6">
            <!-- Antrian Pembayaran -->
            <section class="bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
                <div class="p-6 border-b border-[#E8EEF6] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Antrian Resep Siap Bayar</h2>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Daftar resep yang sudah selesai diracik dan menunggu pembayaran.</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 text-blue-700 font-bold h-[52px] px-6 rounded-[16px] text-xs uppercase tracking-widest flex items-center justify-center shrink-0">
                        {{ prescriptions.length }} Menunggu
                    </div>
                </div>

                <div v-if="prescriptions.length === 0" class="p-12 text-center">
                    <div class="w-16 h-16 mx-auto bg-slate-50 text-slate-300 rounded-[16px] flex items-center justify-center mb-4 border border-[#E8EEF6]">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Tidak ada antrian</h3>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1.5">Belum ada resep yang berstatus Siap Diambil saat ini.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">No Resep</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pasien</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Dokter & Tgl</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Jml Item</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Usia Resep</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EEF6]">
                            <tr v-for="rx in prescriptions" :key="rx.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-900 text-sm">{{ rx.prescription_number }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-700">{{ rx.patient?.name || 'Tidak Diketahui' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-700 text-sm">{{ rx.doctor_name }}</p>
                                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-widest mt-0.5">{{ formatDate(rx.prescription_date) }}</p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center justify-center bg-slate-50 border border-[#E8EEF6] text-slate-600 font-bold w-8 h-8 rounded-[12px] text-xs">
                                        {{ rx.prescription_items?.length || 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="font-bold text-slate-900 text-sm">{{ getUsiaResep(rx.prescription_date) }} Hari</span>
                                        <span v-if="getUsiaResep(rx.prescription_date) > 30" class="mt-1 uppercase font-black text-[9px] bg-rose-50 border border-rose-100 text-rose-600 px-2.5 py-0.5 rounded-md tracking-widest">Kadaluarsa</span>
                                        <span v-else-if="getUsiaResep(rx.prescription_date) > 25" class="mt-1 uppercase font-black text-[9px] bg-amber-50 border border-amber-100 text-amber-600 px-2.5 py-0.5 rounded-md tracking-widest">Segera</span>
                                        <span v-else class="mt-1 uppercase font-black text-[9px] bg-emerald-50 border border-emerald-100 text-emerald-600 px-2.5 py-0.5 rounded-md tracking-widest">Aman</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('control.kasir.checkout', rx.id)" class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold h-[44px] px-6 rounded-[14px] text-xs uppercase tracking-widest transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95">
                                        Proses Bayar
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Riwayat Transaksi -->
            <section class="bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
                <div class="p-6 border-b border-[#E8EEF6] flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Riwayat Transaksi</h2>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Daftar transaksi yang sudah Anda selesaikan berdasarkan tanggal.</p>
                    </div>
                    <div class="shrink-0 flex items-center bg-slate-50 rounded-[14px] p-1 border border-[#E8EEF6]">
                        <input type="date" v-model="form.date" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-slate-700 h-[36px] px-4 cursor-pointer" />
                    </div>
                </div>

                <div v-if="sales.length === 0" class="p-12 text-center">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Belum ada transaksi pada tanggal tersebut.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">No Invoice</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pasien</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Metode</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Waktu</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EEF6]">
                            <tr v-for="sale in sales" :key="sale.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-900 text-sm">{{ sale.sale_number }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-700">{{ sale.prescription?.patient?.name || 'OTC' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-emerald-600">{{ formatRupiah(sale.total_amount) }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="uppercase font-bold text-[9px] bg-slate-50 border border-[#E8EEF6] text-slate-600 px-2.5 py-1 rounded-md tracking-wider">
                                        {{ sale.payment_method }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[11px] font-bold text-slate-500">{{ formatTime(sale.created_at) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('control.kasir.sales.receipt', sale.id)" class="text-blue-600 hover:text-blue-700 font-bold text-[11px] uppercase tracking-widest transition-colors">Lihat Struk</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </ControlLayout>
</template>
