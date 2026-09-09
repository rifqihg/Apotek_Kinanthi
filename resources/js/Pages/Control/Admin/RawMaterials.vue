<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: ControlLayout });

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
<div class="space-y-6">
    <!-- Header -->
    <div class="p-6 bg-card rounded-lg border border-border shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 bg-muted text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">Master Data</span>
                <span class="px-2.5 py-0.5 bg-muted/50 text-muted-foreground text-xs font-semibold uppercase tracking-wider rounded-md border border-border">Bahan Baku Racikan</span>
            </div>
            <h1 class="text-xl font-bold text-foreground tracking-tight">Data Bahan Baku</h1>
            <p class="text-muted-foreground text-sm max-w-xl">Katalog bahan baku untuk peracikan obat. Dikelola oleh Admin.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="bg-background border border-border px-4 py-2.5 rounded-md shadow-sm flex items-center gap-3">
                <div class="text-right">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Item Bahan</p>
                    <p class="text-xl font-semibold tracking-tight text-foreground">{{ materials.total }}</p>
                </div>
                <div class="w-8 h-8 bg-muted rounded-md flex items-center justify-center text-sm border border-border">🌿</div>
            </div>
            <button @click="openAdd" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700 h-9 px-4 rounded-md text-sm font-medium shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Bahan Baku
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-card rounded-lg shadow-sm border border-border p-3 flex flex-col md:flex-row gap-4">
        <div class="flex-1 relative">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input v-model="search" type="text" placeholder="Cari nama atau kode bahan baku..." class="w-full h-9 pl-9 pr-3 bg-background border border-input rounded-md text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" />
        </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-muted/50 border-b border-border">
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Bahan</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Kode</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Satuan</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Harga Beli</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Stok Total</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Threshold</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status Stok</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-if="filtered.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">Belum ada bahan baku terdaftar.</td>
                </tr>
                <tr v-for="m in filtered" :key="m.id" class="hover:bg-muted/40 transition-colors">
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-md bg-muted text-foreground flex items-center justify-center text-xs font-bold uppercase border border-border">
                                {{ m.nama_bahan?.substring(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-semibold text-foreground text-sm">{{ m.nama_bahan }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ m.bentuk_sediaan || '-' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-center text-xs font-medium text-muted-foreground tracking-wide">{{ m.kode_bahan || '-' }}</td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-medium bg-muted text-muted-foreground border border-border">
                            {{ m.satuan_ukur }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center font-medium text-foreground text-xs">
                        Rp {{ Number(m.harga_beli_satuan || 0).toLocaleString('id-ID') }}
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span :class="m.stok_total < m.threshold_min ? 'text-destructive' : 'text-foreground'" class="font-semibold text-sm">
                            {{ Number(m.stok_total).toLocaleString('id-ID') }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center text-xs font-medium text-muted-foreground">{{ Number(m.threshold_min).toLocaleString('id-ID') }}</td>
                    <td class="px-5 py-3.5 text-center">
                        <span :class="m.stok_total < m.threshold_min ? 'bg-destructive/10 text-destructive border-destructive/20' : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'" class="text-xs px-2.5 py-0.5 rounded-md font-semibold border uppercase tracking-wider inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full" :class="m.stok_total < m.threshold_min ? 'bg-destructive' : 'bg-emerald-500'"></span>
                            {{ m.stok_total < m.threshold_min ? 'Kritis' : 'Aman' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <button @click="openEdit(m)" class="w-8 h-8 rounded-md bg-background border border-input hover:bg-accent text-muted-foreground hover:text-foreground transition-colors flex items-center justify-center" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="deleteMaterial(m)" class="w-8 h-8 rounded-md bg-background border border-input hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors flex items-center justify-center" title="Hapus">
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
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-card rounded-lg shadow-lg w-full max-w-lg overflow-hidden border border-border">
            <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">{{ editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru' }}</h3>
                    <p class="text-xs text-muted-foreground">{{ editingMaterial ? 'Perbarui data master bahan baku.' : 'Daftarkan bahan baku baru. Stok awal langsung aktif.' }}</p>
                </div>
                <button @click="closeModal" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form @submit.prevent="submitForm" class="p-6 space-y-4 overflow-y-auto max-h-[75vh]">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Nama Bahan Baku <span class="text-destructive">*</span></label>
                        <input v-model="form.nama_bahan" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: Paracetamol">
                        <p v-if="form.errors.nama_bahan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.nama_bahan }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Bentuk Sediaan <span class="text-destructive">*</span></label>
                        <input v-model="form.bentuk_sediaan" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Serbuk, Cairan, dll">
                        <p v-if="form.errors.bentuk_sediaan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.bentuk_sediaan }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Kode Bahan</label>
                        <input v-model="form.kode_bahan" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Satuan Ukur <span class="text-destructive">*</span></label>
                        <input v-model="form.satuan_ukur" type="text" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Gram, mL, dll">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Harga Beli / Satuan <span class="text-destructive">*</span></label>
                        <input v-model="form.harga_beli_satuan" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                        <p v-if="form.errors.harga_beli_satuan" class="text-destructive text-xs font-medium mt-1">{{ form.errors.harga_beli_satuan }}</p>
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Minimum Stok (Threshold) <span class="text-destructive">*</span></label>
                        <input v-model="form.threshold_min" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                    </div>

                    <!-- Only shown when creating a new item -->
                    <template v-if="!editingMaterial">
                        <div class="col-span-2 pt-3 border-t border-border">
                            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Data Stok Awal</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Qty Stok Awal <span class="text-destructive">*</span></label>
                            <input v-model="form.expected_quantity" type="number" required min="0" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="0">
                            <p v-if="form.errors.expected_quantity" class="text-destructive text-xs font-medium mt-1">{{ form.errors.expected_quantity }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">No. Batch</label>
                            <input v-model="form.batch_no" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Opsional (auto-generate)">
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Tanggal Kadaluarsa <span class="text-destructive">*</span></label>
                            <input v-model="form.expired_date" type="date" required class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors">
                            <p v-if="form.errors.expired_date" class="text-destructive text-xs font-medium mt-1">{{ form.errors.expired_date }}</p>
                        </div>
                    </template>

                    <div class="col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-foreground uppercase tracking-wider">Deskripsi / Catatan</label>
                        <textarea v-model="form.deskripsi" rows="2" class="w-full p-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors resize-none" placeholder="Opsional"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <button type="button" @click="closeModal" class="h-9 px-4 rounded-md border border-input bg-background text-foreground text-sm font-medium hover:bg-accent transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing" class="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm transition-colors disabled:opacity-50 shadow-sm">
                        {{ form.processing ? 'Menyimpan...' : (editingMaterial ? 'Simpan Perubahan' : 'Tambah Bahan Baku') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</Teleport>
</template>
