<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import axios from 'axios';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    patients: Array,
    medicines: Array,
    compounded_products: Array
});

const form = useForm({
    patient_id: '',
    patient_weight_kg: '',
    patient_age_years: '',
    doctor_name: '',
    doctor_sip: '',
    clinic_name: '',
    prescription_date: new Date().toISOString().split('T')[0],
    notes: '',
    screening_notes: '',
    items: [
        {
            item_type: 'obat_jadi',
            medicine_id: '',
            compounded_product_id: '',
            custom_formula_name: '',
            custom_formula_notes: '',
            quantity: 1,
            unit: '',
            dosage_instruction: '',
            usage_instructions: '',
            item_notes: '',
            is_iter: false,
            iter_count: 0,
            racikan_source: 'master' // local ui state: 'master' or 'baru'
        }
    ]
});

const hasControlledMedicine = computed(() => {
    return form.items.some(item => {
        if (item.item_type === 'obat_jadi' && item.medicine_id) {
            const med = props.medicines?.find(m => m.id === item.medicine_id);
            return med && (med.classification === 'narkotika' || med.classification === 'psikotropika');
        }
        return false;
    });
});

const localPatients = ref([...(props.patients || [])]);
const showPatientModal = ref(false);
const patientForm = useForm({
    name: '',
    phone: '',
    notes: ''
});

const handleMedicineChange = (index) => {
    const item = form.items[index];
    const med = props.medicines?.find(m => m.id === item.medicine_id);
    if (med) item.unit = med.satuan_stok || '';
};

const handleProductChange = (index) => {
    const item = form.items[index];
    const prod = props.compounded_products?.find(p => p.id === item.compounded_product_id);
    if (prod) item.unit = prod.satuan || '';
};

const handleItemTypeChange = (index) => {
    const item = form.items[index];
    item.medicine_id = '';
    item.compounded_product_id = '';
    item.custom_formula_name = '';
    item.unit = '';
};

