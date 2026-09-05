<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ disposals: Object, rawMaterials: Array });
const showModal = ref(false);

const form = useForm({
    raw_material_id: '',
    qty_lost: '',
    reason: '',
    evidence: null
});

const submit = () => {
    form.post(route('control.apoteker.disposals.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};

const handleFile = (e) => {
    form.evidence = e.target.files[0];
};
</script>

<template>
    <Head title="Pemusnahan" />
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Pemusnahan & Audit Kerusakan</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Audit pelepasan inventaris untuk bahan rusak atau kadaluarsa</p>
            </div>
            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-[1.2rem] text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-900/10 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Catat Pemusnahan Baru
            </button>
        </div>

        <!-- Table View -->
        <div class="bg-white border border-[#E8EEF6] rounded-[1.5rem] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-[0.15em] border-b border-[#E8EEF6]">
                        <tr>
                            <th class="px-8 py-5">Timestamp</th>
                            <th class="px-8 py-5">Substrat / Bahan</th>
                            <th class="px-8 py-5 text-right">Qty Musnah</th>
                            <th class="px-8 py-5">Rasionalisasi</th>
                            <th class="px-8 py-5">Verifikasi Visual</th>
                            <th class="px-8 py-5 text-center">PIC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="disposals.data.length === 0">
                            <td colspan="6" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 border border-[#E8EEF6]">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </div>
                                    <span class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Belum Ada Riwayat Pemusnahan</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="d in disposals.data" :key="d.id" class="hover:bg-rose-50/20 transition-all group">
                            <td class="px-8 py-5 text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{{ new Date(d.created_at).toLocaleString('id-ID') }}</td>
                            <td class="px-8 py-5 font-black text-slate-900 text-sm uppercase tracking-tighter">{{ d.raw_material?.nama_bahan }}</td>
                            <td class="px-8 py-5 text-right font-black text-rose-600 text-sm tabular-nums">-{{ Number(d.qty_lost).toLocaleString('id-ID') }}</td>
                            <td class="px-8 py-5">
                                <span class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-[16px] text-[9px] font-black uppercase tracking-tight block w-fit truncate max-w-[200px]" :title="d.reason">
                                    {{ d.reason }}
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <a v-if="d.evidence_path" :href="'/storage/' + d.evidence_path" target="_blank" class="inline-flex items-center gap-2 text-rose-600 hover:text-rose-700 font-black text-[9px] uppercase tracking-widest bg-rose-50 px-2 py-1 rounded border border-rose-100 transition-all active:scale-95 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Open Proof
                                </a>
                                <span v-else class="text-slate-300 font-black text-[9px] uppercase tracking-widest">N/A</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-7 h-7 bg-slate-100 rounded-full flex items-center justify-center text-[9px] font-black text-slate-500 border border-[#E8EEF6] uppercase">{{ d.user?.name?.charAt(0) }}</div>
                                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-tighter">{{ d.user?.name }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="disposals.links?.length > 3" class="flex justify-center gap-1.5 p-6 border-t border-[#E8EEF6]">
                <template v-for="l in disposals.links" :key="l.label">
                    <button 
                        v-if="l.url" 
                        @click="router.get(l.url)" 
                        :class="l.active ? 'bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-emerald-500 hover:text-emerald-500'" 
                        class="w-8 h-8 flex items-center justify-center rounded-[16px] text-[10px] font-black transition-all border uppercase" 
                        v-html="l.label"
                    ></button>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal Form Pemusnahan -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
            <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-md transform transition-all border border-[#E8EEF6] overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-slate-900 font-black text-sm uppercase tracking-tight leading-none">Protokol Pemusnahan Stok</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-1.5">Audit pengeluaran barang non-operasional.</p>
                    </div>
                </div>
                
                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Target Pemusnahan <span class="text-rose-500">*</span></label>
                        <select v-model="form.raw_material_id" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-rose-500 outline-none transition-all uppercase tracking-tight" required>
                            <option value="">Pilih Bahan Baku...</option>
                            <option v-for="rm in rawMaterials" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }} (Stok: {{ Number(rm.stok_total).toLocaleString() }})</option>
                        </select>
                        <p v-if="form.errors.raw_material_id" class="text-rose-500 text-[9px] font-black uppercase mt-1 ml-1">{{ form.errors.raw_material_id }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Volume / Kuantitas <span class="text-rose-500">*</span></label>
                        <input v-model="form.qty_lost" type="number" step="0.01" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-black text-slate-900 focus:bg-white focus:border-rose-500 outline-none transition-all tabular-nums" required placeholder="0.00" />
                        <p v-if="form.errors.qty_lost" class="text-rose-500 text-[9px] font-black uppercase mt-1 ml-1">{{ form.errors.qty_lost }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Dokumentasi Kerusakan <span class="text-rose-500">*</span></label>
                        <div class="relative group">
                            <input type="file" @change="handleFile" accept="image/*" class="w-full text-[10px] text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-[16px] file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-slate-900 file:text-white hover:file:bg-emerald-600 transition-all cursor-pointer" required />
                        </div>
                        <p v-if="form.errors.evidence" class="text-rose-500 text-[9px] font-black uppercase mt-1 ml-1">{{ form.errors.evidence }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Rasionalisasi / Alasan <span class="text-rose-500">*</span></label>
                        <textarea v-model="form.reason" rows="3" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:border-rose-500 outline-none resize-none transition-all placeholder:text-slate-300 uppercase tracking-tight" placeholder="Jelaskan rasionalisasi pemusnahan (Kadaluarsa/Pecah/Tercemar)..." required></textarea>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showModal = false" class="flex-1 bg-white border border-[#E8EEF6] text-slate-400 py-3.5 rounded-[16px] text-[10px] font-black uppercase tracking-widest transition-all">Batal</button>
                        <button type="submit" :disabled="form.processing" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white py-3.5 rounded-[16px] text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-900/20 shadow active:scale-95 transition-all">Konfirmasi Audit</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
