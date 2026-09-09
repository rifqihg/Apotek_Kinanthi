<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: ControlLayout });

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

<div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-foreground tracking-tight">Manajemen User</h2>
                <p class="text-muted-foreground text-sm mt-1">Kelola akun pengguna dan hak akses (Role)</p>
            </div>
            <button @click="openCreate" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-9 px-4 rounded-md text-sm font-medium shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah User Baru
            </button>
        </div>

        <!-- Table -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm text-left">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama User</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Email</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Role / Akses</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="users.data.length === 0">
                        <td colspan="4" class="px-5 py-12 text-center text-muted-foreground font-medium text-sm">Belum ada data user.</td>
                    </tr>
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-muted/40 transition-colors">
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-md bg-muted text-foreground border border-border flex items-center justify-center font-bold text-sm uppercase">
                                    {{ u.name.substring(0, 1) }}
                                </div>
                                <span class="text-foreground font-medium text-sm">{{ u.name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted-foreground text-sm">{{ u.email }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-center">
                            <span :class="roleClass(u.role)" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-block">
                                {{ u.role }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button @click="openEdit(u)" class="h-8 px-3 bg-background hover:bg-accent text-foreground text-xs font-medium rounded-md border border-input transition-colors" title="Edit Data">Edit</button>
                                <button @click="destroy(u)" class="h-8 px-3 bg-background hover:bg-destructive/10 text-muted-foreground hover:text-destructive text-xs font-medium rounded-md border border-input transition-colors" title="Hapus User">Hapus</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="users.links?.length > 3" class="px-5 py-3 border-t border-border flex items-center justify-center gap-1 bg-muted/30">
                <template v-for="l in users.links" :key="l.label">
                    <button v-if="l.url" @click="router.get(l.url)" :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-foreground hover:bg-accent'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="l.label"></button>
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showModal=false"></div>
                <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md transform transition-all border border-border overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">{{ editing ? 'Edit Data User' : 'Tambah User Baru' }}</h3>
                            <p class="text-xs text-muted-foreground">Informasi akun pengguna.</p>
                        </div>
                        <button @click="showModal=false" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <form @submit.prevent="submit" class="flex flex-col">
                        <div class="p-6 space-y-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Nama Lengkap <span class="text-destructive">*</span></label>
                                <input v-model="form.name" type="text" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                <p v-if="form.errors.name" class="text-destructive text-xs font-medium mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Alamat Email <span class="text-destructive">*</span></label>
                                <input v-model="form.email" type="email" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required />
                                <p v-if="form.errors.email" class="text-destructive text-xs font-medium mt-1">{{ form.errors.email }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Hak Akses (Role) <span class="text-destructive">*</span></label>
                                <select v-model="form.role" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required>
                                    <option value="admin">Admin (Akses Penuh)</option>
                                    <option value="apoteker">Apoteker (Compounding)</option>
                                    <option value="kasir">Kasir (Transaksi)</option>
                                </select>
                            </div>
                            <div class="border-t border-border pt-4 space-y-1.5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Password <span v-if="!editing" class="text-destructive">*</span> <span v-else class="text-muted-foreground font-normal text-xs lowercase">(kosongkan jika tidak ingin diubah)</span></label>
                                <input v-model="form.password" type="password" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" :required="!editing" />
                                <p v-if="form.errors.password" class="text-destructive text-xs font-medium mt-1">{{ form.errors.password }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Konfirmasi Password <span v-if="!editing" class="text-destructive">*</span></label>
                                <input v-model="form.password_confirmation" type="password" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" :required="!editing" />
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-muted/30 border-t border-border flex justify-end gap-3">
                            <button type="button" @click="showModal=false" class="h-9 px-4 text-sm font-medium text-foreground bg-background border border-input hover:bg-accent rounded-md transition-colors">Batal</button>
                            <button type="submit" :disabled="form.processing" class="h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50">Simpan Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
