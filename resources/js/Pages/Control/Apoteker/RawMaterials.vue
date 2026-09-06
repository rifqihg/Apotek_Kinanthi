<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ rawMaterials: Object });
const showModal = ref(false);
const editMode = ref(false);
const currentId = ref(null);

const bentukSediaanOptions = [
    { label: 'Padat (Solid)', value: 'Padat', units: ['mg', 'g', 'kg', 'Tablet', 'Kapsul', 'Pil', 'Bungkus', 'Box'] },
    { label: 'Setengah Padat (Semi-solid)', value: 'Setengah Padat', units: ['g', 'Tube', 'Pot'] },
    { label: 'Cair (Liquid)', value: 'Cair', units: ['mL', 'cc', 'L', 'Botol', 'Vial', 'Ampul'] },
    { label: 'Gas (Aerosol)', value: 'Gas', units: ['Canister'] }
];

const form = useForm({
    nama_bahan: '',
    bentuk_sediaan: '',
    kode_bahan: '',
    satuan_ukur: '',
    harga_beli_satuan: 0,
    threshold_min: 0,
    deskripsi: '',
    expected_quantity: 0,
    batch_no: '',
    expired_date: ''
});

watch(() => form.bentuk_sediaan, (newVal) => {
    const selected = bentukSediaanOptions.find(o => o.value === newVal);
    if (selected && !selected.units.includes(form.satuan_ukur)) {
        form.satuan_ukur = selected.units[0];
    }
});

function openModal(rm = null) {
    if (rm) {
        editMode.value = true;
        currentId.value = rm.id;
        form.nama_bahan = rm.nama_bahan;
        form.bentuk_sediaan = rm.bentuk_sediaan || '';
        form.kode_bahan = rm.kode_bahan;
        form.satuan_ukur = rm.satuan_ukur;
        form.harga_beli_satuan = rm.harga_beli_satuan;
        form.threshold_min = rm.threshold_min;
        form.deskripsi = rm.deskripsi;
        form.expected_quantity = 0;
        form.batch_no = '';
        const d = new Date();
        d.setFullYear(d.getFullYear() + 2);
        form.expired_date = d.toISOString().split('T')[0];
    } else {
        editMode.value = false;
        currentId.value = null;
        form.reset();
    }
    showModal.value = true;
}

