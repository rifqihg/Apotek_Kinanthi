<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ prescription: Object });

const statusColor = (s) => ({
    baru: 'bg-slate-100 text-slate-700 border-[#E8EEF6]',
    diproses: 'bg-blue-50 text-blue-700 border-blue-200',
    siap_ambil: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    selesai: 'bg-teal-50 text-teal-700 border-teal-200',
    batal: 'bg-rose-50 text-rose-700 border-rose-200',
}[s] || 'bg-slate-50 text-slate-600 border-[#E8EEF6]');

const itemStatusColor = (s) => ({
    pending: 'bg-slate-100 text-slate-600',
    dikerjakan: 'bg-amber-100 text-amber-700',
    selesai: 'bg-emerald-100 text-emerald-700',
}[s] || 'bg-slate-100 text-slate-600');

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : '-';

const allItemsDone = computed(() => props.prescription?.prescription_items?.every(item => item.status === 'selesai') ?? false);

const updateStatus = (newStatus) => {
    if (newStatus === 'siap_ambil' && !allItemsDone.value) return;
    
    if (confirm(`Ubah status resep menjadi ${newStatus.replace(/_/g, ' ')}?`)) {
        router.patch(route('control.apoteker.prescriptions.updateStatus', props.prescription.id), { status: newStatus });
    }
};

const startCompounding = (itemId) => {
    if (confirm('Mulai kerjakan racikan untuk item ini?')) {
        router.post(route('control.apoteker.prescriptions.items.start-compounding', itemId));
    }
};

const completeItem = (itemId) => {
    router.patch(route('control.apoteker.prescriptions.items.complete', itemId));
};
</script>

