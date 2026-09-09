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
                <h1 class="text-xl font-bold tracking-tight text-foreground">Stok Bahan Baku Farmasi</h1>
                <p class="text-xs text-muted-foreground mt-0.5">Registrasi dan pelacakan batch bahan mentah laboratorium</p>
            </div>
            <button @click="openModal()" class="h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Registrasi Bahan Baru
            </button>
        </div>

        <!-- Table View -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-muted/50 border-b border-border uppercase">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground tracking-wider text-left">Informasi Bahan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground tracking-wider text-center">Bentuk / Satuan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground tracking-wider text-center">Harga Beli</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground tracking-wider text-center">Stok Total</th>
                        <th class="px-4 py-3 text-xs font-semibold text-muted-foreground tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="rawMaterials?.data?.length === 0">
                        <td colspan="5" class="px-4 py-12 text-center text-muted-foreground text-xs font-medium">
                            Katalog Bahan Baku Masih Kosong
                        </td>
                    </tr>
                    <tr v-for="rm in (rawMaterials?.data || [])" :key="rm.id" class="hover:bg-muted/50 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-semibold text-foreground text-sm block leading-tight">{{ rm.nama_bahan || 'Tanpa Nama' }}</span>
                            <span class="text-xs text-muted-foreground tracking-wide mt-0.5 block">{{ rm.kode_bahan || 'NO-CODE' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-muted text-muted-foreground border border-border">
                                    {{ rm.bentuk_sediaan || '-' }}
                                </span>
                                <span class="text-muted-foreground text-xs font-medium">{{ rm.satuan_ukur || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-semibold text-foreground text-sm tabular-nums">Rp {{ Number(rm.harga_beli_satuan || 0).toLocaleString('id-ID') }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="Number(rm.stok_total || 0) < (rm.threshold_min || 0) ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'" class="px-2.5 py-0.5 rounded-md font-semibold border text-xs">
                                {{ Number(rm.stok_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} {{ rm.satuan_ukur || '' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button @click="openModal(rm)" class="h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors border border-input" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 00-2 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button @click="destroy(rm.id)" class="h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors border border-input" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="rawMaterials?.links?.length > 3" class="flex justify-center gap-1.5 pt-4">
            <template v-for="l in (rawMaterials?.links || [])" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-muted-foreground hover:bg-muted hover:text-foreground'" 
                    class="h-8 w-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                    v-html="l.label"
                ></button>
            </template>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-background/80 backdrop-blur-xs transition-opacity" @click="closeModal"></div>
            <div class="relative bg-card rounded-lg shadow-xl w-full max-w-lg transform transition-all border border-border overflow-hidden flex flex-col z-10">
                <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-foreground font-semibold text-base leading-none">{{ editMode ? 'Update Data Bahan Baku' : 'Registrasi Bahan Baku Baru' }}</h3>
                        <p class="text-xs text-muted-foreground mt-1">Lengkapi identitas batch dan kontrol stok bahan.</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto bg-card">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-muted-foreground">Nama Lengkap Bahan <span class="text-destructive">*</span></label>
                        <input v-model="form.nama_bahan" type="text" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors placeholder:text-muted-foreground" placeholder="Contoh: Paracetamol Powder..." required />
                        <p v-if="form.errors.nama_bahan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.nama_bahan }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Bentuk Sediaan <span class="text-destructive">*</span></label>
                            <select v-model="form.bentuk_sediaan" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required>
                                <option v-for="opt in bentukSediaanOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Satuan Dasar <span class="text-destructive">*</span></label>
                            <select v-model="form.satuan_ukur" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required>
                                <option v-for="u in currentUnits()" :key="u" :value="u">{{ u }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Kode Bahan</label>
                            <input v-model="form.kode_bahan" type="text" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" placeholder="PBF-XXX" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Qty Opname <span class="text-destructive">*</span></label>
                            <input v-model="form.expected_quantity" type="number" step="0.01" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" :disabled="editMode" required />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Threshold <span class="text-destructive">*</span></label>
                            <input v-model="form.threshold_min" type="number" step="0.01" class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                        </div>
                    </div>

                    <div v-if="!editMode" class="grid grid-cols-2 gap-3 p-3 bg-muted/40 rounded-lg border border-border">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">No. Batch Pabrik <span class="text-destructive">*</span></label>
                            <input v-model="form.batch_no" type="text" class="h-9 w-full bg-background border border-input rounded-md px-3 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required placeholder="BN-XXXX" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-muted-foreground">Kadaluarsa Batch <span class="text-destructive">*</span></label>
                            <input v-model="form.expired_date" type="date" class="h-9 w-full bg-background border border-input rounded-md px-3 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-muted-foreground">Biaya Akuisisi Satuan <span class="text-destructive">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">Rp</span>
                            <input v-model="form.harga_beli_satuan" type="number" step="0.01" class="h-9 w-full bg-background border border-input rounded-md pl-8 pr-3 text-sm font-semibold text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors" required />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-muted-foreground">Catatan Stabilitas / Deskripsi</label>
                        <textarea v-model="form.deskripsi" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring resize-none transition-colors" placeholder="Opsional..."></textarea>
                    </div>
                </form>

                <div class="px-6 py-4 border-t border-border bg-muted/30 flex gap-3 mt-auto">
                    <button type="button" @click="closeModal" class="h-9 px-4 flex-1 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">Batal</button>
                    <button @click="submit" :disabled="form.processing" class="h-9 px-4 flex-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors">
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
