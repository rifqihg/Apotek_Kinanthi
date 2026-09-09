<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { computed } from 'vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    prescription: Object,
});

const form = useForm({
    prescription_id: props.prescription.id,
    payment_method: 'tunai',
    discount_amount: '',
    paid_amount: '',
    items: props.prescription.prescription_items.map(item => ({
        prescription_item_id: item.id,
        item_type: item.item_type,
        name: item.item_type === 'racikan' 
            ? (item.compounded_product?.nama_produk || item.custom_formula_name) 
            : (item.medicine?.nama_obat || 'Obat Jadi'),
        quantity: Number(item.quantity),
        unit: item.unit,
        unit_price: item.item_type === 'racikan' 
            ? Number(item.compounded_product?.harga_jual || 0) 
            : (item.medicine?.selling_price ? Number(item.medicine.selling_price) : null),
        has_default_price: item.item_type !== 'racikan' && !!item.medicine?.selling_price,
    }))
});

const subtotalAmount = computed(() => form.items.reduce((sum, item) => sum + ((Number(item.unit_price) || 0) * item.quantity), 0));

const discountAmount = computed(() => {
    const val = Number(form.discount_amount) || 0;
    return val > subtotalAmount.value ? subtotalAmount.value : val;
});

const finalTotalAmount = computed(() => Math.max(0, subtotalAmount.value - discountAmount.value));

const changeAmount = computed(() => Math.max(0, (Number(form.paid_amount) || 0) - finalTotalAmount.value));

const isPaidValid = computed(() => (Number(form.paid_amount) || 0) >= finalTotalAmount.value && finalTotalAmount.value >= 0);

const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const submit = () => {
    if (!isPaidValid.value) return;
    form.post(route('control.kasir.sales.store'));
};
</script>

<template>
    <Head title="Checkout Pembayaran" />

    <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 relative items-start">
        <!-- Left: Resep & Items -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-card rounded-lg border border-border shadow-sm p-6">
                <div class="flex items-center justify-between border-b border-border pb-4 mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Detail Resep</h2>
                        <p class="text-xs text-muted-foreground tracking-wide mt-0.5">{{ prescription.prescription_number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-foreground">{{ prescription.patient?.name }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Dokter: {{ prescription.doctor_name }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-muted/50 border-b border-border">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Item</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jenis</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Qty</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Harga Satuan</th>
                                <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in form.items" :key="item.prescription_item_id" class="hover:bg-muted/50 transition-colors">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-foreground">{{ item.name }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="uppercase font-medium text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-md">
                                        {{ item.item_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-medium text-foreground">{{ item.quantity }} <span class="text-xs text-muted-foreground">{{ item.unit }}</span></span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div v-if="item.item_type === 'racikan'">
                                        <span class="font-semibold text-foreground">{{ formatRupiah(item.unit_price) }}</span>
                                    </div>
                                    <div v-else>
                                        <input type="number" v-model="form.items[idx].unit_price" class="h-8 w-32 text-right text-xs bg-background border border-input rounded-md px-2 font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-ring" placeholder="Input harga" required min="0" step="0.01">
                                        <p v-if="form.items[idx].has_default_price" class="text-xs text-muted-foreground mt-0.5 italic">Harga default</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatRupiah((Number(item.unit_price) || 0) * item.quantity) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Payment Panel -->
        <div class="lg:col-span-1">
            <form @submit.prevent="submit" class="bg-card rounded-lg border border-border shadow-sm p-6 sticky top-24">
                <h2 class="text-base font-semibold text-foreground border-b border-border pb-4 mb-6">Pembayaran</h2>

                <div class="space-y-4">
                    <!-- Subtotal -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Subtotal</span>
                        <span class="text-base font-bold text-foreground">{{ formatRupiah(subtotalAmount) }}</span>
                    </div>

                    <!-- Diskon -->
                    <div>
                        <label class="block text-xs font-medium text-muted-foreground mb-1.5">Diskon (Rp)</label>
                        <input type="number" v-model="form.discount_amount" class="h-9 w-full text-right text-sm bg-background border border-input rounded-md px-3 font-semibold text-destructive focus:outline-none focus:ring-1 focus:ring-ring" placeholder="0" min="0" step="0.01">
                    </div>

                    <!-- Total Tagihan -->
                    <div class="border-t border-border pt-3">
                        <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 text-right">
                            <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-1">Total Tagihan</p>
                            <span class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ formatRupiah(finalTotalAmount) }}</span>
                        </div>
                    </div>

                    <!-- Metode Bayar -->
                    <div>
                        <label class="block text-xs font-medium text-muted-foreground mb-1.5">Metode Pembayaran</label>
                        <select v-model="form.payment_method" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-ring">
                            <option value="tunai">Tunai</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="qris">QRIS</option>
                        </select>
                    </div>

                    <!-- Uang Diterima -->
                    <div>
                        <label class="block text-xs font-medium text-muted-foreground mb-1.5">Uang Diterima (Rp)</label>
                        <input type="number" v-model="form.paid_amount" class="h-10 w-full text-right text-base bg-background border border-input rounded-md px-3 font-bold text-foreground focus:outline-none focus:ring-1 focus:ring-ring" placeholder="0" required min="0" step="0.01">
                    </div>

                    <!-- Kembalian -->
                    <div class="flex justify-between items-end border-t border-border pt-3">
                        <span class="text-xs font-medium text-muted-foreground">Kembalian</span>
                        <span class="text-lg font-bold text-foreground">{{ formatRupiah(changeAmount) }}</span>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" :disabled="form.processing || !isPaidValid" class="w-full h-10 px-4 rounded-md text-white font-medium text-sm transition-colors shadow-sm flex items-center justify-center gap-1.5" :class="isPaidValid ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-muted text-muted-foreground cursor-not-allowed shadow-none'">
                        Konfirmasi Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
