<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    doctor_name: '',
    clinic_name: '',
    prescription_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        {
            item_type: 'obat_jadi',
            medicine_id: '',
            compounded_product_id: '',
            custom_formula_name: '',
            quantity: 1,
            unit: '',
            usage_instructions: '',
            racikan_source: 'master' // local ui state: 'master' or 'baru'
        }
    ]
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
        quantity: 1,
        unit: '',
        usage_instructions: '',
        racikan_source: 'master'
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('control.apoteker.prescriptions.store'));
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
                <p class="text-sm text-muted-foreground mt-1">Masukan data resep dari dokter</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                Batal
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Data Pasien & Dokter -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm space-y-4">
                <h2 class="text-foreground font-semibold text-sm border-b border-border pb-3">Informasi Utama</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
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
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tanggal Resep <span class="text-red-500">*</span></label>
                        <input v-model="form.prescription_date" type="date" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Dokter <span class="text-red-500">*</span></label>
                        <input v-model="form.doctor_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="dr. ..." required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Klinik/RS Asal</label>
                        <input v-model="form.clinic_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Opsional" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Tambahan</label>
                        <textarea v-model="form.notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors resize-none"></textarea>
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
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tipe Item</label>
                                <select v-model="item.item_type" @change="handleItemTypeChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors">
                                    <option value="obat_jadi">Obat Jadi</option>
                                    <option value="racikan">Racikan</option>
                                </select>
                            </div>

                            <div class="md:col-span-6">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Item</label>
                                
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
                                            Pilih dari Master
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="baru" class="text-emerald-600 focus:ring-emerald-500" @change="item.compounded_product_id='';item.unit=''" />
                                            Formula Baru
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
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Instruksi Pakai</label>
                                <input v-model="item.usage_instructions" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="3x1 sesudah makan" />
                            </div>

                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Quantity</label>
                                <div class="flex gap-2">
                                    <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm font-semibold focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                                    <input v-model="item.unit" type="text" class="w-20 bg-muted border border-input rounded-md px-2 h-9 text-foreground text-xs font-medium focus:ring-1 focus:ring-ring outline-none text-center" :readonly="item.item_type === 'obat_jadi' || item.racikan_source === 'master'" placeholder="Satuan" required />
                                </div>
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