function submit() {
    if (editMode.value) {
        form.put(route('control.apoteker.raw-materials.update', currentId.value), {
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('control.apoteker.raw-materials.store'), {
            onSuccess: () => closeModal()
        });
    }
}

function closeModal() {
    showModal.value = false;
    form.reset();
}

function destroy(id) {
    if (confirm('Apakah Anda yakin ingin menghapus bahan baku ini dari katalog?')) {
        router.delete(route('control.apoteker.raw-materials.destroy', id));
    }
}

const currentUnits = () => {
    const selected = bentukSediaanOptions.find(o => o.value === form.bentuk_sediaan);
    return selected ? selected.units : [];
};
</script>

<template>
    <Head title="Stok Bahan Baku" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Stok Bahan Baku Farmasi</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Registrasi dan pelacakan batch bahan mentah laboratorium</p>
            </div>
            <button @click="openModal()" class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-[1.2rem] text-[10px] font-black uppercase tracking-widest shadow-lg shadow-slate-900/10 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Registrasi Bahan Baru
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-[1.5rem] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-slate-50/50 border-b border-[#E8EEF6] uppercase">
                    <tr>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-left uppercase">Informasi Bahan</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Bentuk / Satuan</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Harga Beli</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Stok Total</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 tracking-[0.15em] text-center uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="rawMaterials?.data?.length === 0">
                        <td colspan="5" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 border border-[#E8EEF6]">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Katalog Bahan Baku Masih Kosong</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="rm in (rawMaterials?.data || [])" :key="rm.id" class="hover:bg-emerald-50/20 transition-all group">
                        <td class="px-8 py-5">
                            <span class="font-bold text-slate-800 text-sm block group-hover:text-emerald-700 transition-colors uppercase tracking-tighter leading-tight">{{ rm.nama_bahan || 'Tanpa Nama' }}</span>
                            <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest mt-1 block">{{ rm.kode_bahan || 'NO-CODE' }}</span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <span class="px-2.5 py-1 rounded-[16px] text-[10px] font-black bg-slate-100 text-slate-600 border border-[#E8EEF6] uppercase tracking-tight">
                                    {{ rm.bentuk_sediaan || '-' }}
                                </span>
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest">{{ rm.satuan_ukur || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="font-black text-slate-900 text-xs">Rp {{ Number(rm.harga_beli_satuan || 0).toLocaleString('id-ID') }}</span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span :class="Number(rm.stok_total || 0) < (rm.threshold_min || 0) ? 'bg-rose-50 text-rose-600 border-rose-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'" class="px-3 py-1.5 rounded-[16px] font-black border text-[10px] uppercase tracking-widest">
                                {{ Number(rm.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ rm.satuan_ukur || '' }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="openModal(rm)" class="w-8 h-8 flex items-center justify-center bg-slate-100 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 rounded-[16px] transition-all border border-transparent hover:border-emerald-200 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 00-2 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button @click="destroy(rm.id)" class="w-8 h-8 flex items-center justify-center bg-slate-100 text-slate-400 hover:bg-rose-50 hover:text-rose-600 rounded-[16px] transition-all border border-transparent hover:border-rose-200 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rawMaterials?.links?.length > 3" class="flex justify-center gap-1.5 pt-6">
            <template v-for="l in (rawMaterials?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-emerald-500 hover:text-emerald-500'" 
                    class="w-9 h-9 flex items-center justify-center rounded-[16px] text-xs font-black transition-all border uppercase" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
            <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-xl transform transition-all border border-[#E8EEF6] overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-slate-900 font-black text-sm uppercase tracking-tight leading-none">{{ editMode ? 'Update Data Bahan Baku' : 'Registrasi Bahan Baku Baru' }}</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-1.5">Lengkapi identitas batch dan kontrol stok bahan.</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-8 space-y-6 max-h-[70vh] overflow-y-auto scrollbar-v-minimal bg-white">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Nama Lengkap Bahan <span class="text-rose-500">*</span></label>
                        <input v-model="form.nama_bahan" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all placeholder:text-slate-300 uppercase tracking-tight" placeholder="Contoh: Paracetamol Powder..." required />
                        <p v-if="form.errors.nama_bahan" class="text-rose-500 text-[10px] font-bold uppercase mt-1 pl-1">{{ form.errors.nama_bahan }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Bentuk Sediaan <span class="text-rose-500">*</span></label>
                            <select v-model="form.bentuk_sediaan" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all uppercase tracking-tight" required>
                                <option v-for="opt in bentukSediaanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Satuan Dasar <span class="text-rose-500">*</span></label>
                            <select v-model="form.satuan_ukur" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all uppercase tracking-tight" required>
                                <option v-for="u in currentUnits()" :key="u" :value="u">{{ u }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Kode Bahan</label>
                            <input v-model="form.kode_bahan" type="text" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all font-mono uppercase" placeholder="PBF-XXX" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Qty Opname <span class="text-rose-500">*</span></label>
                            <input v-model="form.expected_quantity" type="number" step="0.01" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all" :disabled="editMode" required />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Threshold <span class="text-rose-500">*</span></label>
                            <input v-model="form.threshold_min" type="number" step="0.01" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all" required />
                        </div>
                    </div>

                    <div v-if="!editMode" class="grid grid-cols-2 gap-5 p-6 bg-slate-50 rounded-[24px] border border-[#E8EEF6]">
                        <div class="space-y-1.5">
                            <label class="block text-[9px] font-black text-emerald-600 uppercase tracking-widest">No. Batch Pabrik <span class="text-rose-500">*</span></label>
                            <input v-model="form.batch_no" type="text" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-xs font-black text-slate-900 focus:border-emerald-500 outline-none transition-all font-mono uppercase" required placeholder="BN-XXXX" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[9px] font-black text-emerald-600 uppercase tracking-widest">Kadaluarsa Batch <span class="text-rose-500">*</span></label>
                            <input v-model="form.expired_date" type="date" class="w-full bg-white border border-[#E8EEF6] rounded-[16px] h-[52px] px-6.5 text-xs font-black text-slate-900 focus:border-emerald-500 outline-none transition-all" required />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Biaya Akuisisi Satuan <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">Rp</span>
                            <input v-model="form.harga_beli_satuan" type="number" step="0.01" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] pl-12 pr-4 py-4 text-base font-black text-slate-900 focus:bg-white focus:border-emerald-500 outline-none transition-all" required />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Catatan Stabilitas / Deskripsi</label>
                        <textarea v-model="form.deskripsi" rows="2" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-500 outline-none resize-none transition-all placeholder:text-slate-300 uppercase tracking-tight" placeholder="Opsional..."></textarea>
                    </div>
                </form>

                <div class="p-8 border-t border-[#E8EEF6] bg-slate-50 flex gap-4 mt-auto">
                    <button type="button" @click="closeModal" class="flex-1 bg-white border border-[#E8EEF6] text-slate-500 hover:bg-slate-100 py-4 rounded-[16px] text-[10px] font-black uppercase tracking-widest transition-all">Batal</button>
                    <button @click="submit" :disabled="form.processing" class="flex-1 bg-slate-900 hover:bg-emerald-600 text-white py-4 rounded-[16px] text-[10px] font-black uppercase tracking-widest shadow-lg shadow-slate-900/20 active:scale-95 transition-all">
                        {{ editMode ? 'Simpan Perubahan' : 'Konfirmasi Registrasi' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Remove number input default arrows */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
