<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ suppliers: Object });

const showModal = ref(false);
const editingSupplier = ref(null);

const form = useForm({ nama_pbf: '', alamat: '', telepon: '', email: '', is_active: true });

function openCreate() {
    editingSupplier.value = null;
    form.reset(); form.clearErrors();
    showModal.value = true;
}

function openEdit(supplier) {
    editingSupplier.value = supplier;
    form.nama_pbf = supplier.nama_pbf;
    form.alamat = supplier.alamat || '';
    form.telepon = supplier.telepon || '';
    form.email = supplier.email || '';
    form.is_active = supplier.is_active;
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    if (editingSupplier.value) {
        form.put(route('control.admin.suppliers.update', editingSupplier.value.id), { 
            onSuccess: () => {
                showModal.value = false;
            } 
        });
    } else {
        form.post(route('control.admin.suppliers.store'), { 
            onSuccess: () => {
                showModal.value = false;
            } 
        });
    }
}

function destroy(supplier) {
    if (confirm(`Hapus supplier "${supplier.nama_pbf}"?`)) {
        router.delete(route('control.admin.suppliers.destroy', supplier.id));
    }
}
</script>

<template>
    <Head title="Data Supplier PBF" />
    <ControlLayout>
        <template #header-title>Data Supplier PBF</template>

        <div class="space-y-6">
            <!-- COMPACT PREMIUM HERO HEADER -->
            <div class="relative p-6 bg-white rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] overflow-hidden group">
                <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50/50 rounded-full -mr-32 -mt-32 transition-transform group-hover:scale-110 duration-1000"></div>
                
                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-50 text-blue-700 text-[9px] font-bold uppercase tracking-widest rounded-full border border-blue-100">Logistik</span>
                            <span class="px-3 py-1 bg-slate-50 text-slate-500 text-[9px] font-bold uppercase tracking-widest rounded-full border border-[#E8EEF6]">Sistem Eksternal</span>
                        </div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight leading-none uppercase">Data Supplier PBF</h1>
                        <p class="text-slate-500 font-medium text-xs max-w-xl mt-1.5">Kelola direktori Pedagang Besar Farmasi (PBF) dan rekanan distributor resmi obat.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <div class="bg-white border border-[#E8EEF6] p-4 rounded-[16px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none">Total Rekanan</p>
                                <p class="text-2xl font-black text-blue-600 leading-none mt-1.5">{{ suppliers.total || suppliers.data.length }}</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center text-lg border border-blue-100 text-blue-600">🏢</div>
                        </div>
                        <button @click="openCreate" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[52px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all hover:scale-[1.02] active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Supplier
                        </button>
                    </div>
                </div>
            </div>

            <!-- SEARCH & FILTER -->
            <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-[#E8EEF6] p-4 flex flex-col md:flex-row gap-4">
                <div class="flex-1 relative group">
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" placeholder="Cari berdasarkan nama PBF atau wilayah..." class="w-full h-[52px] pl-12 pr-4 bg-slate-50 border-[#E8EEF6] rounded-[16px] text-sm font-medium focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)]" />
                </div>
            </div>

            <!-- TABLE CONTAINER -->
            <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-[#E8EEF6]">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Informasi PBF / Nama Perusahaan</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Kontak Distributor</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Alamat / Lokasi</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Aksi Kendali</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8EEF6]">
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-slate-50 border border-[#E8EEF6] rounded-[16px] flex items-center justify-center text-2xl mb-4 text-slate-300 grayscale">🏢</div>
                                    <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Data Supplier Belum Tersedia</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="s in suppliers.data" :key="s.id" class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-[12px] bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs uppercase border border-blue-100 group-hover:scale-105 transition-transform">
                                        {{ s.nama_pbf.substring(0, 2) }}
                                    </div>
                                    <div>
                                        <p class="text-slate-900 font-bold tracking-tight leading-none mb-1">{{ s.nama_pbf }}</p>
                                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Registered Partner</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1.5">
                                    <span class="flex items-center gap-2 text-xs font-bold text-slate-700 font-mono">
                                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        {{ s.telepon || '-' }}
                                    </span>
                                    <span class="flex items-center gap-2 text-[10px] font-medium text-slate-500 lowercase">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        {{ s.email || '-' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-2 max-w-[250px]">
                                    <svg class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <p class="text-xs font-medium text-slate-600 leading-relaxed">{{ s.alamat || '-' }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span :class="s.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'" class="text-[9px] px-2.5 py-1 rounded-md font-bold uppercase tracking-widest border inline-flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="s.is_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></div>
                                    {{ s.is_active ? 'Status Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEdit(s)" class="w-8 h-8 flex items-center justify-center bg-white border border-[#E8EEF6] hover:border-blue-500 text-slate-400 hover:text-blue-600 rounded-[16px] transition-colors" title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="destroy(s)" class="w-8 h-8 flex items-center justify-center bg-white border border-[#E8EEF6] hover:border-rose-500 text-slate-400 hover:text-rose-600 rounded-[16px] transition-colors" title="Hapus Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pagination Footer -->
                <div class="px-6 py-4 bg-slate-50/30 border-t border-[#E8EEF6] flex items-center justify-between">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Halaman {{ suppliers.current_page || 1 }} dari {{ suppliers.last_page || 1 }}</p>
                    <div v-if="suppliers.links?.length > 3" class="flex items-center gap-1.5">
                        <template v-for="link in suppliers.links" :key="link.label">
                            <button v-if="link.url" @click="router.get(link.url)" 
                                :class="link.active ? 'bg-blue-600 text-white shadow-[0_8px_24px_rgba(15,23,42,0.04)] border-blue-600' : 'bg-white border-[#E8EEF6] text-slate-500 hover:border-blue-500 hover:text-blue-600'" 
                                class="w-8 h-8 flex items-center justify-center rounded-[16px] text-xs font-bold transition-all border" v-html="link.label"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL SYSTEM -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showModal = false"></div>
                    
                    <Transition name="pop">
                        <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-lg overflow-hidden border border-[#E8EEF6]">
                            <!-- Modal Header -->
                            <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                                <div>
                                    <h3 class="text-slate-900 font-black text-sm uppercase tracking-tight leading-none mb-1.5">{{ editingSupplier ? 'Edit Rekanan PBF' : 'Entri PBF Baru' }}</h3>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-none">Formulir Identitas Distributor Resmi</p>
                                </div>
                                <button @click="showModal=false" class="w-8 h-8 flex items-center justify-center rounded-[16px] bg-white border border-[#E8EEF6] text-slate-400 hover:text-slate-600 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                            </div>

                            <form @submit.prevent="submit" class="p-8 space-y-5">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-1.5 pl-1">Nama Perusahaan / PBF <span class="text-rose-500">*</span></label>
                                        <input v-model="form.nama_pbf" type="text" class="w-full h-[52px] bg-slate-50 border-[#E8EEF6] rounded-[16px] px-4 text-sm font-bold text-slate-900 focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] placeholder:font-medium" placeholder="Contoh: PT. Kimia Farma Trading..." required />
                                        <p v-if="form.errors.nama_pbf" class="text-rose-500 text-[10px] font-bold mt-1 uppercase tracking-wide pl-1">{{ form.errors.nama_pbf }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-1.5 pl-1">Alamat Kantor / Gudang PBF</label>
                                        <textarea v-model="form.alamat" rows="2" class="w-full bg-slate-50 border-[#E8EEF6] rounded-[16px] p-4 text-sm font-bold text-slate-900 focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 outline-none transition-all resize-none shadow-[0_8px_24px_rgba(15,23,42,0.04)] placeholder:font-medium" placeholder="Masukkan alamat lengkap operasional..."></textarea>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-1.5 pl-1">No. Telepon / WA</label>
                                            <input v-model="form.telepon" type="tel" class="w-full h-[52px] bg-slate-50 border-[#E8EEF6] rounded-[16px] px-4 text-sm font-bold text-slate-900 focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] placeholder:font-medium" placeholder="021-XXXXXXXX" />
                                        </div>
                                        <div>
                                            <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-1.5 pl-1">Email Korespondensi</label>
                                            <input v-model="form.email" type="email" class="w-full h-[52px] bg-slate-50 border-[#E8EEF6] rounded-[16px] px-4 text-sm font-bold text-slate-900 focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 outline-none transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] placeholder:font-medium" placeholder="pbf@distributor.com" />
                                        </div>
                                    </div>
                                    
                                    <div class="pt-2">
                                        <label class="flex items-center gap-4 cursor-pointer group bg-slate-50 p-4 rounded-[16px] border border-[#E8EEF6] hover:bg-slate-100 transition-all">
                                            <div class="relative flex items-center">
                                                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                                                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600 shadow-inner"></div>
                                            </div>
                                            <div>
                                                <span class="block text-slate-900 text-xs font-bold uppercase tracking-widest">Status Kemitraan</span>
                                                <span class="block text-[10px] text-slate-500 font-medium mt-0.5">Aktifkan untuk pemesanan stok baru</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="flex gap-4 pt-4 mt-2">
                                    <button type="button" @click="showModal = false" class="flex-1 bg-white border border-[#E8EEF6] text-slate-500 hover:bg-slate-50 h-[52px] rounded-[16px] text-[11px] font-bold uppercase tracking-widest transition-all">Batal</button>
                                    <button type="submit" :disabled="form.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white h-[52px] rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 disabled:opacity-50 transition-all">
                                        {{ form.processing ? 'Memproses...' : 'Simpan Data PBF' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </ControlLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.pop-enter-active { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop-leave-active { transition: all 0.3s ease-in; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
