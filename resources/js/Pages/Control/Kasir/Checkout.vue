<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { computed, watch } from 'vue';

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

const subtotalAmount = computed(() => {
    return form.items.reduce((sum, item) => sum + ((Number(item.unit_price) || 0) * item.quantity), 0);
});

const discountAmount = computed(() => {
    const val = Number(form.discount_amount) || 0;
    return val > subtotalAmount.value ? subtotalAmount.value : val;
});

const finalTotalAmount = computed(() => {
    const total = subtotalAmount.value - discountAmount.value;
    return total > 0 ? total : 0;
});

const changeAmount = computed(() => {
    const paid = Number(form.paid_amount) || 0;
    const diff = paid - finalTotalAmount.value;
    return diff > 0 ? diff : 0;
});

const isPaidValid = computed(() => {
    return (Number(form.paid_amount) || 0) >= finalTotalAmount.value && finalTotalAmount.value >= 0;
});

const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
};

const submit = () => {
    if (!isPaidValid.value) return;
    form.post(route('control.kasir.sales.store'));
};
</script>

<template>
    <Head title="Checkout Pembayaran" />

    <ControlLayout>
        <template #header-title>
            <div class="flex items-center gap-3">
                <Link :href="route('control.kasir.queue')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <span>Checkout Resep</span>
            </div>
        </template>

        <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 relative items-start">
            <!-- Left: Resep & Items -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-6">
                    <div class="flex items-center justify-between border-b border-[#E8EEF6] pb-4 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Detail Resep</h2>
                            <p class="text-sm text-slate-500 font-mono mt-1">{{ prescription.prescription_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-800">{{ prescription.patient?.name }}</p>
                            <p class="text-xs text-slate-500 mt-1">Dokter: {{ prescription.doctor_name }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                                    <th class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider">Nama Item</th>
                                    <th class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider text-center">Jenis</th>
                                    <th class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider text-center">Qty</th>
                                    <th class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Harga Satuan</th>
                                    <th class="px-4 py-3 text-xs font-black text-slate-500 uppercase tracking-wider text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, idx) in form.items" :key="item.prescription_item_id">
                                    <td class="px-4 py-3">
                                        <span class="font-bold text-slate-800">{{ item.name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="uppercase font-bold text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded-[16px] tracking-wider">
                                            {{ item.item_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold text-slate-700">{{ item.quantity }} <span class="text-xs text-slate-400 font-medium">{{ item.unit }}</span></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div v-if="item.item_type === 'racikan'">
                                            <span class="font-bold text-slate-700">{{ formatRupiah(item.unit_price) }}</span>
                                        </div>
                                        <div v-else>
                                            <input type="number" v-model="form.items[idx].unit_price" class="w-32 text-right text-sm border-[#E8EEF6] rounded-[16px] focus:ring-emerald-500 focus:border-emerald-500 font-bold" placeholder="Input harga" required min="0" step="0.01">
                                            <p v-if="form.items[idx].has_default_price" class="text-[10px] text-slate-400 mt-1 italic">Harga default</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-600">
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
                <form @submit.prevent="submit" class="bg-white rounded-[24px] border border-[#E8EEF6]/60 shadow-[0_8px_24px_rgba(15,23,42,0.04)] p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-slate-800 border-b border-[#E8EEF6] pb-4 mb-6">Pembayaran</h2>

                    <div class="space-y-6">
                        <!-- Subtotal -->
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-wider">Subtotal</span>
                            <span class="text-lg font-black text-slate-800">{{ formatRupiah(subtotalAmount) }}</span>
                        </div>

                        <!-- Diskon -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Diskon (Rp)</label>
                            <input type="number" v-model="form.discount_amount" class="w-full text-right text-lg border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold text-rose-600" placeholder="0" min="0" step="0.01">
                        </div>

                        <!-- Total Tagihan -->
                        <div class="border-t border-[#E8EEF6] pt-4">
                            <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">Total Tagihan</p>
                            <div class="bg-emerald-50 border border-emerald-100 rounded-[16px] p-4 text-right">
                                <span class="text-3xl font-black text-emerald-700">{{ formatRupiah(finalTotalAmount) }}</span>
                            </div>
                        </div>

                        <!-- Metode Bayar -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Metode Pembayaran</label>
                            <select v-model="form.payment_method" class="w-full border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold text-slate-700">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="qris">QRIS</option>
                            </select>
                        </div>

                        <!-- Uang Diterima -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Uang Diterima (Rp)</label>
                            <input type="number" v-model="form.paid_amount" class="w-full text-right text-lg border-[#E8EEF6] rounded-[16px] focus:ring-indigo-500 focus:border-indigo-500 font-bold text-slate-800" placeholder="0" required min="0" step="0.01">
                        </div>

                        <!-- Kembalian -->
                        <div class="flex justify-between items-end border-t border-[#E8EEF6] pt-4">
                            <span class="text-sm font-bold text-slate-500">Kembalian</span>
                            <span class="text-xl font-black text-slate-800">{{ formatRupiah(changeAmount) }}</span>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" :disabled="form.processing || !isPaidValid" class="w-full py-3.5 px-4 rounded-[16px] text-white font-bold text-base transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] shadow-indigo-600/20" :class="isPaidValid ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-slate-300 cursor-not-allowed text-slate-500 shadow-none'">
                            Konfirmasi Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ControlLayout>
</template>
