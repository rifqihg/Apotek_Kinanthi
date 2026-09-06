<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ materials: Object });
const search = ref('');
const showModal = ref(false);
const editingMaterial = ref(null);

const filtered = computed(() => {
    if (!search.value) return props.materials.data;
    const s = search.value.toLowerCase();
    return props.materials.data.filter(m =>
        m.nama_bahan?.toLowerCase().includes(s) || m.kode_bahan?.toLowerCase().includes(s)
    );
});

const form = useForm({
    nama_bahan: '',
    bentuk_sediaan: '',
    kode_bahan: '',
    satuan_ukur: '',
    expected_quantity: '',
    batch_no: '',
    expired_date: '',
    harga_beli_satuan: '',
    threshold_min: '',
    deskripsi: '',
});

const openAdd = () => {
    editingMaterial.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (m) => {
    editingMaterial.value = m;
    form.nama_bahan = m.nama_bahan;
    form.bentuk_sediaan = m.bentuk_sediaan || '';
    form.kode_bahan = m.kode_bahan || '';
    form.satuan_ukur = m.satuan_ukur || '';
    form.harga_beli_satuan = m.harga_beli_satuan || '';
    form.threshold_min = m.threshold_min || '';
    form.deskripsi = m.deskripsi || '';
    // Non-editable after creation:
    form.expected_quantity = '';
    form.batch_no = '';
    form.expired_date = '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingMaterial.value = null;
};

const submitForm = () => {
    if (editingMaterial.value) {
        form.put(route('control.admin.raw-materials.update', editingMaterial.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.post(route('control.admin.raw-materials.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
};

const deleteMaterial = (m) => {
    if (!confirm(`Hapus bahan baku "${m.nama_bahan}"? Tindakan ini tidak dapat diurungkan.`)) return;
    router.delete(route('control.admin.raw-materials.destroy', m.id), { preserveScroll: true });
};
</script>

<template>
<Head title="Data Bahan Baku" />
<ControlLayout title="Master Data Bahan Baku">
<div class="space-y-8">
    <!-- Header -->
    <div class="relative p-10 bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] overflow-hidden group">
        <div class="absolute top-0 right-0 w-80 h-80 bg-emerald-50 rounded-full -mr-40 -mt-40 transition-transform group-hover:scale-110 duration-1000"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-teal-50 rounded-full -ml-20 -mb-20 opacity-50"></div>
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <span class="px-4 py-1.5 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full">Master Data</span>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight leading-none">Data Bahan Baku</h1>
                <p class="text-slate-500 font-medium">Katalog bahan baku untuk peracikan. Dikelola oleh Admin.</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-white/50 backdrop-blur-md border border-white p-6 rounded-[2.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Bahan</p>
                        <p class="text-3xl font-black text-emerald-600 leading-none mt-1">{{ materials.total }}</p>
                    </div>
                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-[24px] flex items-center justify-center text-2xl shadow-xl shadow-emerald-500/20 text-white">🌿</div>
                </div>
                <button @click="openAdd" class="flex items-center gap-2 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold px-6 py-4 rounded-[20px] shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 transition-all hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Tambah Bahan Baku
                </button>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-[2.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] p-6">
        <div class="relative">
            <svg class="w-5 h-5 absolute left-6 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input v-model="search" type="text" placeholder="Cari nama atau kode bahan baku..." class="w-full pl-14 pr-6 py-4 bg-slate-50 border-[#E8EEF6] rounded-[24px] text-sm font-medium focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all" />
        </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
        <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-slate-50 border-b border-[#E8EEF6]">
                <tr>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Nama Bahan</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Kode</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Satuan</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Harga Beli</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Stok Total</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Threshold</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Status Stok</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-if="filtered.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center text-slate-400">Belum ada bahan baku terdaftar.</td>
                </tr>
                <tr v-for="m in filtered" :key="m.id" class="hover:bg-slate-50/70 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-[16px] bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs font-bold border border-emerald-100/50">
                                {{ m.nama_bahan?.substring(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">{{ m.nama_bahan }}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ m.bentuk_sediaan || '-' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center font-mono text-xs font-bold text-slate-600">{{ m.kode_bahan || '-' }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2.5 py-1 bg-slate-100/60 text-slate-600 text-[10px] font-bold uppercase tracking-wider rounded-full border border-[#E8EEF6]/50">{{ m.satuan_ukur }}</span>
                    </td>
                    <td class="px-6 py-4 text-center font-bold text-slate-700 text-xs">
                        Rp {{ Number(m.harga_beli_satuan || 0).toLocaleString('id-ID') }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span :class="m.stok_total < m.threshold_min ? 'text-red-600' : 'text-slate-900'" class="font-mono font-black text-base">
                            {{ Number(m.stok_total).toLocaleString('id-ID') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-xs font-bold text-slate-500 font-mono">{{ Number(m.threshold_min).toLocaleString('id-ID') }}</td>
                    <td class="px-6 py-4 text-center">
                        <span :class="m.stok_total < m.threshold_min ? 'bg-red-50 text-red-700 border-red-200/50' : 'bg-emerald-50 text-emerald-700 border-emerald-200/50'" class="text-[10px] px-2.5 py-1 rounded-full font-bold border uppercase tracking-wider">
                            {{ m.stok_total < m.threshold_min ? 'Kritis' : 'Aman' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button @click="openEdit(m)" class="w-8 h-8 rounded-[10px] bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="deleteMaterial(m)" class="w-8 h-8 rounded-[10px] bg-red-50 text-red-500 hover:bg-red-100 transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add / Edit -->
<Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-white rounded-[28px] shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="p-8 border-b border-[#E8EEF6]">
                <h3 class="text-xl font-black text-slate-900">{{ editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru' }}</h3>
                <p class="text-sm text-slate-500 mt-1">{{ editingMaterial ? 'Perbarui data master bahan baku.' : 'Daftarkan bahan baku baru. Stok awal langsung aktif.' }}</p>
            </div>
            <form @submit.prevent="submitForm" class="p-8 space-y-4 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Nama Bahan Baku *</label>
                        <input v-model="form.nama_bahan" type="text" required class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="Contoh: Paracetamol">
                        <p v-if="form.errors.nama_bahan" class="text-red-500 text-xs mt-1">{{ form.errors.nama_bahan }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Bentuk Sediaan *</label>
                        <input v-model="form.bentuk_sediaan" type="text" required class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="Serbuk, Cairan, dll">
                        <p v-if="form.errors.bentuk_sediaan" class="text-red-500 text-xs mt-1">{{ form.errors.bentuk_sediaan }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Kode Bahan</label>
                        <input v-model="form.kode_bahan" type="text" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="Opsional">
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Satuan Ukur *</label>
                        <input v-model="form.satuan_ukur" type="text" required class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="Gram, mL, dll">
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Harga Beli / Satuan *</label>
                        <input v-model="form.harga_beli_satuan" type="number" required min="0" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="0">
                        <p v-if="form.errors.harga_beli_satuan" class="text-red-500 text-xs mt-1">{{ form.errors.harga_beli_satuan }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Minimum Stok (Threshold) *</label>
                        <input v-model="form.threshold_min" type="number" required min="0" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="0">
                    </div>

                    <!-- Only shown when creating a new item -->
                    <template v-if="!editingMaterial">
                        <div class="col-span-2 pt-2 border-t border-[#E8EEF6]">
                            <p class="text-xs font-black text-emerald-700 uppercase tracking-wider">Data Stok Awal</p>
                        </div>
                        <div>
                            <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Qty Stok Awal *</label>
                            <input v-model="form.expected_quantity" type="number" required min="0" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="0">
                            <p v-if="form.errors.expected_quantity" class="text-red-500 text-xs mt-1">{{ form.errors.expected_quantity }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-black text-slate-600 uppercase tracking-wider">No. Batch</label>
                            <input v-model="form.batch_no" type="text" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500" placeholder="Opsional (auto-generate)">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Tanggal Kadaluarsa *</label>
                            <input v-model="form.expired_date" type="date" required class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500">
                            <p v-if="form.errors.expired_date" class="text-red-500 text-xs mt-1">{{ form.errors.expired_date }}</p>
                        </div>
                    </template>

                    <div class="col-span-2">
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Deskripsi / Catatan</label>
                        <textarea v-model="form.deskripsi" rows="3" class="mt-1 w-full border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:ring-emerald-500 focus:border-emerald-500 resize-none" placeholder="Opsional"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" @click="closeModal" class="flex-1 py-3 rounded-[16px] border border-[#E8EEF6] text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 py-3 rounded-[16px] bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 transition-all disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : (editingMaterial ? 'Simpan Perubahan' : 'Tambah Bahan Baku') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</Teleport>
</ControlLayout>
</template>
