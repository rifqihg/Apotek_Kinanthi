<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    opnames: Object,
    medicines: Array,
    rawMaterials: Array,
});

const showModal = ref(false);

const form = useForm({
    tanggal_opname: new Date().toISOString().split('T')[0],
    keterangan: '',
    items: [
        { type: 'medicine', medicine_id: '', raw_material_id: '', qty_fisik: '' }
    ]
});

const addItem = () => {
    form.items.push({ type: 'medicine', medicine_id: '', raw_material_id: '', qty_fisik: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('control.admin.stock-opname.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            form.items = [{ type: 'medicine', medicine_id: '', raw_material_id: '', qty_fisik: '' }];
        }
    });
};
</script>

<template>
    <Head title="Stock Opname Admin" />

    <ControlLayout>
        <template #header-title>Stock Opname</template>

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div>
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Daftar Sesi Stock Opname</h2>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Riwayat penyesuaian stok langsung oleh Admin.</p>
                </div>
                <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[52px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Buat Stock Opname Baru
                </button>
            </div>

            <!-- Table -->
            <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Tanggal</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Keterangan</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Jumlah Item</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EEF6]">
                        <tr v-if="opnames.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-bold text-xs uppercase tracking-widest">Belum ada riwayat stock opname dari Admin.</td>
                        </tr>
                        <tr v-for="op in opnames.data" :key="op.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">
                                {{ new Date(op.opname_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-500">
                                {{ op.catatan || '-' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-50 border border-[#E8EEF6] text-slate-600 px-3 py-1.5 rounded-[12px] text-xs font-bold">{{ op.items_count || 0 }} item(s)</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="op.status === 'completed'" class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-3 py-1.5 rounded-[12px] text-[9px] font-bold uppercase tracking-widest">Selesai / Disesuaikan</span>
                                <span v-else class="bg-amber-50 text-amber-600 border border-amber-200 px-3 py-1.5 rounded-[12px] text-[9px] font-bold uppercase tracking-widest">Draft</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('control.admin.stock-opname.show', op.id)" class="inline-flex items-center justify-center text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-600 hover:text-white h-[52px] px-6 rounded-[16px] text-[10px] font-bold uppercase tracking-widest transition-all active:scale-95">Lihat Detail</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="opnames.links?.length > 3" class="px-6 py-4 border-t border-[#E8EEF6] flex items-center justify-center gap-1.5 bg-slate-50/30">
                    <template v-for="l in opnames.links" :key="l.label">
                        <button v-if="l.url" @click="router.get(l.url)" :class="l.active ? 'bg-blue-600 text-white font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] border-blue-600' : 'bg-white border-[#E8EEF6] text-slate-500 hover:bg-blue-500 hover:text-blue-600'" class="w-8 h-8 flex items-center justify-center rounded-[16px] text-xs font-bold transition-all border uppercase" v-html="l.label"></button>
                        <span v-else class="w-8 h-8 flex items-center justify-center text-slate-300 text-xs font-bold uppercase" v-html="l.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
                    <div class="absolute inset-0" @click="showModal = false"></div>
                    <div class="relative bg-white rounded-[24px] shadow-2xl w-[90%] max-w-4xl max-h-[90vh] overflow-hidden flex flex-col border border-[#E8EEF6]">
                        <div class="px-8 py-6 border-b border-[#E8EEF6] flex justify-between items-center bg-slate-50/50">
                            <div>
                                <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight">Buat Stock Opname Baru</h3>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter mt-1.5">Penyesuaian stok sistem dengan fisik.</p>
                            </div>
                            <button @click="showModal = false" class="w-8 h-8 flex items-center justify-center rounded-[16px] bg-white border border-[#E8EEF6] text-slate-400 hover:text-slate-600 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-8 overflow-y-auto flex-1">
                            <form @submit.prevent="submit" class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Tanggal Opname</label>
                                        <input type="date" v-model="form.tanggal_opname" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Keterangan (Opsional)</label>
                                        <input type="text" v-model="form.keterangan" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none placeholder:font-medium" placeholder="Contoh: Audit mendadak Q3">
                                    </div>
                                </div>

                                <div class="border-t border-[#E8EEF6] pt-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-widest">Item yang Diopname</h4>
                                        <button type="button" @click="addItem" class="text-[10px] bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded-[12px] transition-colors border border-blue-200 uppercase tracking-widest">
                                            + Tambah Baris
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        <div v-for="(item, idx) in form.items" :key="idx" class="flex items-center gap-3 bg-slate-50/50 p-3 rounded-[16px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                                            <div class="w-48">
                                                <select v-model="item.type" @change="item.medicine_id = ''; item.raw_material_id = ''" class="w-full h-[52px] px-4 text-sm border-[#E8EEF6] rounded-[16px] focus:ring focus:ring-blue-200/50 focus:border-blue-500 font-bold text-slate-900 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] outline-none">
                                                    <option value="medicine">Obat Jadi</option>
                                                    <option value="raw_material">Bahan Baku</option>
                                                </select>
                                            </div>
                                            <div class="flex-1">
                                                <select v-if="item.type === 'medicine'" v-model="item.medicine_id" class="w-full h-[52px] px-4 text-sm border-[#E8EEF6] rounded-[16px] focus:ring focus:ring-blue-200/50 focus:border-blue-500 font-bold text-slate-900 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] outline-none" required>
                                                    <option value="" disabled>Pilih Obat Jadi...</option>
                                                    <option v-for="m in medicines" :key="m.id" :value="m.id">{{ m.nama_obat }}</option>
                                                </select>
                                                <select v-if="item.type === 'raw_material'" v-model="item.raw_material_id" class="w-full h-[52px] px-4 text-sm border-[#E8EEF6] rounded-[16px] focus:ring focus:ring-blue-200/50 focus:border-blue-500 font-bold text-slate-900 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] outline-none" required>
                                                    <option value="" disabled>Pilih Bahan Baku...</option>
                                                    <option v-for="rm in rawMaterials" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }}</option>
                                                </select>
                                            </div>
                                            <div class="w-32">
                                                <input type="number" v-model="item.qty_fisik" class="w-full h-[52px] px-4 text-sm text-right border-[#E8EEF6] rounded-[16px] focus:ring focus:ring-blue-200/50 focus:border-blue-500 font-bold text-slate-900 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] outline-none placeholder:font-medium" placeholder="Qty Fisik" required min="0" step="0.01">
                                            </div>
                                            <button type="button" @click="removeItem(idx)" :disabled="form.items.length === 1" class="w-10 h-10 flex items-center justify-center rounded-[12px] bg-white border border-[#E8EEF6] text-slate-400 hover:text-rose-500 hover:border-rose-500 transition-colors disabled:opacity-50">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="px-8 py-6 border-t border-[#E8EEF6] flex justify-end gap-3 bg-slate-50/50">
                            <button type="button" @click="showModal = false" class="px-6 h-[52px] text-[11px] font-bold text-slate-500 bg-white border border-[#E8EEF6] hover:bg-slate-50 rounded-[16px] uppercase tracking-widest transition-colors shadow-[0_8px_24px_rgba(15,23,42,0.04)]">Batal</button>
                            <button type="button" @click="submit" :disabled="form.processing" class="px-6 h-[52px] bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold rounded-[16px] uppercase tracking-widest transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] disabled:opacity-50 active:scale-95">
                                Simpan Opname
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </ControlLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
