<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-6 text-sm text-slate-600 leading-relaxed text-center">
            Lupa password? Masukkan alamat email Anda dan kami akan mengirimkan link untuk mereset password Anda.
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 p-3 rounded-[16px] border border-emerald-100 text-center">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-bold text-slate-700 mb-2">Alamat Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                    </div>
                    <input
                        id="email"
                        type="email"
                        class="block w-full pl-11 pr-4 py-3 bg-slate-50 border border-[#E8EEF6] rounded-[16px] text-slate-900 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow outline-none"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="admin@apotek.com"
                    />
                </div>
                <p v-if="form.errors.email" class="text-sm text-red-500 mt-2 font-medium">{{ form.errors.email }}</p>
            </div>

            <div class="pt-2 space-y-3">
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
                    {{ form.processing ? 'Memproses...' : 'Kirim Link Reset Password' }}
                </button>
                <Link :href="route('login')" class="block text-center text-sm font-bold text-slate-500 hover:text-teal-600 transition">
                    Kembali ke halaman Login
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
