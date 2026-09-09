<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    sale: Object,
});

const formatDateTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';
const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);
const printReceipt = () => window.print();
</script>

<template>
    <Head title="Struk Pembayaran" />

    <div class="max-w-md mx-auto print-container">
        <div class="mb-4 no-print">
            <Link :href="route('control.kasir.queue')" class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                <span>Kembali ke Antrean</span>
            </Link>
        </div>

        <div class="bg-card rounded-lg border border-border shadow-sm overflow-hidden receipt-box">
            <!-- Header -->
            <div class="p-6 text-center border-b border-dashed border-border">
                <h1 class="text-lg font-bold text-foreground">APOTEK KINANTHI</h1>
                <p class="text-xs text-muted-foreground mt-0.5">Jl. Contoh Alamat No. 123, Kota</p>
                <p class="text-xs text-muted-foreground">Telp: 0812-3456-7890</p>
            </div>

            <!-- Info -->
            <div class="p-6 border-b border-dashed border-border text-xs space-y-2">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">No Invoice</span>
                    <span class="font-semibold text-foreground">{{ sale.sale_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Tanggal</span>
                    <span class="font-medium text-foreground">{{ formatDateTime(sale.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Kasir</span>
                    <span class="font-medium text-foreground">{{ sale.kasir?.name }}</span>
                </div>
                <div v-if="sale.prescription" class="flex justify-between">
                    <span class="text-muted-foreground">Pasien</span>
                    <span class="font-medium text-foreground">{{ sale.prescription?.patient?.name }}</span>
                </div>
                <div v-if="sale.prescription" class="flex justify-between">
                    <span class="text-muted-foreground">No Resep</span>
                    <span class="font-medium text-foreground">{{ sale.prescription?.prescription_number }}</span>
                </div>
            </div>

            <!-- Items -->
            <div class="p-6 border-b border-dashed border-border">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-muted-foreground border-b border-border">
                            <th class="pb-2 font-semibold w-full">Item</th>
                            <th class="pb-2 font-semibold text-right whitespace-nowrap pl-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in sale.sale_items" :key="item.id">
                            <td class="py-2.5">
                                <p class="font-semibold text-foreground">{{ item.item_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ item.quantity }} {{ item.unit }} x {{ formatRupiah(item.unit_price) }}</p>
                            </td>
                            <td class="py-2.5 text-right font-semibold text-foreground align-top">
                                {{ formatRupiah(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Total -->
            <div class="p-6 bg-muted/40 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="font-medium text-foreground">Total Belanja</span>
                    <span class="font-bold text-foreground text-sm">{{ formatRupiah(sale.total_amount) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Tunai / Dibayar ({{ String(sale.payment_method).toUpperCase() }})</span>
                    <span class="font-medium text-foreground">{{ formatRupiah(sale.paid_amount) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-border">
                    <span class="font-medium text-foreground">Kembalian</span>
                    <span class="font-semibold text-foreground">{{ formatRupiah(sale.change_amount) }}</span>
                </div>
            </div>

            <div class="p-6 text-center text-xs text-muted-foreground bg-card">
                <p>Terima kasih atas kunjungan Anda</p>
                <p>Semoga lekas sembuh</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex flex-col sm:flex-row gap-3 no-print">
            <button @click="printReceipt" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium h-10 rounded-md transition-colors shadow-sm flex justify-center items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Cetak Struk
            </button>
            <Link :href="route('control.kasir.queue')" class="flex-1 bg-secondary hover:bg-secondary/80 text-secondary-foreground font-medium h-10 rounded-md transition-colors border border-border flex justify-center items-center text-sm">
                Kembali ke Antrian
            </Link>
        </div>
    </div>
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
