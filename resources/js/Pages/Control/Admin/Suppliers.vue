<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({ suppliers: Object });

const stats = computed(() => {
    const list = props.suppliers?.data || [];
    const total = props.suppliers?.total ?? list.length;
    const active = list.filter(s => s.is_active).length;
    const inactive = list.filter(s => !s.is_active).length;
    return { total, active, inactive };
});

const search = ref('');
const filteredSuppliers = computed(() => {
    if (!search.value) return props.suppliers?.data || [];
    const q = search.value.toLowerCase();
    return (props.suppliers?.data || []).filter(s =>
        s.nama_pbf?.toLowerCase().includes(q) || s.alamat?.toLowerCase().includes(q) || s.email?.toLowerCase().includes(q)
    );
});

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

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Manajemen Supplier & PBF
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Direktori Pedagang Besar Farmasi resmi, kontak penanggung jawab sales, dan tata kelola kemitraan distribusi apotek.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- PBF Facility (behind left) -->
                            <rect x="4" y="8" width="28" height="43" rx="3" stroke="#0284C7" stroke-width="2" fill="#FFFFFF" />
                            <rect x="11" y="4" width="14" height="4" rx="1.5" stroke="#0284C7" stroke-width="1.6" fill="#E0F2FE" />
                            <rect x="8" y="13" width="7" height="6" rx="1.2" fill="#BAE6FD" />
                            <rect x="21" y="13" width="7" height="6" rx="1.2" fill="#BAE6FD" />
                            <rect x="8" y="23" width="7" height="6" rx="1.2" fill="#BAE6FD" />
                            <rect x="21" y="23" width="7" height="6" rx="1.2" fill="#BAE6FD" />
                            <path d="M14 51V39h8v12" stroke="#0284C7" stroke-width="1.6" fill="#F0F9FF" />
                            <!-- Logistics Van (in front right) -->
                            <g transform="translate(20, 26)">
                                <rect x="0" y="0" width="23" height="19" rx="2.5" stroke="#0284C7" stroke-width="2" fill="#FFFFFF" />
                                <circle cx="11.5" cy="9.5" r="4" fill="#E0F2FE" />
                                <path d="M11.5 7v5M9 9.5h5" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M23 7h6l4 6v6h-10V7z" stroke="#0284C7" stroke-width="2" fill="#F0F9FF" />
                                <path d="M25 9h4l2.5 4H25v-4z" fill="#BAE6FD" />
                                <circle cx="7" cy="20" r="3.5" stroke="#0284C7" stroke-width="1.8" fill="#FFFFFF" />
                                <circle cx="7" cy="20" r="1.3" fill="#0284C7" />
                                <circle cx="27" cy="20" r="3.5" stroke="#0284C7" stroke-width="1.8" fill="#FFFFFF" />
                                <circle cx="27" cy="20" r="1.3" fill="#0284C7" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Distributor Aktif -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            DISTRIBUTOR AKTIF
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.active }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Mitra siap transaksi
                    </p>
                </div>

                <!-- Card 2: Perlu Tinjauan -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            PERLU TINJAUAN
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.inactive > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.inactive > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.inactive }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.inactive > 0 ? 'Mitra non-aktif / arsip' : 'Semua mitra aktif' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- SEARCH & ACTION BAR -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="relative w-full sm:flex-1">
                <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" type="text" placeholder="Cari berdasarkan nama PBF atau wilayah..." class="w-full h-10 pl-10 pr-3.5 bg-background border border-input rounded-md text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" />
            </div>
            <button @click="openCreate" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700 h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Supplier
            </button>
        </div>

            <!-- TABLE CONTAINER -->
            <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-muted/50 border-b border-border">
                            <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Informasi PBF / Nama Perusahaan</th>
                            <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Kontak Distributor</th>
                            <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Alamat / Lokasi</th>
                            <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status</th>
                            <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Aksi Kendali</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="filteredSuppliers.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-12 h-12 bg-muted border border-border rounded-md flex items-center justify-center text-xl mb-3 text-muted-foreground">🏢</div>
                                    <p class="text-muted-foreground font-medium text-sm">Data Supplier Belum Tersedia</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="s in filteredSuppliers" :key="s.id" class="hover:bg-muted/40 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-md bg-muted text-foreground flex items-center justify-center font-bold text-xs uppercase border border-border">
                                        {{ s.nama_pbf.substring(0, 2) }}
                                    </div>
                                    <div>
                                        <p class="text-foreground font-semibold tracking-tight text-sm mb-0.5">{{ s.nama_pbf }}</p>
                                        <p class="text-xs text-muted-foreground">Registered Partner</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex flex-col gap-1">
                                    <span class="flex items-center gap-1.5 text-xs font-semibold text-foreground tracking-wide">
                                        <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        {{ s.telepon || '-' }}
                                    </span>
                                    <span class="flex items-center gap-1.5 text-xs text-muted-foreground lowercase">
                                        <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        {{ s.email || '-' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-start gap-1.5 max-w-[260px]">
                                    <svg class="w-3.5 h-3.5 text-muted-foreground mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <p class="text-xs text-muted-foreground leading-relaxed">{{ s.alamat || '-' }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-center">
                                <span :class="s.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-destructive/10 text-destructive border-destructive/20'" class="text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider border inline-flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="s.is_active ? 'bg-emerald-500' : 'bg-destructive'"></span>
                                    {{ s.is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openEdit(s)" class="w-8 h-8 flex items-center justify-center bg-background border border-input hover:bg-accent text-muted-foreground hover:text-foreground rounded-md transition-colors" title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="destroy(s)" class="w-8 h-8 flex items-center justify-center bg-background border border-input hover:bg-destructive/10 text-muted-foreground hover:text-destructive rounded-md transition-colors" title="Hapus Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pagination Footer -->
                <div class="px-5 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                    <p class="text-xs font-medium text-muted-foreground">Halaman {{ suppliers.current_page || 1 }} dari {{ suppliers.last_page || 1 }}</p>
                    <div v-if="suppliers.links?.length > 3" class="flex items-center gap-1">
                        <template v-for="link in suppliers.links" :key="link.label">
                            <button v-if="link.url" @click="router.get(link.url)" 
                                :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-foreground hover:bg-accent'" 
                                class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL SYSTEM -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showModal = false"></div>
                    
                    <Transition name="pop">
                        <div class="relative bg-card rounded-lg shadow-lg w-full max-w-lg overflow-hidden border border-border">
                            <!-- Modal Header -->
                            <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                                <div>
                                    <h3 class="text-foreground font-semibold text-base tracking-tight leading-none mb-1">{{ editingSupplier ? 'Edit Rekanan PBF' : 'Entri PBF Baru' }}</h3>
                                    <p class="text-xs text-muted-foreground">Formulir Identitas Distributor Resmi</p>
                                </div>
                                <button @click="showModal=false" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <form @submit.prevent="submit" class="p-6 space-y-4">
                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Nama Perusahaan / PBF <span class="text-destructive">*</span></label>
                                        <input v-model="form.nama_pbf" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="Contoh: PT. Kimia Farma Trading..." required />
                                        <p v-if="form.errors.nama_pbf" class="text-destructive text-xs font-medium">{{ form.errors.nama_pbf }}</p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Alamat Kantor / Gudang PBF</label>
                                        <textarea v-model="form.alamat" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-sm font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors resize-none" placeholder="Masukkan alamat lengkap operasional..."></textarea>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">No. Telepon / WA</label>
                                            <input v-model="form.telepon" type="tel" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="021-XXXXXXXX" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Email Korespondensi</label>
                                            <input v-model="form.email" type="email" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" placeholder="pbf@distributor.com" />
                                        </div>
                                    </div>
                                    
                                    <div class="pt-1">
                                        <label class="flex items-center gap-3 cursor-pointer group bg-muted/40 p-3 rounded-md border border-border hover:bg-muted/70 transition-colors">
                                            <div class="relative flex items-center">
                                                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                                                <div class="w-9 h-5 bg-input peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                            </div>
                                            <div>
                                                <span class="block text-foreground text-xs font-semibold uppercase tracking-wider">Status Kemitraan</span>
                                                <span class="block text-xs text-muted-foreground mt-0.5">Aktifkan untuk pemesanan stok baru</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                                    <button type="button" @click="showModal = false" class="h-9 px-4 rounded-md border border-input bg-background text-foreground text-sm font-medium hover:bg-accent transition-colors">Batal</button>
                                    <button type="submit" :disabled="form.processing" class="h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                                        {{ form.processing ? 'Memproses...' : 'Simpan Data PBF' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.pop-enter-active { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pop-leave-active { transition: all 0.3s ease-in; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
