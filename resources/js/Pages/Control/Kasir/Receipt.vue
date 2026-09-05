<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

const props = defineProps({
    sale: Object,
});

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(dateString));
};

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
};

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head title="Struk Pembayaran" />

    <ControlLayout>
        <template #header-title>
            <div class="flex items-center gap-3 no-print">
                <Link :href="route('control.kasir.queue')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <span>Struk Pembayaran</span>
            </div>
        </template>

        <div class="max-w-md mx-auto print-container">
            <div class="bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden receipt-box">
                <!-- Header -->
                <div class="p-6 text-center border-b border-dashed border-slate-300">
                    <h1 class="text-xl font-black text-slate-800">APOTEK KINANTHI</h1>
                    <p class="text-xs text-slate-500 mt-1">Jl. Contoh Alamat No. 123, Kota</p>
                    <p class="text-xs text-slate-500">Telp: 0812-3456-7890</p>
                </div>

                <!-- Info -->
                <div class="p-6 border-b border-dashed border-slate-300 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-500">No Invoice</span>
                        <span class="font-bold text-slate-800">{{ sale.sale_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Tanggal</span>
                        <span class="font-semibold text-slate-800">{{ formatDateTime(sale.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Kasir</span>
                        <span class="font-semibold text-slate-800">{{ sale.kasir?.name }}</span>
                    </div>
                    <div v-if="sale.prescription" class="flex justify-between">
                        <span class="text-slate-500">Pasien</span>
                        <span class="font-semibold text-slate-800">{{ sale.prescription?.patient?.name }}</span>
                    </div>
                    <div v-if="sale.prescription" class="flex justify-between">
                        <span class="text-slate-500">No Resep</span>
                        <span class="font-semibold text-slate-800">{{ sale.prescription?.prescription_number }}</span>
                    </div>
                </div>

                <!-- Items -->
                <div class="p-6 border-b border-dashed border-slate-300">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b border-[#E8EEF6]">
                                <th class="pb-2 font-semibold w-full">Item</th>
                                <th class="pb-2 font-semibold text-right whitespace-nowrap pl-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in sale.sale_items" :key="item.id">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">{{ item.item_name }}</p>
                                    <p class="text-xs text-slate-500">{{ item.quantity }} {{ item.unit }} x {{ formatRupiah(item.unit_price) }}</p>
                                </td>
                                <td class="py-3 text-right font-bold text-slate-800 align-top">
                                    {{ formatRupiah(item.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total -->
                <div class="p-6 bg-slate-50 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="font-bold text-slate-700">Total Belanja</span>
                        <span class="font-black text-slate-800 text-base">{{ formatRupiah(sale.total_amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Tunai / Dibayar ({{ String(sale.payment_method).toUpperCase() }})</span>
                        <span class="font-semibold text-slate-800">{{ formatRupiah(sale.paid_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-[#E8EEF6]">
                        <span class="font-bold text-slate-700">Kembalian</span>
                        <span class="font-bold text-slate-800">{{ formatRupiah(sale.change_amount) }}</span>
                    </div>
                </div>

                <div class="p-6 text-center text-xs text-slate-500 bg-white">
                    <p>Terima kasih atas kunjungan Anda</p>
                    <p>Semoga lekas sembuh</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex flex-col sm:flex-row gap-3 no-print">
                <button @click="printReceipt" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-[16px] transition-colors shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    Cetak Struk
                </button>
                <Link :href="route('control.kasir.queue')" class="flex-1 bg-white hover:bg-slate-50 text-slate-700 font-bold py-3 rounded-[16px] transition-colors border border-[#E8EEF6] flex justify-center items-center">
                    Kembali ke Antrian
                </Link>
            </div>
        </div>
    </ControlLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .print-container, .print-container * {
        visibility: visible;
    }
    .print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .no-print {
        display: none !important;
    }
    .receipt-box {
        border: none !important;
        box-shadow: none !important;
    }
    /* Minimalist styling for receipt printers */
    @page { margin: 0; }
}
</style>
