<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

defineOptions({ layout: ControlLayout });

const props = defineProps({ 
    suppliers: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const isSearching = ref(false);

watch(() => props.filters?.search, (newSearch) => {
    if (newSearch !== undefined && newSearch !== search.value) {
        search.value = newSearch || '';
    }
});

const handleLiveSearch = useDebounceFn((val) => {
    isSearching.value = true;
    router.get(route('control.admin.suppliers'), {
        search: val || undefined,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        },
    });
}, 300);

watch(search, (newVal) => {
    handleLiveSearch(newVal);
});

const clearSearch = () => {
    search.value = '';
};

const stats = computed(() => {
    const list = props.suppliers?.data || [];
    const total = props.suppliers?.total ?? list.length;
    const active = list.filter(s => s.is_active).length;
    const inactive = list.filter(s => !s.is_active).length;
    return { total, active, inactive };
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
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            } 
        });
    } else {
        form.post(route('control.admin.suppliers.store'), { 
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            } 
        });
    }
}

function destroy(supplier) {
    if (confirm(`Hapus supplier "${supplier.nama_pbf}"?`)) {
        router.delete(route('control.admin.suppliers.destroy', supplier.id), { preserveScroll: true });
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
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 mb-1.5">
                        SUPPLY CHAIN · KEMITRAAN
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Manajemen Supplier & PBF
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Direktori Pedagang Besar Farmasi resmi, kontak penanggung jawab sales, dan tata kelola kemitraan distribusi apotek.
                    </p>
                </div>

            <!-- Right Integrated Control Panel -->
            <div class="relative z-10 flex flex-col sm:flex-row items-center gap-2.5 shrink-0 bg-white/80 dark:bg-card/80 p-2 rounded-xl border border-border shadow-2xs backdrop-blur-xs w-full sm:w-auto mt-4 sm:mt-0">
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="search" type="text" placeholder="Cari nama PBF, kontak..." class="w-full h-9 pl-9 pr-9 bg-background border border-input rounded-lg text-xs font-medium text-foreground placeholder-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-emerald-500 transition-all" />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center">
                        <svg v-if="isSearching" class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <button 
                            v-else-if="search" 
                            type="button" 
                            @click="clearSearch" 
                            class="p-1 text-muted-foreground hover:text-foreground rounded-full hover:bg-muted transition-colors cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button @click="openCreate" class="w-full sm:w-auto h-9 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-semibold rounded-lg transition-all shadow-xs flex items-center justify-center gap-1.5 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    <span>Tambah Supplier</span>
                </button>
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


            <!-- TABLE CONTAINER -->
            <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm mt-2">
                <Table class="w-full min-w-[950px]">
                    <TableHeader>
                        <TableRow class="h-10 border-b border-border/60 hover:bg-transparent">
                            <TableHead class="w-12 min-w-12 text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">NO</TableHead>
                            <TableHead class="min-w-[250px] text-left py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Informasi PBF / Nama Perusahaan</TableHead>
                            <TableHead class="min-w-[210px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Kontak Distributor</TableHead>
                            <TableHead class="min-w-[250px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Alamat / Lokasi</TableHead>
                            <TableHead class="min-w-[100px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Status</TableHead>
                            <TableHead class="min-w-[90px] text-center py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="!suppliers?.data || suppliers.data.length === 0">
                            <TableCell colspan="6" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">
                                Data Supplier Belum Tersedia.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="(s, index) in (suppliers?.data || [])" :key="s.id" class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                            <!-- NO -->
                            <TableCell class="py-2.5 px-3 text-center align-middle font-mono text-xs text-muted-foreground tabular-nums">
                                {{ ((suppliers.current_page || 1) - 1) * (suppliers.per_page || 15) + index + 1 }}
                            </TableCell>
                            <!-- NAMA PBF -->
                            <TableCell class="py-2.5 px-3 align-middle">
                                <div class="flex flex-col">
                                    <span class="text-foreground font-medium text-sm leading-tight">{{ s.nama_pbf }}</span>
                                    <span class="text-xs text-muted-foreground leading-tight mt-0.5">Distributor Farmasi</span>
                                </div>
                            </TableCell>
                            <!-- KONTAK DISTRIBUTOR -->
                            <TableCell class="py-2.5 px-3 text-center align-middle">
                                <div class="flex flex-col items-center justify-center gap-0.5">
                                    <span class="font-sans text-sm font-normal text-foreground tabular-nums leading-tight">{{ s.telepon || '-' }}</span>
                                    <span v-if="s.email" class="font-sans text-xs text-muted-foreground leading-tight truncate max-w-full" :title="s.email">{{ s.email }}</span>
                                </div>
                            </TableCell>
                            <!-- ALAMAT / LOKASI -->
                            <TableCell class="py-2.5 px-3 text-center align-middle">
                                <p class="font-sans text-xs font-normal text-foreground leading-relaxed break-words" :title="s.alamat">{{ s.alamat || '-' }}</p>
                            </TableCell>
                            <!-- STATUS -->
                            <TableCell class="py-2.5 px-3 whitespace-nowrap text-center align-middle">
                                <span :class="s.is_active ? 'text-emerald-600' : 'text-muted-foreground'" class="text-xs font-medium inline-flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="s.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'"></span>
                                    {{ s.is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </TableCell>
                            <!-- AKSI -->
                            <TableCell class="py-2.5 px-3 whitespace-nowrap text-center align-middle">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="openEdit(s)" class="h-7 w-7 flex items-center justify-center bg-background border border-input hover:bg-accent text-muted-foreground hover:text-foreground rounded-md transition-colors" title="Edit Data">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="destroy(s)" class="h-7 w-7 flex items-center justify-center bg-background border border-input hover:bg-destructive/10 text-muted-foreground hover:text-destructive rounded-md transition-colors" title="Hapus Data">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                
                <!-- Pagination Footer -->
                <div class="px-5 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                    <p class="text-xs font-medium text-muted-foreground">Halaman {{ suppliers.current_page || 1 }} dari {{ suppliers.last_page || 1 }} (Total {{ suppliers.total || 0 }} PBF)</p>
                    <div v-if="suppliers.links?.length > 3" class="flex items-center gap-1">
                        <template v-for="(link, i) in suppliers.links" :key="i">
                            <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll 
                                :class="link.active ? 'bg-primary text-primary-foreground border-primary font-semibold' : 'bg-background border-input text-foreground hover:bg-accent'" 
                                class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="link.label" />
                            <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs text-muted-foreground opacity-50 border border-input" v-html="link.label"></span>
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
