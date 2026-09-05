<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="mb-6 text-sm text-slate-600 leading-relaxed text-center font-medium">
            Masukkan password baru untuk akun Anda.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-bold text-slate-700 mb-2">Alamat Email</label>
                <div class="relative">
                    <input
                        id="email"
                        type="email"
                        class="block w-full px-4 py-3 bg-slate-50 border border-[#E8EEF6] rounded-[16px] text-slate-900 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow outline-none"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        readonly
                    />
                </div>
                <p v-if="form.errors.email" class="text-sm text-red-500 mt-2 font-medium">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-bold text-slate-700 mb-2">Password Baru</label>
                <div class="relative">
                    <input
                        id="password"
                        type="password"
                        class="block w-full px-4 py-3 bg-slate-50 border border-[#E8EEF6] rounded-[16px] text-slate-900 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow outline-none"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </div>
                <p v-if="form.errors.password" class="text-sm text-red-500 mt-2 font-medium">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-bold text-slate-700 mb-2">Konfirmasi Password Baru</label>
                <div class="relative">
                    <input
                        id="password_confirmation"
                        type="password"
                        class="block w-full px-4 py-3 bg-slate-50 border border-[#E8EEF6] rounded-[16px] text-slate-900 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow outline-none"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </div>
                <p v-if="form.errors.password_confirmation" class="text-sm text-red-500 mt-2 font-medium">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="pt-4">
                <button
                    type="submit"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                    class="w-full flex justify-center items-center gap-2 py-3.5 px-4 border border-transparent rounded-[16px] shadow-[0_8px_24px_rgba(15,23,42,0.04)] text-base font-bold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-all transform hover:-translate-y-0.5"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? 'Menyimpan...' : 'Reset Password' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
