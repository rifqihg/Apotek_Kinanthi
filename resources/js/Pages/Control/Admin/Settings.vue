<script setup>
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object });

const form = useForm({
    default_ppn: props.settings.default_ppn,
    default_margin: props.settings.default_margin,
});

const submit = () => form.put(route('control.admin.settings.update'));
</script>

<template>
    <Head title="Pengaturan" />
    <ControlLayout>
        <template #header-title>Konfigurasi Sistem</template>
        
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <div>
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Pengaturan Sistem</h2>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-tight mt-1">Konfigurasi nilai default PPN dan margin profit untuk keseluruhan sistem</p>
                </div>
            </div>

            <div class="bg-white border border-[#E8EEF6] rounded-[24px] p-8 max-w-2xl shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                <form @submit.prevent="submit" class="space-y-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                                Default PPN (%)
                            </label>
                            <div class="relative">
                                <input v-model="form.default_ppn" type="number" step="0.01" min="0" max="100"
                                    class="w-full h-[52px] bg-slate-50 focus:bg-white border border-[#E8EEF6] rounded-[16px] px-4 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-shadow font-mono font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)]" />
                                <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 font-bold">%</span>
                            </div>
                            <p v-if="form.errors.default_ppn" class="text-rose-500 text-[10px] font-bold mt-1.5 pl-1">{{ form.errors.default_ppn }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 pl-1 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Default Margin (%)
                            </label>
                            <div class="relative">
                                <input v-model="form.default_margin" type="number" step="0.01" min="0" max="100"
                                    class="w-full h-[52px] bg-slate-50 focus:bg-white border border-[#E8EEF6] rounded-[16px] px-4 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-shadow font-mono font-bold shadow-[0_8px_24px_rgba(15,23,42,0.04)]" />
                                <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 font-bold">%</span>
                            </div>
                            <p v-if="form.errors.default_margin" class="text-rose-500 text-[10px] font-bold mt-1.5 pl-1">{{ form.errors.default_margin }}</p>
                        </div>
                    </div>

                    <div class="bg-blue-50/50 border border-blue-100 rounded-[16px] p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                        <p class="text-blue-800 text-[11px] font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Simulasi Kalkulasi Harga Jual Obat
                        </p>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center py-1 border-b border-blue-100/60 border-dashed">
                                <span class="text-slate-500 font-bold text-xs">Harga Beli Dasar (HNA)</span>
                                <span class="text-slate-900 font-mono font-bold text-xs">Rp 10.000</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-blue-100/60 border-dashed">
                                <span class="text-slate-500 font-bold text-xs">PPN ({{ form.default_ppn }}%)</span>
                                <span class="text-slate-900 font-mono font-bold text-xs">+ Rp {{ (10000 * (form.default_ppn||0) / 100).toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-blue-100/60 border-dashed">
                                <span class="text-slate-500 font-bold text-xs">Margin Profit ({{ form.default_margin }}%)</span>
                                <span class="text-slate-900 font-mono font-bold text-xs">+ Rp {{ (10000 * (form.default_margin||0) / 100).toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3">
                                <span class="text-slate-700 font-black text-sm uppercase tracking-tight">Harga Jual Akhir (HJA)</span>
                                <span class="text-blue-700 font-mono font-black text-lg bg-white px-3 py-1 rounded-[12px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] border border-blue-200">
                                    Rp {{ (10000 + 10000 * (form.default_ppn||0) / 100 + 10000 * (form.default_margin||0) / 100).toLocaleString('id-ID') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="w-full sm:w-auto px-8 h-[52px] bg-blue-600 text-white rounded-[16px] text-[11px] font-bold uppercase tracking-widest shadow-[0_8px_24px_rgba(15,23,42,0.04)] hover:bg-blue-700 hover:shadow transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 active:scale-95">
                            <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ControlLayout>
</template>