const addItem = () => {
    form.items.push({
        item_type: 'obat_jadi',
        medicine_id: '',
        compounded_product_id: '',
        custom_formula_name: '',
        custom_formula_notes: '',
        quantity: 1,
        unit: '',
        dosage_instruction: '',
        usage_instructions: '',
        item_notes: '',
        is_iter: false,
        iter_count: 0,
        racikan_source: 'master'
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('control.apoteker.prescriptions.store'), {
        preserveScroll: true,
    });
};

const savePatient = async () => {
    try {
        const { data } = await axios.post(route('control.apoteker.patients.store'), {
            name: patientForm.name,
            phone: patientForm.phone,
            notes: patientForm.notes
        });
        localPatients.value.push(data);
        form.patient_id = data.id;
        showPatientModal.value = false;
        patientForm.reset();
    } catch (err) {
        alert('Gagal menyimpan pasien. Cek kembali form Anda.');
    }
};
</script>

<template>
    <Head title="Tambah Resep Baru" />
    <div class="space-y-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Resep Baru</h1>
                <p class="text-sm text-muted-foreground mt-1">Masukan data resep dari dokter sesuai Permenkes No. 73/2016</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                Batal
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Data Pasien & Dokter -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h2 class="text-foreground font-semibold text-sm">Informasi Administratif & Klinis Resep</h2>
                    <span class="text-xs text-muted-foreground">Permenkes RI No. 73/2016</span>
                </div>

                <!-- Alert Khusus Obat Terkontrol (Narkotika/Psikotropika) -->
                <div v-if="hasControlledMedicine" class="p-3 bg-red-500/10 border border-red-500/30 rounded-md flex items-start gap-2.5 text-xs text-red-700 dark:text-red-400">
                    <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <strong class="font-bold uppercase tracking-wider block">Peringatan Regulasi Narkotika & Psikotropika</strong>
                        Resep memuat obat golongan khusus. Nomor SIP Dokter wajib dicantumkan dan fisik resep asli wajib diverifikasi sebelum pemrosesan.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Pasien -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Pasien <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select v-model="form.patient_id" class="flex-1 bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                <option value="">Pilih Pasien...</option>
                                <option v-for="p in localPatients" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <button type="button" @click="showPatientModal = true" class="bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border px-3 h-9 rounded-md text-xs font-medium whitespace-nowrap transition-colors">
                                Pasien Baru +
                            </button>
                        </div>
                    </div>

                    <!-- Umur Pasien -->
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Umur Pasien (Thn)</label>
                        <input v-model.number="form.patient_age_years" type="number" min="0" max="150" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 8" />
                    </div>

                    <!-- Berat Badan Pasien -->
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Berat Badan (kg)</label>
                        <input v-model.number="form.patient_weight_kg" type="number" step="0.1" min="0" max="300" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 22.5" />
                    </div>

                    <!-- Nama Dokter -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Dokter <span class="text-red-500">*</span></label>
                        <input v-model="form.doctor_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="dr. ..." required />
                    </div>

                    <!-- SIP Dokter -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                            SIP Dokter <span class="text-muted-foreground font-normal ml-0.5">(Opsional)</span>
                        </label>
                        <input 
                            v-model="form.doctor_sip" 
                            type="text" 
                            class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors"
                            placeholder="Contoh: 446/123/SIP.D/2026" 
                        />
                        <p v-if="form.errors.doctor_sip" class="text-red-600 text-xs mt-1">{{ form.errors.doctor_sip }}</p>
                    </div>

                    <!-- Tanggal Resep -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tanggal Resep <span class="text-red-500">*</span></label>
                        <input v-model="form.prescription_date" type="date" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                    </div>

                    <!-- Klinik / RS Asal -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Klinik/RS Asal</label>
                        <input v-model="form.clinic_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Puskesmas / RSUD..." />
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Umum</label>
                        <input v-model="form.notes" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Catatan tambahan pasien..." />
                    </div>

                    <!-- Skrining Klinis & Farmasetis -->
                    <div class="md:col-span-12">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Telaah / Skrining Klinis Apoteker</label>
                        <textarea v-model="form.screening_notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors resize-none" placeholder="Hasil telaah persyaratan farmasetis dan klinis (ketepatan indikasi, dosis, duplikasi, potensi interaksi obat)..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4 border-b border-border pb-3">
                    <h2 class="text-foreground font-semibold text-sm">Daftar Obat / Racikan</h2>
                    <button type="button" @click="addItem" class="bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border h-8 px-3 rounded-md text-xs font-medium transition-colors">
                        Tambah Item +
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="(item, idx) in form.items" :key="idx" class="bg-muted/30 border border-border p-4 rounded-md relative">
                        <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)" class="absolute top-3 right-3 text-muted-foreground hover:text-red-500 hover:bg-red-500/10 w-7 h-7 rounded-md flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                            <!-- Baris 1: Tipe, Nama, Qty, Iter -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tipe Item <span class="text-red-500">*</span></label>
                                <select v-model="item.item_type" @change="handleItemTypeChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors">
                                    <option value="obat_jadi">Obat Jadi</option>
                                    <option value="racikan">Racikan</option>
                                </select>
                            </div>

                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Item <span class="text-red-500">*</span></label>
                                
                                <template v-if="item.item_type === 'obat_jadi'">
                                    <select v-model="item.medicine_id" @change="handleMedicineChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                        <option value="">Pilih Obat...</option>
                                        <option v-for="m in medicines" :key="m.id" :value="m.id">{{ m.nama_obat }} ({{ m.satuan_stok }})</option>
                                    </select>
                                </template>

                                <template v-if="item.item_type === 'racikan'">
                                    <div class="flex gap-4 mb-1.5">
                                        <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="master" class="text-emerald-600 focus:ring-emerald-500" @change="item.custom_formula_name='';item.unit=''" />
                                            Master
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="baru" class="text-emerald-600 focus:ring-emerald-500" @change="item.compounded_product_id='';item.unit=''" />
                                            Baru
                                        </label>
                                    </div>

                                    <div v-if="item.racikan_source === 'master'">
                                        <select v-model="item.compounded_product_id" @change="handleProductChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                                            <option value="">Pilih Racikan Master...</option>
                                            <option v-for="p in compounded_products" :key="p.id" :value="p.id">{{ p.nama_produk }}</option>
                                        </select>
                                    </div>
                                    <div v-else>
                                        <input v-model="item.custom_formula_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Nama racikan kustom..." required />
                                    </div>
                                </template>
                            </div>

                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm font-semibold focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                                    <input v-model="item.unit" type="text" class="w-20 bg-muted border border-input rounded-md px-2 h-9 text-foreground text-xs font-medium focus:ring-1 focus:ring-ring outline-none text-center" :readonly="item.item_type === 'obat_jadi' || item.racikan_source === 'master'" placeholder="Satuan" required />
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Iter (Pengulangan)</label>
                                <div class="flex items-center gap-2 h-9">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer shrink-0">
                                        <input type="checkbox" v-model="item.is_iter" class="text-emerald-600 rounded focus:ring-emerald-500" />
                                        Ya
                                    </label>
                                    <input v-if="item.is_iter" v-model.number="item.iter_count" type="number" min="1" class="w-full bg-background border border-input rounded-md h-8 px-2 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Berapa x" required />
                                </div>
                            </div>

                            <!-- Baris 2: Dosis, Instruksi Pakai, Keterangan Tambahan -->
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Dosis <span class="text-red-500">*</span></label>
                                <input v-model="item.dosage_instruction" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Cth: 500mg, 1 Tab, 1 Cth" required />
                            </div>

                            <div class="md:col-span-4">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Aturan Pakai (Sig) <span class="text-red-500">*</span></label>
                                <input v-model="item.usage_instructions" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Cth: 3x1 sesudah makan" required />
                            </div>

                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Keterangan Spesifik</label>
                                <input v-model="item.item_notes" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Cth: Habiskan, Bila Demam, Telinga Kiri" />
                            </div>

                            <!-- Baris 3: Komposisi Racikan Kustom (Hanya jika racikan baru) -->
                            <div v-if="item.item_type === 'racikan' && item.racikan_source === 'baru'" class="md:col-span-12 mt-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Isi / Komposisi Resep Dokter <span class="text-red-500">*</span></label>
                                <textarea v-model="item.custom_formula_notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors resize-none" placeholder="Salin persis dari resep dokter. Cth: R/ Paracetamol 250mg, CTM 2mg, m.f. pulv dtd no X..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white h-10 rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50 uppercase tracking-wider">
                Simpan Resep
            </button>
        </form>
    </div>

    <!-- Patient Modal -->
    <Teleport to="body">
        <div v-if="showPatientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showPatientModal = false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md p-6 border border-border">
                <h3 class="text-foreground font-semibold text-base mb-4">Tambah Pasien Baru</h3>
                <form @submit.prevent="savePatient" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Pasien *</label>
                        <input v-model="patientForm.name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">No. Telepon</label>
                        <input v-model="patientForm.phone" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan / Alergi</label>
                        <textarea v-model="patientForm.notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors resize-none"></textarea>
                    </div>
                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" @click="showPatientModal = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">Batal</button>
                        <button type="submit" :disabled="patientForm.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors">Simpan Pasien</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
