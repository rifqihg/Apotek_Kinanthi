<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';

defineOptions({ layout: ControlLayout });

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

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Pustaka Resep & Formula Obat</h1>
                <p class="text-sm text-muted-foreground mt-1">Kelola panduan instruksi racikan standar kefarmasian</p>
            </div>
            <button @click="showModal=true;form.reset();form.clearErrors()" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white h-9 px-4 rounded-md text-sm font-medium shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Formula Baru
            </button>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-if="!formulas?.data?.length" class="col-span-full py-16 flex flex-col items-center justify-center bg-card border border-border rounded-lg shadow-sm text-center">
                <div class="w-12 h-12 bg-muted text-muted-foreground rounded-md flex items-center justify-center mb-3 border border-border">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <p class="text-foreground font-semibold text-sm">Pustaka Formula Kosong</p>
                <p class="text-muted-foreground text-xs mt-1 max-w-sm">Klik tombol Tambah Formula Baru untuk mulai membangun basis data instruksi racikan standar.</p>
            </div>

            <div v-for="f in (formulas?.data || [])" :key="f.id" class="bg-card border border-border rounded-lg p-5 shadow-sm hover:shadow-md transition-all flex flex-col group">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-9 h-9 rounded-md bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <span class="inline-flex items-center bg-muted text-muted-foreground border border-border text-xs px-2.5 py-0.5 rounded-md font-semibold uppercase tracking-wider">
                        {{ f.kategori_sediaan || 'Kustom' }}
                    </span>
                </div>
                <h2 class="text-foreground font-semibold text-sm leading-tight mb-2">{{ f.nama_resep }}</h2>
                <p class="text-muted-foreground text-xs leading-relaxed line-clamp-3 flex-1">{{ f.instruksi_racik || 'Tidak ada instruksi khusus pemrosesan.' }}</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="formulas.links?.length > 3" class="flex justify-center gap-1 pt-4">
            <template v-for="l in formulas.links" :key="l.label">
                <button 
                    v-if="l.url" 
                    @click="router.get(l.url)" 
                    :class="l.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-background border-input text-foreground hover:bg-muted'" 
                    class="w-8 h-8 flex items-center justify-center rounded-md text-xs font-medium transition-colors border" 
                    v-html="l.label"
                ></button>
                <span v-else class="w-8 h-8 flex items-center justify-center text-muted-foreground text-xs" v-html="l.label"></span>
            </template>
        </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showModal=false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md overflow-hidden border border-border">
                <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-foreground font-semibold text-base">Registrasi Formula Baru</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Standarisasi alur kerja compounding.</p>
                    </div>
                </div>
                
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Resep / Sediaan <span class="text-red-500">*</span></label>
                        <input v-model="form.nama_resep" type="text" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Nama racikan standar..." required />
                        <p v-if="form.errors.nama_resep" class="text-red-500 text-xs mt-1">{{ form.errors.nama_resep }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Kategori Sediaan <span class="text-red-500">*</span></label>
                        <select v-model="form.kategori_sediaan" class="w-full h-9 bg-background border border-input rounded-md px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required>
                            <option value="" disabled>Pilih Kategori...</option>
                            <option value="Larutan Air/Sirup">Larutan Air/Sirup (BUD 14 Hari)</option>
                            <option value="Sediaan Padat/Puyer/Kapsul">Sediaan Padat/Puyer/Kapsul (BUD 30 Hari)</option>
                            <option value="Sediaan Topikal/Lainnya">Lainnya / Topikal</option>
                        </select>
                        <p v-if="form.errors.kategori_sediaan" class="text-red-500 text-xs mt-1">{{ form.errors.kategori_sediaan }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Instruksi Racik</label>
                        <textarea v-model="form.instruksi_racik" rows="4" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none resize-none transition-colors leading-relaxed" placeholder="Catatan cara pembuatan atau peringatan khusus..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showModal=false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50">Simpan Formula</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
