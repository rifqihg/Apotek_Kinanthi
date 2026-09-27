<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineOptions({ layout: ControlLayout });

const props = defineProps({ settings: Object });

const form = useForm({
    default_ppn: props.settings.default_ppn,
    default_margin: props.settings.default_margin,
});

const submit = () => form.put(route('control.admin.settings.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Pengaturan" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card p-6 rounded-lg border border-border shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Pengaturan Sistem</h1>
                <p class="text-sm text-muted-foreground mt-1">Konfigurasi nilai default PPN dan margin profit untuk keseluruhan sistem</p>
            </div>
        </div>

        <div class="bg-card border border-border rounded-lg p-6 max-w-2xl shadow-sm">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                            Default PPN (%)
                        </label>
                        <div class="relative">
                            <input v-model="form.default_ppn" type="number" step="0.01" min="0" max="100"
                                class="w-full h-9 bg-background border border-input rounded-md px-3 text-foreground font-medium focus:ring-1 focus:ring-ring outline-none transition-colors" />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground text-sm font-semibold">%</span>
                        </div>
                        <p v-if="form.errors.default_ppn" class="text-red-500 text-xs mt-1">{{ form.errors.default_ppn }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Default Margin (%)
                        </label>
                        <div class="relative">
                            <input v-model="form.default_margin" type="number" step="0.01" min="0" max="100"
                                class="w-full h-9 bg-background border border-input rounded-md px-3 text-foreground font-medium focus:ring-1 focus:ring-ring outline-none transition-colors" />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground text-sm font-semibold">%</span>
                        </div>
                        <p v-if="form.errors.default_margin" class="text-red-500 text-xs mt-1">{{ form.errors.default_margin }}</p>
                    </div>
                </div>

                <div class="bg-muted/40 border border-border rounded-md p-4 space-y-3">
                    <p class="text-foreground text-xs font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Simulasi Kalkulasi Harga Jual Obat
                    </p>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center py-1 border-b border-border border-dashed">
                            <span class="text-muted-foreground text-xs font-medium">Harga Beli Dasar (HNA)</span>
                            <span class="text-foreground font-semibold text-xs">Rp 10.000</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-border border-dashed">
                            <span class="text-muted-foreground text-xs font-medium">PPN ({{ form.default_ppn }}%)</span>
                            <span class="text-foreground font-semibold text-xs">+ Rp {{ (10000 * (form.default_ppn||0) / 100).toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-border border-dashed">
                            <span class="text-muted-foreground text-xs font-medium">Margin Profit ({{ form.default_margin }}%)</span>
                            <span class="text-foreground font-semibold text-xs">+ Rp {{ (10000 * (form.default_margin||0) / 100).toLocaleString('id-ID') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-foreground font-semibold text-xs uppercase tracking-wider">Harga Jual Akhir (HJA)</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold tracking-tight text-base bg-background px-3 py-1 rounded-md border border-border shadow-sm">
                                Rp {{ (10000 + 10000 * (form.default_ppn||0) / 100 + 10000 * (form.default_margin||0) / 100).toLocaleString('id-ID') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
