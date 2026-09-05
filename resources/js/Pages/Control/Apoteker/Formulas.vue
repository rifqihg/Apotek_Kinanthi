<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineProps({ formulas: Object });
const showModal = ref(false);
const form = useForm({ nama_resep:'', kategori_sediaan:'', instruksi_racik:'' });

function submit() { 
    const routeName = route().current().startsWith('control.admin') 
        ? 'control.admin.formulas.store' 
        : 'control.apoteker.formulas.store';
    form.post(route(routeName), { onSuccess: () => { showModal.value = false; form.reset(); } }); 
}
</script>

<template>
    <Head title="Formula Obat" />
    <ControlLayout>
        <template #header-title>Pustaka Resep & Formula</template>
        
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div>
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Pustaka Resep & Formula Obat</h2>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Kelola panduan instruksi racikan standar kefarmasian</p>
                </div>
                <button @click="showModal=true;form.reset();form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[52px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Formula Baru
                </button>
            </div>

            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-if="!formulas?.data?.length" class="col-span-full py-16 flex flex-col items-center justify-center bg-white border border-[#E8EEF6] rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] text-center">
                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-[16px] flex items-center justify-center mb-4 border border-[#E8EEF6]">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <p class="text-slate-900 font-black uppercase text-xs tracking-tight">Pustaka Formula Kosong</p>
                    <p class="text-slate-400 text-[11px] mt-2 max-w-sm font-bold uppercase tracking-tighter leading-tight px-8">Klik tombol di atas untuk mulai membangun basis data instruksi racikan standar.</p>
                </div>

                <div v-for="f in (formulas?.data || [])" :key="f.id" class="bg-white border border-[#E8EEF6] rounded-[24px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] hover:shadow-xl hover:border-blue-200 transition-all flex flex-col group cursor-pointer relative overflow-hidden active:scale-95">
                    <div class="absolute -right-3 -top-3 w-16 h-16 bg-blue-50/50 rounded-full scale-0 group-hover:scale-100 transition-transform duration-500 opacity-50"></div>
                    
                    <div class="flex items-start justify-between mb-4 relative z-10">
                        <div class="w-10 h-10 rounded-[12px] bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <span class="inline-flex items-center bg-blue-50 text-blue-700 border border-blue-100 text-[9px] px-2.5 py-1 rounded-md font-bold uppercase tracking-widest">
                            {{ f.kategori_sediaan || 'Kustom' }}
                        </span>
                    </div>
                    <h3 class="text-slate-900 font-bold text-sm uppercase tracking-tight group-hover:text-blue-700 transition-colors leading-tight mb-3 relative z-10">{{ f.nama_resep }}</h3>
                    <p class="text-slate-500 text-[11px] font-medium uppercase tracking-tighter leading-relaxed line-clamp-3 flex-1 relative z-10">{{ f.instruksi_racik || 'Tidak ada instruksi khusus pemrosesan.' }}</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="formulas.links?.length > 3" class="flex justify-center gap-1.5 pt-4">
                <template v-for="l in formulas.links" :key="l.label">
                    <button 
                        v-if="l.url" 
                        @click="router.get(l.url)" 
                        :class="l.active ? 'bg-blue-600 text-white border-blue-600 shadow-[0_8px_24px_rgba(15,23,42,0.04)]' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-blue-500 hover:text-blue-600'" 
                        class="w-10 h-10 flex items-center justify-center rounded-[12px] text-xs font-bold transition-all border uppercase" 
                        v-html="l.label"
                    ></button>
                    <span v-else class="w-10 h-10 flex items-center justify-center text-slate-300 text-xs font-bold uppercase" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </ControlLayout>

    <!-- Modal Form -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-0">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showModal=false"></div>
            <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-md transform transition-all overflow-hidden border border-[#E8EEF6]">
                <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-slate-900 font-black text-sm uppercase tracking-tight leading-none">Registrasi Formula Baru</h3>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter mt-1.5">Standarisasi alur kerja compounding.</p>
                    </div>
                </div>
                
                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest pl-1">Nama Resep / Sediaan <span class="text-rose-500">*</span></label>
                        <input v-model="form.nama_resep" type="text" class="w-full h-[52px] bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 text-sm font-bold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200/50 outline-none transition-all placeholder:text-slate-400 uppercase tracking-tight" placeholder="Nama racikan standar..." required />
                        <p v-if="form.errors.nama_resep" class="text-rose-500 text-[10px] font-bold uppercase mt-1 pl-1">{{ form.errors.nama_resep }}</p>
                    </div>
                    
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest pl-1">Kategori Sediaan <span class="text-rose-500">*</span></label>
                        <select v-model="form.kategori_sediaan" class="w-full h-[52px] bg-slate-50 border border-[#E8EEF6] rounded-[16px] px-4 text-sm font-bold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200/50 outline-none transition-all uppercase tracking-tight" required>
                            <option value="" disabled>Pilih Kategori...</option>
                            <option value="Larutan Air/Sirup">Larutan Air/Sirup (BUD 14 Hari)</option>
                            <option value="Sediaan Padat/Puyer/Kapsul">Sediaan Padat/Puyer/Kapsul (BUD 30 Hari)</option>
                            <option value="Sediaan Topikal/Lainnya">Lainnya / Topikal</option>
                        </select>
                        <p v-if="form.errors.kategori_sediaan" class="text-rose-500 text-[10px] font-bold uppercase mt-1 pl-1">{{ form.errors.kategori_sediaan }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest pl-1">Instruksi Racik</label>
                        <textarea v-model="form.instruksi_racik" rows="4" class="w-full bg-slate-50 border border-[#E8EEF6] rounded-[16px] p-4 text-sm font-bold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200/50 outline-none resize-none transition-all placeholder:text-slate-400 uppercase tracking-tight leading-relaxed" placeholder="Catatan cara pembuatan atau peringatan khusus..."></textarea>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showModal=false" class="flex-1 bg-white border border-[#E8EEF6] text-slate-500 hover:bg-slate-50 h-[52px] rounded-[16px] text-[11px] font-bold uppercase tracking-widest transition-all">Batal</button>
                        <button type="submit" :disabled="form.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white h-[52px] rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all disabled:opacity-50">Simpan Formula</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
