<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import Swal from 'sweetalert2';

defineOptions({ layout: ControlLayout });

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updateProfile = () => {
    form.put(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('current_password', 'password', 'password_confirmation');
            Swal.fire({
                title: 'Profil Diperbarui',
                text: 'Informasi akun Anda telah berhasil diperbarui.',
                icon: 'success',
                confirmButtonColor: '#10b981',
                customClass: { popup: 'rounded-lg' }
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Gagal Memperbarui',
                text: 'Mohon periksa kembali inputan Anda.',
                icon: 'error',
                confirmButtonColor: '#ef4444',
                customClass: { popup: 'rounded-lg' }
            });
        },
    });
};
</script>

<template>
    <Head title="Pengaturan Profil" />

    <div class="py-6">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">Pengaturan Akun</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">Kelola informasi profil dan keamanan kata sandi Anda.</p>
                </div>
                <div class="flex items-center gap-3 bg-card p-2 rounded-lg border border-border shadow-sm">
                    <div class="w-10 h-10 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400 border border-emerald-200 flex items-center justify-center text-base font-bold">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="pr-3">
                        <p class="text-xs text-muted-foreground leading-none">Role Terdaftar</p>
                        <p class="text-xs font-semibold text-foreground mt-1 uppercase">{{ user.role }}</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="updateProfile" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Sidebar Info -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-card p-5 rounded-lg border border-border shadow-sm space-y-3">
                        <h3 class="text-base font-semibold text-foreground">Informasi Dasar</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Pastikan nama dan email Anda aktif untuk keperluan audit trail dan notifikasi sistem.
                        </p>
                        <div class="pt-2 space-y-2.5">
                            <div class="flex items-center gap-2.5 text-muted-foreground">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                <span class="text-xs font-medium text-foreground">{{ user.name }}</span>
                            </div>
                            <div class="flex items-center gap-2.5 text-muted-foreground">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <span class="text-xs font-medium text-foreground">{{ user.email }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-muted/40 p-5 rounded-lg border border-border">
                        <h3 class="text-sm font-semibold text-foreground">Keamanan Akun</h3>
                        <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
                            Gunakan kombinasi simbol dan angka untuk performa keamanan maksimum.
                        </p>
                    </div>
                </div>

                <!-- Right Form Area -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info Card -->
                    <div class="bg-card p-6 rounded-lg border border-border shadow-sm space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-muted-foreground">Nama Lengkap</label>
                                <input 
                                    v-model="form.name" 
                                    type="text" 
                                    class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                                    placeholder="Masukkan nama lengkap..."
                                />
                                <p v-if="form.errors.name" class="text-xs text-destructive font-medium mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-muted-foreground">Alamat Email</label>
                                <input 
                                    v-model="form.email" 
                                    type="email" 
                                    class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                                    placeholder="example@mail.com"
                                />
                                <p v-if="form.errors.email" class="text-xs text-destructive font-medium mt-1">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <div class="h-px bg-border"></div>

                        <div class="space-y-4">
                            <h4 class="text-sm font-semibold text-foreground flex items-center gap-2">
                                <div class="w-2 h-2 bg-destructive rounded-full"></div>
                                Ubah Kata Sandi
                            </h4>
                            
                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-muted-foreground">Kata Sandi Saat Ini</label>
                                <input 
                                    v-model="form.current_password" 
                                    type="password" 
                                    class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                                    placeholder="••••••••"
                                />
                                <p v-if="form.errors.current_password" class="text-xs text-destructive font-medium mt-1">{{ form.errors.current_password }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-muted-foreground">Kata Sandi Baru</label>
                                    <input 
                                        v-model="form.password" 
                                        type="password" 
                                        class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                                        placeholder="Min. 8 karakter"
                                    />
                                    <p v-if="form.errors.password" class="text-xs text-destructive font-medium mt-1">{{ form.errors.password }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-muted-foreground">Konfirmasi Kata Sandi</label>
                                    <input 
                                        v-model="form.password_confirmation" 
                                        type="password" 
                                        class="h-9 w-full bg-background border border-input rounded-md px-3 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring transition-colors"
                                        placeholder="Ulangi kata sandi"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors active:scale-95 disabled:opacity-50 inline-flex items-center gap-2"
                            >
                                <span v-if="form.processing">Menyimpan...</span>
                                <span v-else>Simpan Perubahan</span>
                                <svg v-if="!form.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
