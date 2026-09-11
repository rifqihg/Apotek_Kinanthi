<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log In" />

    <div class="min-h-screen flex flex-col lg:flex-row font-sans bg-cover bg-center bg-no-repeat" style="background-image: url('/images/background.webp');">
        
        <!-- ==============================================
             LEFT PANEL
             ============================================== -->
        <div class="w-full lg:w-[50%] min-h-screen flex flex-col justify-center items-center px-8 relative z-20">
            <div class="w-full max-w-sm">
            
            <!-- Header -->
            <div class="flex items-center gap-4 sm:gap-5 mb-10 z-10">
                <img src="/images/logo-clean.webp" alt="Logo Apotek Kinanthi" class="w-16 h-16 sm:w-20 sm:h-20 aspect-square object-contain shrink-0 drop-shadow-sm" />
                <div class="h-12 sm:h-14 w-[1.5px] bg-slate-300 shrink-0"></div>
                <div>
                    <h1 class="text-2xl sm:text-[26px] font-bold text-[#1e293b] tracking-tight leading-tight">Apotek Kinanthi</h1>
                    <div class="text-[11px] sm:text-xs text-[#0d9488] font-semibold uppercase tracking-wider mt-0.5">
                        Pharmacy Management System | Login Page
                    </div>
                </div>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-5 w-full z-10 relative">
                
                <div>
                    <input 
                        v-model="form.email" 
                        type="email" 
                        required 
                        placeholder="Email Address"
                        class="w-full px-6 py-4 bg-white border-2 border-slate-300 shadow-sm rounded-lg focus:ring-0 focus:border-teal-500 transition-all outline-none text-sm text-slate-800 placeholder-slate-400 hover:border-slate-400"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-500 ml-4 font-medium">{{ form.errors.email }}</p>
                </div>
                
                <div>
                    <input 
                        v-model="form.password" 
                        type="password" 
                        required 
                        placeholder="Password"
                        class="w-full px-6 py-4 bg-white border-2 border-slate-300 shadow-sm rounded-lg focus:ring-0 focus:border-teal-500 transition-all outline-none text-sm text-slate-800 placeholder-slate-400 hover:border-slate-400"
                    />
                    <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-500 ml-4 font-medium">{{ form.errors.password }}</p>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between mt-1 px-4">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded text-teal-500 border-slate-300 focus:ring-teal-400 transition-colors" />
                        <span class="text-xs font-medium text-slate-500 group-hover:text-slate-700 transition-colors">Remember Me</span>
                    </label>
                    <Link v-if="route().has('password.request')" :href="route('password.request')" class="text-xs font-semibold text-teal-600 hover:text-teal-700 transition-colors">
                        Forgot Password?
                    </Link>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-400 hover:to-cyan-500 text-white font-semibold py-4 rounded-lg transition-all active:scale-[0.98] shadow-md shadow-teal-500/25 disabled:opacity-70 text-sm tracking-wide"
                    >
                        Sign In
                    </button>
                </div>
            </form>
            </div>
        </div>

    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
.font-sans {
    font-family: 'Inter', sans-serif;
}
</style>
