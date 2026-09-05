<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

/**
 * EDIT PROFIL VIEW
 * Integrated Account Settings for Admin, Gudang, and Apoteker
 */

const props = defineProps({
    status: String,
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    current_password: '',
    password: '',
    password_confirmation: '',
});

const isProcessing = ref(false);

const updateProfile = () => {
    isProcessing.value = true;
    form.put(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isProcessing.value = false;
            form.reset('current_password', 'password', 'password_confirmation');
            Swal.fire({
                title: 'Profil Diperbarui',
                text: 'Informasi akun Anda telah berhasil diperbarui.',
                icon: 'success',
                confirmButtonColor: '#10b981',
                customClass: {
                    popup: 'rounded-[2rem]',
                }
            });
        },
        onError: () => {
            isProcessing.value = false;
            Swal.fire({
                title: 'Gagal Memperbarui',
                text: 'Mohon periksa kembali inputan Anda.',
                icon: 'error',
                confirmButtonColor: '#ef4444',
                customClass: {
                    popup: 'rounded-[1.5rem]',
                }
            });
        },
    });
};
</script>

<script>
import ControlLayout from '@/Layouts/ControlLayout.vue';
export default { layout: ControlLayout };
</script>

<template>
    <Head title="Pengaturan Profil" />

    <div class="py-12">
        <div class="max-w-4xl mx-auto space-y-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Pengaturan Akun</h1>
                    <p class="text-slate-500 mt-2 font-medium">Kelola informasi profil dan keamanan kata sandi Anda.</p>
                </div>
                <div class="flex items-center gap-3 bg-white p-2 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                    <div class="w-12 h-12 rounded-[16px] bg-emerald-600 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-emerald-200">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="pr-4">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Role Terdaftar</p>
                        <p class="text-sm font-bold text-slate-800 mt-1 uppercase">{{ user.role }}</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="updateProfile" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Sidebar Info -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-8 rounded-[2.5rem] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] relative overflow-hidden group">
                        <div class="absolute -top-12 -right-12 w-32 h-32 bg-emerald-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
                        <h3 class="text-lg font-black text-slate-900 relative z-10">Informasi Dasar</h3>
                        <p class="text-xs text-slate-500 mt-2 font-medium leading-relaxed relative z-10">
                            Pastikan nama dan email Anda aktif untuk keperluan audit trail dan notifikasi sistem.
                        </p>
                        <div class="mt-8 space-y-4 relative z-10">
                            <div class="flex items-center gap-3 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                <span class="text-xs font-bold">{{ user.name }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <span class="text-xs font-bold">{{ user.email }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 p-8 rounded-[2.5rem] text-white shadow-xl shadow-slate-200">
                        <h3 class="text-lg font-black tracking-tight">Keamanan Akun</h3>
                        <p class="text-xs text-slate-400 mt-2 font-medium leading-relaxed">
                            Gunakan kombinasi simbol dan angka untuk performa keamanan maksimum.
                        </p>
                    </div>
                </div>

                <!-- Right Form Area -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info Card -->
                    <div class="bg-white p-10 rounded-[24px] border border-[#E8EEF6] shadow-[0_8px_24px_rgba(15,23,42,0.04)] space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Nama Lengkap</label>
                                <input 
                                    v-model="form.name" 
                                    type="text" 
                                    class="w-full bg-slate-50 border-2 border-[#E8EEF6] rounded-[24px] px-6 py-4 text-sm font-bold text-slate-900 focus:ring-8 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"
                                    placeholder="Masukkan nama lengkap..."
                                />
                                <p v-if="form.errors.name" class="text-xs text-rose-500 font-bold mt-1 ml-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Alamat Email</label>
                                <input 
                                    v-model="form.email" 
                                    type="email" 
                                    class="w-full bg-slate-50 border-2 border-[#E8EEF6] rounded-[24px] px-6 py-4 text-sm font-bold text-slate-900 focus:ring-8 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"
                                    placeholder="example@mail.com"
                                />
                                <p v-if="form.errors.email" class="text-xs text-rose-500 font-bold mt-1 ml-1">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <div class="h-px bg-slate-100"></div>

                        <div class="space-y-6">
                            <h4 class="text-sm font-black text-slate-900 flex items-center gap-2">
                                <div class="w-2 h-2 bg-rose-500 rounded-full animate-pulse"></div>
                                Ubah Kata Sandi
                            </h4>
                            
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Kata Sandi Saat Ini</label>
                                <input 
                                    v-model="form.current_password" 
                                    type="password" 
                                    class="w-full bg-slate-50 border-2 border-[#E8EEF6] rounded-[24px] px-6 py-4 text-sm font-bold text-slate-900 focus:ring-8 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all font-mono"
                                    placeholder="••••••••"
                                />
                                <p v-if="form.errors.current_password" class="text-xs text-rose-500 font-bold mt-1 ml-1">{{ form.errors.current_password }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-2">
                                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Kata Sandi Baru</label>
                                    <input 
                                        v-model="form.password" 
                                        type="password" 
                                        class="w-full bg-slate-50 border-2 border-[#E8EEF6] rounded-[24px] px-6 py-4 text-sm font-bold text-slate-900 focus:ring-8 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-mono"
                                        placeholder="Min. 8 karakter"
                                    />
                                    <p v-if="form.errors.password" class="text-xs text-rose-500 font-bold mt-1 ml-1">{{ form.errors.password }}</p>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Konfirmasi Kata Sandi</label>
                                    <input 
                                        v-model="form.password_confirmation" 
                                        type="password" 
                                        class="w-full bg-slate-50 border-2 border-[#E8EEF6] rounded-[24px] px-6 py-4 text-sm font-bold text-slate-900 focus:ring-8 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-mono"
                                        placeholder="Ulangi kata sandi"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-10 py-5 bg-emerald-600 text-white rounded-[2rem] text-xs font-black uppercase tracking-widest hover:bg-emerald-700 shadow-2xl shadow-emerald-200 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-3"
                            >
                                <span v-if="form.processing">Menyimpan...</span>
                                <span v-else>Simpan Perubahan</span>
                                <svg v-if="!form.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
