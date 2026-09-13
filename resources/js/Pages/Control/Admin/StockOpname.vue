<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref, computed } from 'vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    opnames: Object,
    medicines: Array,
    rawMaterials: Array,
});

const stats = computed(() => {
    const list = props.opnames?.data || [];
    const total = props.opnames?.total ?? list.length;
    const inProgress = list.filter(op => op.status !== 'completed').length;
    const completed = list.filter(op => op.status === 'completed').length;
    return { total, inProgress, completed };
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

    <div class="space-y-6">
        <!-- HEADER SECTION: HERO BANNER + 2 STAT CARDS -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- HERO BANNER CARD (lg:col-span-8) -->
            <div class="lg:col-span-8 p-4 sm:py-5 sm:px-6 min-h-[108px] rounded-xl border border-slate-200/80 dark:border-border shadow-2xs relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-[#ebf3fa] via-white to-[#eaf7f4] dark:from-card dark:via-card dark:to-emerald-950/20 transition-all duration-300">
                <!-- Left Text Column -->
                <div class="relative z-10 max-w-sm sm:max-w-md">
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-foreground leading-snug">
                        Manajemen Stock Opname
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-muted-foreground font-normal mt-1 leading-relaxed">
                        Pencocokan fisik berkala antara persediaan obat di rak apotek dengan saldo data tercatat pada sistem.
                    </p>
                </div>

                <!-- Right Vector Illustration Column (Micro-Housing Container & Duotone) -->
                <div class="relative z-10 flex items-center justify-center shrink-0">
                    <div class="w-20 h-20 rounded-xl bg-white/80 dark:bg-card/80 border border-slate-200/80 dark:border-border/80 shadow-2xs flex items-center justify-center p-2 backdrop-blur-xs">
                        <svg class="w-full h-full select-none" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Clipboard Audit Sheet (behind left) -->
                            <rect x="11" y="4" width="14" height="5.5" rx="2" stroke="#4F46E5" stroke-width="1.8" fill="#EEF2FF" />
                            <circle cx="18" cy="6.8" r="1.2" fill="#4F46E5" />
                            <rect x="4" y="8" width="30" height="43" rx="4" stroke="#4F46E5" stroke-width="2.2" fill="#FFFFFF" />
                            <rect x="8" y="17" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 19.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="19.5" x2="28" y2="19.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <rect x="8" y="26" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 28.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="28.5" x2="26" y2="28.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <rect x="8" y="35" width="5" height="5" rx="1.2" stroke="#4F46E5" stroke-width="1.3" fill="#EEF2FF" />
                            <path d="M9.2 37.5l1.2 1.2 2.2-2.2" stroke="#4F46E5" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="16" y1="37.5" x2="27" y2="37.5" stroke="#4F46E5" stroke-opacity="0.4" stroke-width="1.5" stroke-linecap="round" />
                            <!-- Magnifying Glass Scanner (in front right) -->
                            <g transform="translate(24, 18)">
                                <circle cx="15" cy="15" r="12" stroke="#4F46E5" stroke-width="2.2" fill="#FFFFFF" />
                                <circle cx="15" cy="15" r="9" fill="#EEF2FF" fill-opacity="0.7" />
                                <path d="M10.5 15.5l3 3 6-6" stroke="#4F46E5" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M24 24l8 8" stroke="#4F46E5" stroke-width="3.5" stroke-linecap="round" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 2 STAT CARDS (lg:col-span-4) -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-3">
                <!-- Card 1: Sesi Draft / Aktif -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            SESI AKTIF / DRAFT
                        </span>
                        <div class="w-6 h-6 rounded-full border flex items-center justify-center shrink-0" :class="stats.inProgress > 0 ? 'bg-amber-50 dark:bg-amber-950/50 border-amber-100 dark:border-amber-900 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-muted border-slate-200 dark:border-border text-slate-400 dark:text-muted-foreground'">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight leading-none" :class="stats.inProgress > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-foreground'">
                            {{ stats.inProgress }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        {{ stats.inProgress > 0 ? 'Perlu finalisasi fisik' : 'Semua sesi selesai' }}
                    </p>
                </div>

                <!-- Card 2: Audit Selesai -->
                <div class="bg-white dark:bg-card border border-slate-200/80 dark:border-border rounded-xl p-3 sm:p-3.5 shadow-2xs flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider text-slate-500 dark:text-muted-foreground truncate">
                            AUDIT SELESAI
                        </span>
                        <div class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="my-0.5">
                        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 leading-none">
                            {{ stats.completed }}
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-muted-foreground leading-tight">
                        Stok telah diselaraskan
                    </p>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-muted-foreground uppercase tracking-wider">
                Daftar Sesi Stock Opname
            </h2>
            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-10 px-4 rounded-md text-sm font-medium shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Buat Stock Opname Baru
            </button>
        </div>

        <!-- Table -->
        <div class="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tanggal</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Keterangan</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Jumlah Item</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="opnames.data.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-muted-foreground text-sm font-medium">Belum ada riwayat stock opname dari Admin.</td>
                    </tr>
                    <tr v-for="op in opnames.data" :key="op.id" class="hover:bg-muted/40 transition-colors">
                        <td class="px-5 py-3.5 font-semibold text-foreground text-sm">
                            {{ new Date(op.opname_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                        </td>
                        <td class="px-5 py-3.5 text-sm font-medium text-muted-foreground">
                            {{ op.catatan || '-' }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="bg-muted border border-border text-foreground px-2.5 py-0.5 rounded-md text-xs font-medium">{{ op.items_count || 0 }} item(s)</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span v-if="op.status === 'completed'" class="bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">Selesai / Disesuaikan</span>
                            <span v-else class="bg-amber-500/10 text-amber-600 border border-amber-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">Draft</span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('control.admin.stock-opname.show', op.id)" class="inline-flex items-center justify-center text-foreground bg-background border border-input hover:bg-accent h-8 px-3 rounded-md text-xs font-medium transition-colors">Lihat Detail</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="opnames.links?.length > 3" class="px-5 py-3 border-t border-border flex items-center justify-center gap-1.5 bg-muted/30">
                <template v-for="l in opnames.links" :key="l.label">
                    <button v-if="l.url" @click="router.get(l.url)" :class="l.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-foreground hover:bg-accent'" class="h-8 min-w-[32px] px-2 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" v-html="l.label"></button>
                    <span v-else class="h-8 min-w-[32px] px-2 flex items-center justify-center text-muted-foreground text-xs font-medium" v-html="l.label"></span>
                </template>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-background/80 backdrop-blur-sm p-4">
                <div class="absolute inset-0" @click="showModal = false"></div>
                <div class="relative bg-card rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col border border-border">
                    <div class="px-6 py-4 border-b border-border flex justify-between items-center bg-muted/30">
                        <div>
                            <h3 class="text-base font-semibold text-foreground tracking-tight leading-none mb-1">Buat Stock Opname Baru</h3>
                            <p class="text-xs text-muted-foreground">Penyesuaian stok sistem dengan fisik.</p>
                        </div>
                        <button @click="showModal = false" class="h-8 w-8 flex items-center justify-center rounded-md border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Tanggal Opname</label>
                                    <input type="date" v-model="form.tanggal_opname" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors" required>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">Keterangan (Opsional)</label>
                                    <input type="text" v-model="form.keterangan" class="w-full h-9 px-3 border border-input bg-background rounded-md text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors placeholder:text-muted-foreground" placeholder="Contoh: Audit mendadak Q3">
                                </div>
                            </div>

                            <div class="border-t border-border pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">Item yang Diopname</h4>
                                    <button type="button" @click="addItem" class="text-xs bg-muted text-foreground hover:bg-accent font-medium px-3 py-1.5 rounded-md transition-colors border border-border">
                                        + Tambah Baris
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(item, idx) in form.items" :key="idx" class="flex items-center gap-3 bg-muted/20 p-3 rounded-md border border-border">
                                        <div class="w-44">
                                            <select v-model="item.type" @change="item.medicine_id = ''; item.raw_material_id = ''" class="w-full h-9 px-3 text-sm border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium">
                                                <option value="medicine">Obat Jadi</option>
                                                <option value="raw_material">Bahan Baku</option>
                                            </select>
                                        </div>
                                        <div class="flex-1">
                                            <select v-if="item.type === 'medicine'" v-model="item.medicine_id" class="w-full h-9 px-3 text-sm border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" required>
                                                <option value="" disabled>Pilih Obat Jadi...</option>
                                                <option v-for="m in medicines" :key="m.id" :value="m.id">{{ m.nama_obat }}</option>
                                            </select>
                                            <select v-if="item.type === 'raw_material'" v-model="item.raw_material_id" class="w-full h-9 px-3 text-sm border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-medium" required>
                                                <option value="" disabled>Pilih Bahan Baku...</option>
                                                <option v-for="rm in rawMaterials" :key="rm.id" :value="rm.id">{{ rm.nama_bahan }}</option>
                                            </select>
                                        </div>
                                        <div class="w-32">
                                            <input type="number" v-model="item.qty_fisik" class="w-full h-9 px-3 text-sm text-right border border-input rounded-md text-foreground bg-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors font-semibold placeholder:text-muted-foreground" placeholder="Qty Fisik" required min="0" step="0.01">
                                        </div>
                                        <button type="button" @click="removeItem(idx)" :disabled="form.items.length === 1" class="w-9 h-9 flex items-center justify-center rounded-md bg-background border border-input text-muted-foreground hover:text-destructive hover:border-destructive transition-colors disabled:opacity-50">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-border flex justify-end gap-3 bg-muted/30">
                        <button type="button" @click="showModal = false" class="px-4 h-9 text-sm font-medium text-foreground bg-background border border-input hover:bg-accent rounded-md transition-colors">Batal</button>
                        <button type="button" @click="submit" :disabled="form.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md transition-colors disabled:opacity-50 shadow-sm">
                            Simpan Opname
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
