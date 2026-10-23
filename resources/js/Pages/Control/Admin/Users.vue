<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ users: Object });
const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name:'', email:'', role:'kasir', password:'', password_confirmation:'' });

const search = ref('');
const activeRoleTab = ref('all');

const stats = computed(() => {
    const list = props.users?.data || [];
    const total = props.users?.total ?? list.length;
    const apoteker = list.filter(u => u.role === 'apoteker').length;
    const kasir = list.filter(u => u.role === 'kasir').length;
    const admin = list.filter(u => u.role === 'admin').length;
    return { total, apoteker, kasir, admin };
});

const filteredUsers = computed(() => {
    let list = props.users?.data || [];
    if (activeRoleTab.value !== 'all') {
        list = list.filter(u => u.role === activeRoleTab.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(u => 
            u.name?.toLowerCase().includes(q) || 
            u.email?.toLowerCase().includes(q) ||
            u.role?.toLowerCase().includes(q)
        );
    }
    return list;
});

const clearSearch = () => {
    search.value = '';
};

const roleClass = (r) => ({
    admin: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    apoteker: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    kasir: 'bg-blue-50 text-blue-700 border-blue-200'
}[r] || 'bg-slate-50 text-slate-700 border-[#E8EEF6]');

function openCreate() { editing.value=null; form.reset(); form.clearErrors(); showModal.value=true; }
function openEdit(u) { editing.value=u; form.name=u.name; form.email=u.email; form.role=u.role; form.password=''; form.password_confirmation=''; form.clearErrors(); showModal.value=true; }
function submit() {
    if (editing.value) form.put(route('control.admin.users.update', editing.value.id), { preserveScroll: true, onSuccess: () => showModal.value = false });
    else form.post(route('control.admin.users.store'), { preserveScroll: true, onSuccess: () => showModal.value = false });
}
function destroy(u) { if (confirm(`Hapus user "${u.name}"?`)) router.delete(route('control.admin.users.destroy', u.id), { preserveScroll: true }); }
</script>

<template>
<Head title="Manajemen User" />

<div class="space-y-8">
    <!-- BENTO COCKPIT HEADER -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
        <!-- SISI KIRI: KONSOL OPERASIONAL & ACTION BAR (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-center gap-2.5">
                <!-- Baris 1: Filter Tabs (Pills) & Tombol Tambah Staff (Desktop) -->
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        <button
                            type="button"
                            @click="activeRoleTab = 'all'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeRoleTab === 'all' 
                                ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span>Semua</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeRoleTab === 'all' ? 'bg-white/20 dark:bg-slate-900/20 text-white dark:text-slate-900' : 'bg-slate-200 dark:bg-card text-slate-700 dark:text-foreground'"
                            >{{ stats.total }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeRoleTab = 'apoteker'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeRoleTab === 'apoteker' 
                                ? 'bg-emerald-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="activeRoleTab === 'apoteker' ? 'bg-white' : 'bg-emerald-500'"></span>
                            <span>Apoteker</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeRoleTab === 'apoteker' ? 'bg-white/20 text-white' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
                            >{{ stats.apoteker }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeRoleTab = 'kasir'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeRoleTab === 'kasir' 
                                ? 'bg-blue-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="activeRoleTab === 'kasir' ? 'bg-white' : 'bg-blue-500'"></span>
                            <span>Kasir</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeRoleTab === 'kasir' ? 'bg-white/20 text-white' : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300'"
                            >{{ stats.kasir }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeRoleTab = 'admin'"
                            class="h-9 px-2 sm:px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 flex-1 sm:flex-none cursor-pointer"
                            :class="activeRoleTab === 'admin' 
                                ? 'bg-indigo-600 text-white shadow-xs' 
                                : 'bg-slate-100 dark:bg-muted text-slate-600 dark:text-muted-foreground hover:bg-slate-200/70 dark:hover:bg-muted/80'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="activeRoleTab === 'admin' ? 'bg-white' : 'bg-indigo-500'"></span>
                            <span>Admin</span>
                            <span 
                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                :class="activeRoleTab === 'admin' ? 'bg-white/20 text-white' : 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300'"
                            >{{ stats.admin }}</span>
                        </button>
                    </div>

                    <button 
                        @click="openCreate" 
                        class="hidden sm:inline-flex h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                        title="Tambah Akun Staff Baru"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Tambah Staff</span>
                    </button>
                </div>

                <!-- Baris 2: Search Input & Tombol Tambah Staff (Mobile) -->
                <div class="flex items-center gap-2 w-full">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari nama staf, email pengguna, atau role akun..." 
                            class="w-full h-9 pl-9 pr-9 bg-slate-50 dark:bg-background border border-slate-200 dark:border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" 
                        />
                        <button 
                            v-if="search" 
                            type="button" 
                            @click="clearSearch" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <button 
                        @click="openCreate" 
                        class="sm:hidden h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs inline-flex items-center gap-1.5 whitespace-nowrap shrink-0 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Tambah</span>
                    </button>
                </div>
        </div>

        <!-- SISI KANAN: 2 METRIK KARTU BERDAMPINGAN (lg:col-span-4) -->
        <div class="lg:col-span-4 grid grid-cols-2 gap-2.5">
            <!-- Card 1: Tim Apoteker -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        TIM APOTEKER
                    </span>
                    <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                        {{ stats.apoteker }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Staff</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Otoritas resep & racikan
                </p>
            </div>

            <!-- Card 2: Operator Kasir -->
            <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[9.5px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                        OPERATOR KASIR
                    </span>
                    <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-muted flex items-center justify-center text-slate-600 dark:text-muted-foreground shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <div class="my-1 truncate">
                    <span class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-none">
                        {{ stats.kasir }}<span class="text-xs sm:text-sm font-normal text-muted-foreground ml-1">Staff</span>
                    </span>
                </div>
                <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground truncate">
                    Layanan kasir & transaksi
                </p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <table class="w-full text-sm text-left min-w-[650px]">
            <thead class="bg-muted/50 border-b border-border">
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[190px] whitespace-nowrap">Nama User</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[200px] whitespace-nowrap">Email</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[130px] whitespace-nowrap">Role / Akses</th>
                    <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center min-w-[130px] whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-if="filteredUsers.length === 0">
                    <td colspan="4" class="px-5 py-12 text-center text-muted-foreground font-medium text-sm">Belum ada data user yang sesuai.</td>
                </tr>
                <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-muted/40 transition-colors">
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
                    <Link v-if="l.url" :href="l.url" preserve-scroll :class="l.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="l.label" />
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
                        <div class="p-6 space-y-5">
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
