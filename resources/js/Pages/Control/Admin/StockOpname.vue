<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: ControlLayout });

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

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-foreground tracking-tight">Daftar Sesi Stock Opname</h2>
                <p class="text-muted-foreground text-sm mt-1">Riwayat penyesuaian stok langsung oleh Admin.</p>
            </div>
            <button @click="showModal = true" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-9 px-4 rounded-md text-sm font-medium shadow-sm transition-colors">
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
