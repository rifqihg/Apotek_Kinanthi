<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

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
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Resep Baru</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Masukan data resep dari dokter</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-slate-500 hover:text-slate-700 text-sm font-bold transition-colors">
                Batal
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Data Pasien & Dokter -->
            <div class="bg-white border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] space-y-4">
                <h3 class="text-slate-800 font-bold uppercase tracking-wide text-sm border-b border-[#E8EEF6] pb-3">Informasi Utama</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-slate-700 text-[11px] font-black uppercase mb-1.5 ml-1">Pasien <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select v-model="form.patient_id" class="flex-1 bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none transition-all" required>
                                <option value="">Pilih Pasien...</option>
                                <option v-for="p in localPatients" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <button type="button" @click="showPatientModal = true" class="bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-4 rounded-[16px] text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-colors">
                                Pasien Baru +
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-700 text-[11px] font-black uppercase mb-1.5 ml-1">Tanggal Resep <span class="text-red-500">*</span></label>
                        <input v-model="form.prescription_date" type="date" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" required />
                    </div>
                    <div>
                        <label class="block text-slate-700 text-[11px] font-black uppercase mb-1.5 ml-1">Nama Dokter <span class="text-red-500">*</span></label>
                        <input v-model="form.doctor_name" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" placeholder="dr. ..." required />
                    </div>
                    <div>
                        <label class="block text-slate-700 text-[11px] font-black uppercase mb-1.5 ml-1">Klinik/RS Asal</label>
                        <input v-model="form.clinic_name" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" placeholder="Opsional" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 text-[11px] font-black uppercase mb-1.5 ml-1">Catatan Tambahan</label>
                        <textarea v-model="form.notes" rows="2" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm focus:border-teal-500 outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-slate-50 border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div class="flex items-center justify-between mb-4 border-b border-[#E8EEF6] pb-3">
                    <h3 class="text-slate-800 font-bold uppercase tracking-wide text-sm">Daftar Obat / Racikan</h3>
                    <button type="button" @click="addItem" class="bg-slate-900 text-white hover:bg-slate-800 h-[52px] px-6 rounded-[16px] text-[10px] font-black uppercase tracking-widest transition-colors">
                        Tambah Item +
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="(item, idx) in form.items" :key="idx" class="bg-white border border-[#E8EEF6] p-5 rounded-[24px] relative">
                        <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 transition-colors bg-slate-50 hover:bg-red-50 w-8 h-8 rounded-[16px] flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-slate-700 text-[10px] font-black uppercase mb-1.5 ml-1">Tipe Item</label>
                                <select v-model="item.item_type" @change="handleItemTypeChange(idx)" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none">
                                    <option value="obat_jadi">Obat Jadi</option>
                                    <option value="racikan">Racikan</option>
                                </select>
                            </div>

                            <div class="md:col-span-6">
                                <label class="block text-slate-700 text-[10px] font-black uppercase mb-1.5 ml-1">Nama Item</label>
                                
                                <template v-if="item.item_type === 'obat_jadi'">
                                    <select v-model="item.medicine_id" @change="handleMedicineChange(idx)" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" required>
                                        <option value="">Pilih Obat...</option>
                                        <option v-for="m in medicines" :key="m.id" :value="m.id">{{ m.nama_obat }} ({{ m.satuan_stok }})</option>
                                    </select>
                                </template>

                                <template v-if="item.item_type === 'racikan'">
                                    <div class="flex gap-4 mb-2">
                                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="master" class="text-teal-600 focus:ring-teal-500" @change="item.custom_formula_name='';item.unit=''" />
                                            Pilih dari Master
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="baru" class="text-teal-600 focus:ring-teal-500" @change="item.compounded_product_id='';item.unit=''" />
                                            Formula Baru
                                        </label>
                                    </div>

                                    <div v-if="item.racikan_source === 'master'">
                                        <select v-model="item.compounded_product_id" @change="handleProductChange(idx)" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" required>
                                            <option value="">Pilih Racikan Master...</option>
                                            <option v-for="p in compounded_products" :key="p.id" :value="p.id">{{ p.nama_produk }}</option>
                                        </select>
                                    </div>
                                    <div v-else>
                                        <input v-model="item.custom_formula_name" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-bold focus:border-teal-500 outline-none" placeholder="Nama racikan kustom..." required />
                                    </div>
                                </template>
                            </div>

                            <div class="md:col-span-3">
                                <label class="block text-slate-700 text-[10px] font-black uppercase mb-1.5 ml-1">Instruksi Pakai</label>
                                <input v-model="item.usage_instructions" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm focus:border-teal-500 outline-none" placeholder="3x1 sesudah makan" />
                            </div>

                            <div class="md:col-span-3 mt-0 md:-mt-2">
                                <label class="block text-slate-700 text-[10px] font-black uppercase mb-1.5 ml-1">Quantity</label>
                                <div class="flex gap-2">
                                    <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-slate-900 text-sm font-black focus:border-teal-500 outline-none" required />
                                    <input v-model="item.unit" type="text" class="w-20 bg-slate-100 border border-[#E8EEF6] rounded-[16px] px-2 py-2.5 text-slate-700 text-xs font-bold focus:border-teal-500 outline-none text-center" :readonly="item.item_type === 'obat_jadi' || item.racikan_source === 'master'" placeholder="Satuan" required />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-4 rounded-[24px] text-base font-black shadow-lg shadow-teal-100 transition-all active:scale-[0.98] disabled:opacity-50 uppercase tracking-widest">
                Simpan Resep
            </button>
        </form>
    </div>

    <!-- Patient Modal -->
    <Teleport to="body">
        <div v-if="showPatientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showPatientModal = false"></div>
            <div class="relative bg-white rounded-[24px] shadow-xl w-full max-w-md p-6 border border-[#E8EEF6]">
                <h3 class="text-slate-900 font-bold text-lg mb-4">Tambah Pasien Baru</h3>
                <form @submit.prevent="savePatient" class="space-y-4">
                    <div>
                        <label class="block text-slate-700 text-xs font-bold mb-1.5">Nama Pasien *</label>
                        <input v-model="patientForm.name" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-sm" required />
                    </div>
                    <div>
                        <label class="block text-slate-700 text-xs font-bold mb-1.5">No. Telepon</label>
                        <input v-model="patientForm.phone" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-slate-700 text-xs font-bold mb-1.5">Catatan / Alergi</label>
                        <textarea v-model="patientForm.notes" rows="2" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-sm"></textarea>
                    </div>
                    <div class="pt-2 flex gap-2">
                        <button type="button" @click="showPatientModal = false" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 py-3 rounded-[16px] text-xs font-bold transition-colors">Batal</button>
                        <button type="submit" :disabled="patientForm.processing" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-[16px] text-xs font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] shadow-emerald-100 transition-colors">Simpan Pasien</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