<template>
    <Head :title="`Detail Resep - ${prescription.prescription_number}`" />
    <div class="space-y-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Detail Resep</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">{{ prescription.prescription_number }}</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-slate-500 hover:text-slate-700 text-sm font-bold transition-colors">
                &larr; Kembali ke Daftar
            </Link>
        </div>

        <!-- Section 1: Header Info -->
        <div class="bg-white border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">No. Resep</p>
                <p class="font-mono font-bold text-slate-800 text-sm">{{ prescription.prescription_number }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Pasien</p>
                <p class="font-bold text-slate-800 text-sm">{{ prescription.patient?.name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Dokter</p>
                <p class="font-bold text-slate-800 text-sm">{{ prescription.doctor_name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Klinik/RS</p>
                <p class="font-bold text-slate-800 text-sm">{{ prescription.clinic_name || '-' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Tgl Resep</p>
                <p class="font-bold text-slate-800 text-sm">{{ formatDate(prescription.prescription_date) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Ditangani Oleh</p>
                <p class="font-bold text-slate-800 text-sm">{{ prescription.handler?.name || '-' }}</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Status Resep</p>
                <span :class="statusColor(prescription.status)" class="text-[10px] px-3 py-1.5 rounded-[16px] font-black uppercase tracking-widest border block w-fit">
                    {{ prescription.status ? prescription.status.replace(/_/g, ' ') : 'unknown' }}
                </span>
            </div>
        </div>

        <!-- Section 2: Items -->
        <div class="bg-white border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-x-auto">
            <h3 class="text-slate-800 font-bold uppercase tracking-wide text-sm mb-4">Daftar Item Obat</h3>
            <table class="w-full text-sm text-left border-collapse min-w-[700px]">
                <thead class="bg-slate-50/50 border-y border-[#E8EEF6] uppercase">
                    <tr>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em]">No</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em]">Jenis</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em]">Nama Obat/Racikan</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em] text-right">Qty</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em]">Satuan</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em]">Instruksi</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em] text-center">Status Item</th>
                        <th class="px-4 py-3 text-[10px] font-black text-slate-400 tracking-[0.1em] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(item, idx) in prescription.prescription_items" :key="item.id" class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 font-mono font-bold text-slate-500">{{ idx + 1 }}</td>
                        <td class="px-4 py-3">
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold uppercase tracking-wider">{{ item.item_type.replace('_', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3 font-bold text-slate-800">
                            <template v-if="item.medicine_id">{{ item.medicine?.nama_obat }}</template>
                            <template v-else-if="item.compounded_product_id">{{ item.compounded_product?.nama_produk }}</template>
                            <template v-else-if="item.custom_formula_name">
                                {{ item.custom_formula_name }} <span class="text-xs font-normal text-slate-400 ml-1">(Formula Baru)</span>
                            </template>
                        </td>
                        <td class="px-4 py-3 font-mono font-black text-slate-700 text-right">{{ Number(item.quantity) }}</td>
                        <td class="px-4 py-3 text-slate-500 font-bold text-xs">{{ item.unit }}</td>
                        <td class="px-4 py-3 text-slate-600 text-xs italic">{{ item.usage_instructions || '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="itemStatusColor(item.status)" class="text-[9px] px-2.5 py-1 rounded-[16px] font-black uppercase tracking-widest inline-block">
                                {{ item.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <!-- Racikan dengan submission -->
                            <template v-if="item.item_type === 'racikan' && (item.compounding_id || item.compounding_submission?.id)">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="route('control.apoteker.submissions.show', item.compounding_id || item.compounding_submission.id)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-widest transition-all">
                                        Lihat Racikan
                                    </Link>
                                    <span v-if="item.status === 'selesai'" class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-1 rounded-[12px] text-[9px] font-black uppercase tracking-widest">
                                        Siap
                                    </span>
                                </div>
                            </template>
                            <!-- Racikan pending/belum diracik -->
                            <template v-else-if="item.item_type === 'racikan' && item.status !== 'selesai' && prescription.status === 'diproses'">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button v-if="item.compounded_product_id" @click="completeItem(item.id)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-widest transition-all shadow-sm" title="Tandai siap dari stok master yang sudah ada">
                                        Siap (Master)
                                    </button>
                                    <button @click="startCompounding(item.id)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-widest transition-all shadow-sm">
                                        Kerjakan Racikan
                                    </button>
                                </div>
                            </template>
                            <!-- Obat Jadi belum siap -->
                            <template v-else-if="item.item_type === 'obat_jadi' && item.status !== 'selesai' && prescription.status === 'diproses'">
                                <button @click="completeItem(item.id)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-widest transition-all shadow-sm inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    Tandai Siap
                                </button>
                            </template>
                            <!-- Item Selesai -->
                            <template v-else-if="item.status === 'selesai'">
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-[12px] text-[9px] font-black uppercase tracking-widest inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    Siap
                                </span>
                            </template>
                            <template v-else>
                                <span class="text-slate-300 font-bold">—</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 3: Status Actions -->
        <div class="bg-white border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <h3 class="text-slate-800 font-bold uppercase tracking-wide text-sm mb-4">Aksi Proses Resep</h3>
            
            <div v-if="prescription.status === 'baru'">
                <button @click="updateStatus('diproses')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-[24px] text-xs font-black uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all active:scale-95">
                    Mulai Proses Resep
                </button>
            </div>
            
            <div v-else-if="prescription.status === 'diproses'">
                <div v-if="allItemsDone">
                    <button @click="updateStatus('siap_ambil')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-[24px] text-xs font-black uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all active:scale-95">
                        Tandai Siap Diambil
                    </button>
                </div>
                <div v-else>
                    <button disabled class="bg-slate-200 text-slate-400 px-6 py-3 rounded-[24px] text-xs font-black uppercase tracking-widest cursor-not-allowed" title="Selesaikan semua item terlebih dahulu">
                        Tandai Siap Diambil
                    </button>
                    <p class="text-amber-600 text-xs mt-2 font-bold flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Semua item harus diselesaikan terlebih dahulu.
                    </p>
                </div>
            </div>

            <div v-else-if="prescription.status === 'siap_ambil'" class="bg-emerald-50 border border-emerald-200 p-4 rounded-[24px]">
                <p class="text-emerald-700 font-bold text-sm">Resep sudah siap diambil oleh pasien. Menunggu proses pembayaran di kasir.</p>
            </div>

            <div v-else class="flex items-center gap-3">
                <p class="text-slate-500 font-bold text-sm">Status akhir resep:</p>
                <span :class="statusColor(prescription.status)" class="text-[10px] px-3 py-1.5 rounded-[16px] font-black uppercase tracking-widest border inline-block">
                    {{ prescription.status ? prescription.status.replace(/_/g, ' ') : 'unknown' }}
                </span>
            </div>
        </div>
    </div>
</template>
