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

const hasControlledMedicine = computed(() => {
    return props.prescription?.prescription_items?.some(item => 
        ['narkotika', 'psikotropika'].includes(item.medicine?.classification)
    ) ?? false;
});

const updateStatus = (newStatus) => {
    if (newStatus === 'siap_ambil' && !allItemsDone.value) return;
    
    if (confirm(`Ubah status resep menjadi ${newStatus.replace(/_/g, ' ')}?`)) {
        router.patch(route('control.apoteker.prescriptions.updateStatus', props.prescription.id), { status: newStatus }, { preserveScroll: true });
    }
};

const startCompounding = (itemId) => {
    if (confirm('Mulai kerjakan racikan untuk item ini?')) {
        router.post(route('control.apoteker.prescriptions.items.start-compounding', itemId), {}, { preserveScroll: true });
    }
};

const completeItem = (itemId) => {
    router.patch(route('control.apoteker.prescriptions.items.complete', itemId), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Detail Resep - ${prescription.prescription_number}`" />
    <div class="space-y-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Detail Resep</h1>
                <p class="text-sm text-muted-foreground tracking-wide mt-1">{{ prescription.prescription_number }}</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                &larr; Kembali ke Daftar
            </Link>
        </div>

        <!-- Alert Narkotika / Psikotropika -->
        <div v-if="hasControlledMedicine" class="bg-rose-50 border border-rose-300 dark:bg-rose-950/40 dark:border-rose-800 rounded-lg p-4 flex items-start gap-3 text-rose-800 dark:text-rose-200 shadow-sm">
            <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <div>
                <p class="font-bold text-sm tracking-wide">PERINGATAN REGULASI: RESEP MENGANDUNG NARKOTIKA / PSIKOTROPIKA</p>
                <p class="text-xs mt-1 text-rose-700 dark:text-rose-300">Resep ini memuat obat golongan terkontrol. Wajib verifikasi fisik resep asli, keabsahan SIP dokter ({{ prescription.doctor_sip || 'BELUM TERDAFTAR' }}), identitas penerima, dan pencatatan mutasi SIPNAP.</p>
            </div>
        </div>

        <!-- Section 1: Header Info -->
        <div class="bg-card border border-border rounded-lg p-6 shadow-sm grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">No. Resep</p>
                <p class="font-medium text-foreground text-sm tracking-wide">{{ prescription.prescription_number }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Pasien</p>
                <p class="font-medium text-foreground text-sm">{{ prescription.patient?.name }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ prescription.patient_age_years ? prescription.patient_age_years + ' thn' : '-' }} / {{ prescription.patient_weight_kg ? prescription.patient_weight_kg + ' kg' : '-' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Dokter & SIP</p>
                <p class="font-medium text-foreground text-sm">{{ prescription.doctor_name }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">SIP: {{ prescription.doctor_sip || '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Klinik/RS</p>
                <p class="font-medium text-foreground text-sm">{{ prescription.clinic_name || '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Tgl Resep</p>
                <p class="font-medium text-foreground text-sm">{{ formatDate(prescription.prescription_date) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Ditangani Oleh</p>
                <p class="font-medium text-foreground text-sm">{{ prescription.handler?.name || '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Status Resep</p>
                <span :class="statusColor(prescription.status)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block">
                    {{ prescription.status ? prescription.status.replace(/_/g, ' ') : 'unknown' }}
                </span>
            </div>
            <div class="col-span-2 md:col-span-4 bg-muted/20 border border-border/50 rounded p-3 mt-1" v-if="prescription.screening_notes">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Catatan Skrining Resep (Farmasis)</p>
                <p class="text-xs text-foreground">{{ prescription.screening_notes }}</p>
            </div>
        </div>

        <!-- Section 2: Items -->
        <div class="bg-card border border-border rounded-lg p-6 shadow-sm overflow-x-auto">
            <h2 class="text-foreground font-semibold text-sm mb-4">Daftar Item Obat</h2>
            <table class="w-full text-sm text-left border-collapse min-w-[700px]">
                <thead class="bg-muted/50 border-y border-border">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">No</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Jenis</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Obat/Racikan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Qty</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Satuan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Instruksi</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status Item</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="(item, idx) in prescription.prescription_items" :key="item.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3 font-medium text-muted-foreground">{{ idx + 1 }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded-md font-medium uppercase tracking-wider border border-border">{{ item.item_type.replace('_', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-foreground">
                            <template v-if="item.medicine_id">
                                <span>{{ item.medicine?.nama_obat }}</span>
                                <span v-if="item.medicine?.classification" class="ml-2 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase" :class="{
                                    'bg-red-100 text-red-800 border border-red-300': item.medicine.classification === 'narkotika',
                                    'bg-purple-100 text-purple-800 border border-purple-300': item.medicine.classification === 'psikotropika',
                                    'bg-blue-100 text-blue-800 border border-blue-300': item.medicine.classification === 'prekursor',
                                    'bg-rose-50 text-rose-700 border border-rose-200': item.medicine.classification === 'obat_keras',
                                    'bg-amber-50 text-amber-700 border border-amber-200': item.medicine.classification === 'bebas_terbatas',
                                    'bg-emerald-50 text-emerald-700 border border-emerald-200': item.medicine.classification === 'bebas'
                                }">
                                    {{ item.medicine.classification }}
                                </span>
                            </template>
                            <template v-else-if="item.compounded_product_id">{{ item.compounded_product?.nama_produk }}</template>
                            <template v-else-if="item.custom_formula_name">
                                {{ item.custom_formula_name }} <span class="text-xs font-normal text-muted-foreground ml-1">(Formula Baru)</span>
                            </template>
                        </td>
                        <td class="px-4 py-3 font-semibold text-foreground text-right">{{ Number(item.quantity) }}</td>
                        <td class="px-4 py-3 text-muted-foreground text-xs font-medium">{{ item.unit }}</td>
                        <td class="px-4 py-3 text-muted-foreground text-xs italic">{{ item.usage_instructions || '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="itemStatusColor(item.status)" class="text-xs px-2 py-0.5 rounded-md font-semibold uppercase tracking-wider inline-block">
                                {{ item.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <!-- Racikan dengan submission -->
                            <template v-if="item.item_type === 'racikan' && (item.compounding_id || item.compounding_submission?.id)">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="route('control.apoteker.submissions.show', item.compounding_id || item.compounding_submission.id)" class="bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border px-2.5 py-1 rounded-md text-xs font-medium transition-colors">
                                        Lihat Racikan
                                    </Link>
                                    <span v-if="item.status === 'selesai'" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">
                                        Siap
                                    </span>
                                </div>
                            </template>
                            <!-- Racikan pending/belum diracik -->
                            <template v-else-if="item.item_type === 'racikan' && item.status !== 'selesai' && prescription.status === 'diproses'">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button v-if="item.compounded_product_id" @click="completeItem(item.id)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded-md text-xs font-medium transition-colors shadow-sm" title="Tandai siap dari stok master yang sudah ada">
                                        Siap (Master)
                                    </button>
                                    <button @click="startCompounding(item.id)" class="bg-primary hover:bg-primary/90 text-primary-foreground px-2.5 py-1 rounded-md text-xs font-medium transition-colors shadow-sm">
                                        Kerjakan Racikan
                                    </button>
                                </div>
                            </template>
                            <!-- Obat Jadi belum siap -->
                            <template v-else-if="item.item_type === 'obat_jadi' && item.status !== 'selesai' && prescription.status === 'diproses'">
                                <button @click="completeItem(item.id)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded-md text-xs font-medium transition-colors shadow-sm inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Tandai Siap
                                </button>
                            </template>
                            <!-- Item Selesai -->
                            <template v-else-if="item.status === 'selesai'">
                                <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Siap
                                </span>
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground font-medium">—</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 3: Status Actions -->
        <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
            <h2 class="text-foreground font-semibold text-sm mb-4">Aksi Proses Resep</h2>
            
            <div v-if="prescription.status === 'baru'">
                <button @click="updateStatus('diproses')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 h-9 rounded-md text-sm font-medium shadow-sm transition-colors uppercase tracking-wider">
                    Mulai Proses Resep
                </button>
            </div>
            
            <div v-else-if="prescription.status === 'diproses'">
                <div v-if="allItemsDone">
                    <button @click="updateStatus('siap_ambil')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 h-9 rounded-md text-sm font-medium shadow-sm transition-colors uppercase tracking-wider">
                        Tandai Siap Diambil
                    </button>
                </div>
                <div v-else>
                    <button disabled class="bg-muted text-muted-foreground px-4 h-9 rounded-md text-sm font-medium cursor-not-allowed uppercase tracking-wider" title="Selesaikan semua item terlebih dahulu">
                        Tandai Siap Diambil
                    </button>
                    <p class="text-amber-600 text-xs mt-2 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Semua item harus diselesaikan terlebih dahulu.
                    </p>
                </div>
            </div>

            <div v-else-if="prescription.status === 'siap_ambil'" class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-md">
                <p class="text-emerald-600 dark:text-emerald-400 font-medium text-sm">Resep sudah siap diambil oleh pasien. Menunggu proses pembayaran di kasir.</p>
            </div>

            <div v-else class="flex items-center gap-3">
                <p class="text-muted-foreground font-medium text-sm">Status akhir resep:</p>
                <span :class="statusColor(prescription.status)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block">
                    {{ prescription.status ? prescription.status.replace(/_/g, ' ') : 'unknown' }}
                </span>
            </div>
        </div>
    </div>
</template>
