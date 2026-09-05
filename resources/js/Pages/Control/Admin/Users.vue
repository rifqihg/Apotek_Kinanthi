<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ users: Object });
const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name:'', email:'', role:'kasir', password:'', password_confirmation:'' });

const roleClass = (r) => ({
    admin: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    apoteker: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    kasir: 'bg-blue-50 text-blue-700 border-blue-200'
}[r] || 'bg-slate-50 text-slate-700 border-[#E8EEF6]');

function openCreate() { editing.value=null; form.reset(); form.clearErrors(); showModal.value=true; }
function openEdit(u) { editing.value=u; form.name=u.name; form.email=u.email; form.role=u.role; form.password=''; form.password_confirmation=''; form.clearErrors(); showModal.value=true; }
function submit() {
    if (editing.value) form.put(route('control.admin.users.update',editing.value.id),{onSuccess:()=>showModal.value=false});
    else form.post(route('control.admin.users.store'),{onSuccess:()=>showModal.value=false});
}
function destroy(u) { if(confirm(`Hapus user "${u.name}"?`)) router.delete(route('control.admin.users.destroy',u.id)); }
</script>

<template>
<Head title="Manajemen User" />
<ControlLayout>
    <template #header-title>Manajemen User & Akun</template>
    
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Manajemen User</h2>
                <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Kelola akun pengguna dan hak akses (Role)</p>
            </div>
            <button @click="openCreate" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white h-[52px] px-6 rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Tambah User Baru
            </button>
        </div>

        <!-- Table -->
        <div class="w-full overflow-x-auto rounded-[24px] border border-[#E8EEF6] bg-white shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50/50 border-b border-[#E8EEF6]">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Nama User</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Email</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Role / Akses</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8EEF6]">
                    <tr v-if="users.data.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-bold text-[11px] uppercase tracking-widest">Belum ada data user.</td>
                    </tr>
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-[12px] bg-slate-50 text-slate-400 border border-[#E8EEF6] flex items-center justify-center font-black text-sm uppercase shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                                    {{ u.name.substring(0, 1) }}
                                </div>
                                <span class="text-slate-900 font-bold text-sm">{{ u.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-500 font-medium text-sm">{{ u.email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span :class="roleClass(u.role)" class="text-[9px] px-3 py-1.5 rounded-[12px] font-bold uppercase tracking-widest border block w-fit mx-auto">
                                {{ u.role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="openEdit(u)" class="h-[52px] px-6 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-600 text-[10px] font-bold uppercase tracking-widest rounded-[16px] transition-all border border-blue-200 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" title="Edit Data">Edit</button>
                                <button @click="destroy(u)" class="h-[52px] px-6 bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 text-[10px] font-bold uppercase tracking-widest rounded-[16px] transition-all border border-rose-200 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" title="Hapus User">Hapus</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="users.links?.length > 3" class="px-6 py-4 border-t border-[#E8EEF6] flex items-center justify-center gap-1.5 bg-slate-50/30">
                <template v-for="l in users.links" :key="l.label">
                    <button v-if="l.url" @click="router.get(l.url)" :class="l.active ? 'bg-blue-600 text-white font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)]' : 'bg-white border-[#E8EEF6] text-slate-500 hover:bg-blue-500 hover:text-blue-600'" class="w-8 h-8 flex items-center justify-center rounded-[16px] text-xs font-bold transition-all border uppercase" v-html="l.label"></button>
                    <span v-else class="w-8 h-8 flex items-center justify-center text-slate-300 text-xs font-bold uppercase" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showModal=false"></div>
                <div class="relative bg-white rounded-[24px] shadow-2xl w-full max-w-md transform transition-all border border-[#E8EEF6] overflow-hidden flex flex-col">
                    <div class="px-8 py-6 border-b border-[#E8EEF6] bg-slate-50/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight">{{ editing ? 'Edit Data User' : 'Tambah User Baru' }}</h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter mt-1.5">Informasi akun pengguna.</p>
                        </div>
                        <button @click="showModal=false" class="w-8 h-8 flex items-center justify-center rounded-[16px] bg-white border border-[#E8EEF6] text-slate-400 hover:text-slate-600 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                    </div>
                    
                    <form @submit.prevent="submit" class="flex flex-col">
                        <div class="p-8 space-y-5">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <input v-model="form.name" type="text" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" required />
                                <p v-if="form.errors.name" class="text-rose-500 text-[10px] font-bold mt-1.5 pl-1">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Alamat Email <span class="text-rose-500">*</span></label>
                                <input v-model="form.email" type="email" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" required />
                                <p v-if="form.errors.email" class="text-rose-500 text-[10px] font-bold mt-1.5 pl-1">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Hak Akses (Role) <span class="text-rose-500">*</span></label>
                                <select v-model="form.role" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" required>
                                    <option value="admin">Admin (Akses Penuh)</option>
                                    <option value="apoteker">Apoteker (Compounding)</option>
                                    <option value="kasir">Kasir (Transaksi)</option>
                                </select>
                            </div>
                            <div class="border-t border-[#E8EEF6] pt-5 mt-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Password <span v-if="!editing" class="text-rose-500">*</span> <span v-else class="text-slate-400 font-medium">(Kosongkan jika tidak ingin diubah)</span></label>
                                <input v-model="form.password" type="password" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" :required="!editing" />
                                <p v-if="form.errors.password" class="text-rose-500 text-[10px] font-bold mt-1.5 pl-1">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1">Konfirmasi Password <span v-if="!editing" class="text-rose-500">*</span></label>
                                <input v-model="form.password_confirmation" type="password" class="w-full h-[52px] px-4 border-[#E8EEF6] bg-slate-50 rounded-[16px] focus:bg-white focus:ring focus:ring-blue-200/50 focus:border-blue-500 text-sm font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-all outline-none" :required="!editing" />
                            </div>
                        </div>
                        
                        <div class="px-8 py-6 bg-slate-50/50 border-t border-[#E8EEF6] flex justify-end gap-3">
                            <button type="button" @click="showModal=false" class="px-6 h-[52px] text-[11px] font-bold text-slate-500 bg-white border border-[#E8EEF6] hover:bg-slate-50 rounded-[16px] uppercase tracking-widest transition-colors shadow-[0_8px_24px_rgba(15,23,42,0.04)]">Batal</button>
                            <button type="submit" :disabled="form.processing" class="px-6 h-[52px] bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold rounded-[16px] uppercase tracking-widest transition-all shadow-[0_8px_24px_rgba(15,23,42,0.04)] disabled:opacity-50 active:scale-95">Simpan Data</button>
                        </div>
                    </form>
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
