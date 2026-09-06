<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F8FAFC] p-4 relative overflow-hidden">
        <!-- Abstract Ornaments -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-teal-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 translate-x-1/3 -translate-y-1/3"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-amber-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 -translate-x-1/4 translate-y-1/4"></div>

        <div class="relative w-full max-w-md bg-white border border-slate-100 shadow-xl rounded-[2rem] overflow-hidden z-10 flex flex-col">
            <div class="bg-teal-600 p-8 text-center text-white relative">
                <!-- Inner ornament -->
                <div class="absolute inset-0 bg-gradient-to-br from-teal-500 to-teal-700 opacity-80"></div>
                <div class="relative z-10">
                    <img src="/images/logo.webp" alt="Apotek Kinanthi Logo" class="w-20 h-20 rounded-full object-cover mx-auto shadow-md border-2 border-white mb-4" />
                    <h1 class="text-2xl font-extrabold tracking-wide">Apotek Kinanthi</h1>
                    <p class="text-teal-100 text-sm mt-1">Sistem Manajemen & Inventori</p>
                </div>
            </div>

            <div class="p-8 pb-10">
                <slot />
            </div>
        </div>
    </div>
</template>
