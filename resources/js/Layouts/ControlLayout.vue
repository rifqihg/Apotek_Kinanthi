<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || { name: 'User', role: 'admin' });
const userRole = computed(() => user.value.role?.toLowerCase() || 'admin');

// Sidebar States
const isMobileSidebarOpen = ref(false);
const isDesktopCollapsed = ref(false);

// Accordion States
const openAccordions = ref({});
const toggleAccordion = (key) => {
    openAccordions.value[key] = !openAccordions.value[key];
    sessionStorage.setItem('sidebarAccordions', JSON.stringify(openAccordions.value));
};

// Scroll Persistence
const desktopSidebarRef = ref(null);
const mobileSidebarRef = ref(null);

const saveScrollPosition = (e) => {
    sessionStorage.setItem('sidebarScrollPosition', e.target.scrollTop);
};

onMounted(() => {
    const savedScroll = sessionStorage.getItem('sidebarScrollPosition');
    if (savedScroll) {
        if (desktopSidebarRef.value) desktopSidebarRef.value.scrollTop = parseInt(savedScroll, 10);
        if (mobileSidebarRef.value) mobileSidebarRef.value.scrollTop = parseInt(savedScroll, 10);
    }
    
    const savedAccordions = sessionStorage.getItem('sidebarAccordions');
    if (savedAccordions) {
        try {
            openAccordions.value = JSON.parse(savedAccordions);
        } catch (e) {
            // ignore
        }
    }
});

// Navigation Definitions per Role
const adminNav = [
    {
        group: 'DASHBOARD',
        items: [
            { name: 'Dashboard', href: 'control.admin.dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        ]
    },
    {
        group: 'MASTER DATA',
        items: [
            {
                name: 'Katalog Obat', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                key: 'katalog',
                children: [
                    { name: 'Katalog Faktur', href: 'control.admin.medicines.faktur' },
                    { name: 'Katalog Racikan', href: 'control.admin.medicines.racikan' }
                ]
            },
            { name: 'Supplier / PBF', href: 'control.admin.suppliers', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
            { name: 'Bahan Baku', href: 'control.admin.raw-materials', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4' },
            { name: 'Produk Racikan', href: 'control.admin.compounded-products', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
            { name: 'Penetapan Harga', href: 'control.admin.pricing.index', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        ]
    },
    {
        group: 'INVENTORY',
        items: [
            { name: 'Stock Opname', href: 'control.admin.stock-opname.index', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            { name: 'Distribusi Stok', href: 'control.admin.inventory.distribution', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
            { name: 'Faktur Masuk', href: 'control.admin.incoming-stocks', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'Retur Obat', href: 'control.admin.returns.index', icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' },
        ]
    },

    {
        group: 'LAPORAN',
        items: [
            { name: 'Monitoring Transaksi', href: 'control.admin.reports.cashier-monitoring', icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' },
            { name: 'Audit Trail', href: 'control.admin.audit-trails', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
            { name: 'Log Stok', href: 'control.admin.stock-logs', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ]
    },
    {
        group: 'SISTEM',
        items: [
            { name: 'Pengguna', href: 'control.admin.users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
            { name: 'Pengaturan', href: 'control.admin.settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
        ]
    }
];

const apotekerNav = [
    {
        group: 'DASHBOARD',
        items: [
            { name: 'Dashboard', href: 'control.apoteker.dashboard', icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z' },
        ]
    },
    {
        group: 'OPERASIONAL KLINIS',
        items: [
            { name: 'Racikan Aktif', href: 'control.apoteker.submissions', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
            { name: 'Resep Pasien', href: 'control.apoteker.prescriptions.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'Formula Obat', href: 'control.apoteker.formulas', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
        ]
    },
    {
        group: 'INVENTORY',
        items: [
            { name: 'Stok Bahan Baku', href: 'control.apoteker.raw-materials', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
            { name: 'Cek Stok Obat', href: 'control.apoteker.stock-check', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
            { name: 'BUD Tracker', href: 'control.apoteker.bud-tracker', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        ]
    },
    {
        group: 'AUDIT & KEPATUHAN',
        items: [
            { name: 'Audit & Mutasi', href: 'control.apoteker.stock-audit', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ]
    }
];

const kasirNav = [
    {
        group: 'TRANSAKSI',
        items: [
            { name: 'Dashboard / Antrian', href: 'control.kasir.queue', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
            { name: 'Penjualan OTC', href: 'control.kasir.otc.index', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' }
        ]
    }
];

const currentNav = computed(() => {
    if (userRole.value === 'admin') return adminNav;
    if (userRole.value === 'apoteker') return apotekerNav;
    if (userRole.value === 'kasir') return kasirNav;
    return [];
});

const roleLabel = computed(() => {
    const labels = { admin: 'Administrator', apoteker: 'Apoteker', kasir: 'Kasir' };
    return labels[userRole.value] || 'Pengguna';
});

const isRouteActive = (routeName) => {
    try { return route().current(routeName); } catch { return false; }
};

const safeRoute = (routeName) => {
    try { return route(routeName); } catch { return '#'; }
};

// Flash Messages
const showFlash = ref(false);
const showErrorFlash = ref(false);

watch(() => page.props.flash?.success, (newVal) => {
    if (newVal) {
        showFlash.value = true;
        setTimeout(() => { showFlash.value = false; }, 4000);
    }
}, { immediate: true });

watch(() => page.props.flash?.error, (newVal) => {
    if (newVal) {
        showErrorFlash.value = true;
    }
}, { immediate: true });
</script>

<template>
    <div class="h-screen bg-[#F5F9FF] flex font-sans text-slate-800 selection:bg-blue-100 selection:text-blue-900 overflow-hidden">

        <!-- ═══ DESKTOP SIDEBAR ═══ -->
        <aside
            class="hidden lg:flex flex-col h-screen bg-white border-r border-[#E8EEF6] transition-all duration-300 ease-in-out z-30 sticky top-0"
            :class="isDesktopCollapsed ? 'w-[80px]' : 'w-[280px]'"
        >
            <!-- Logo -->
            <div class="h-20 flex items-center gap-3 px-6 border-b border-slate-50 flex-shrink-0">
                <div class="w-10 h-10 bg-blue-600 rounded-[16px] flex items-center justify-center shadow-lg shadow-blue-600/20 flex-shrink-0 text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                </div>
                <span v-show="!isDesktopCollapsed" class="font-bold text-slate-900 tracking-tight text-xl whitespace-nowrap">Kinanthi</span>
            </div>

            <!-- Navigation -->
            <nav ref="desktopSidebarRef" @scroll="saveScrollPosition" class="flex-1 overflow-y-auto py-6 px-4 space-y-8 sidebar-scroll">
                <div v-for="section in currentNav" :key="section.group" class="mb-2">
                    <p v-show="!isDesktopCollapsed" class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ section.group }}</p>
                    <div v-show="isDesktopCollapsed" class="h-px bg-slate-100 mx-4 mb-3"></div>

                    <div class="space-y-1">
                        <template v-for="item in section.items" :key="item.name">
                            <!-- Simple Link (no children) -->
                            <Link
                                v-if="!item.children"
                                :href="safeRoute(item.href)"
                                class="flex items-center gap-3 px-4 py-3 rounded-[16px] transition-all duration-200 group relative overflow-hidden"
                                :class="isRouteActive(item.href)
                                    ? 'bg-blue-50 text-blue-700 font-bold'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600 hover:translate-x-1 font-medium'"
                            >
                                <svg class="w-[20px] h-[20px] flex-shrink-0 transition-colors duration-200" :class="isRouteActive(item.href) ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span v-show="!isDesktopCollapsed" class="text-[13.5px] whitespace-nowrap tracking-wide">{{ item.name }}</span>
                                <!-- Active Indicator Bar -->
                                <div v-if="isRouteActive(item.href) && !isDesktopCollapsed" class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 rounded-r-full bg-blue-600 shadow-[0_0_8px_rgba(37,99,235,0.4)]"></div>
                            </Link>

                            <!-- Accordion (has children) -->
                            <div v-else>
                                <button
                                    @click="toggleAccordion(item.key)"
                                    class="w-full flex items-center gap-3 px-4 py-3 rounded-[16px] transition-all duration-200 group relative text-slate-500 hover:bg-slate-50 hover:text-blue-600 hover:translate-x-1 font-medium"
                                >
                                    <svg class="w-[20px] h-[20px] flex-shrink-0 text-slate-400 group-hover:text-blue-500 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                    </svg>
                                    <span v-show="!isDesktopCollapsed" class="text-[13.5px] whitespace-nowrap flex-1 text-left tracking-wide">{{ item.name }}</span>
                                    <svg v-show="!isDesktopCollapsed" class="w-4 h-4 text-slate-400 transition-transform duration-300" :class="openAccordions[item.key] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="openAccordions[item.key] && !isDesktopCollapsed" class="ml-7 pl-4 border-l border-slate-100 mt-1 space-y-1">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.name"
                                        :href="safeRoute(child.href)"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] transition-all duration-200 font-medium relative"
                                        :class="isRouteActive(child.href)
                                            ? 'text-blue-700 font-bold bg-blue-50/80'
                                            : 'text-slate-500 hover:text-blue-600 hover:bg-slate-50 hover:translate-x-1'"
                                    >
                                        <div class="w-1.5 h-1.5 rounded-full transition-colors duration-200" :class="isRouteActive(child.href) ? 'bg-blue-600 shadow-[0_0_8px_rgba(37,99,235,0.4)]' : 'bg-slate-300'"></div>
                                        <span class="tracking-wide">{{ child.name }}</span>
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- Bottom: System Widget + Collapse Toggle -->
            <div class="border-t border-[#E8EEF6] p-4 space-y-4 flex-shrink-0 bg-white">
                <!-- System Status Widget -->
                <div v-show="!isDesktopCollapsed" class="bg-[#F5F9FF] rounded-[16px] border border-[#E8EEF6] p-4">
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="relative flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </div>
                        <span class="text-[11px] font-bold text-slate-700 tracking-wide">Sistem Normal</span>
                    </div>
                    <div class="space-y-2 mt-3 pt-3 border-t border-[#E8EEF6]">
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500 font-medium">Server</span>
                            <span class="text-slate-700 font-semibold">Online</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500 font-medium">Backup</span>
                            <span class="text-slate-700 font-semibold">10 mnt lalu</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-500 font-medium">Versi</span>
                            <span class="text-blue-600 font-bold">v1.0.0</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="isDesktopCollapsed = !isDesktopCollapsed" class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 text-slate-400 hover:text-blue-600 transition-colors rounded-[16px] hover:bg-blue-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isDesktopCollapsed ? 'M13 5l7 7-7 7' : 'M11 19l-7-7 7-7'" /></svg>
                    </button>
                    <Link :href="safeRoute('logout')" method="post" as="button" class="flex items-center justify-center px-3 py-2.5 text-slate-400 hover:text-rose-600 transition-colors rounded-[16px] hover:bg-rose-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ═══ MOBILE OVERLAY ═══ -->
        <div v-show="isMobileSidebarOpen" class="lg:hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40" @click="isMobileSidebarOpen = false"></div>

        <!-- ═══ MOBILE SIDEBAR DRAWER ═══ -->
        <aside
            class="lg:hidden fixed inset-y-0 left-0 w-[280px] bg-white z-50 shadow-2xl transform transition-transform duration-300 ease-in-out"
            :class="isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="h-20 flex items-center justify-between px-6 border-b border-slate-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-[16px] flex items-center justify-center text-white shadow-lg shadow-blue-600/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    </div>
                    <span class="font-bold text-slate-900 text-xl">Kinanthi</span>
                </div>
                <button @click="isMobileSidebarOpen = false" class="p-2 text-slate-400 hover:text-slate-600 rounded-[16px] hover:bg-slate-50">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <nav ref="mobileSidebarRef" @scroll="saveScrollPosition" class="p-4 space-y-8 overflow-y-auto h-[calc(100vh-80px)] sidebar-scroll">
                <div v-for="section in currentNav" :key="section.group">
                    <p class="px-3 mb-3 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">{{ section.group }}</p>
                    <div class="space-y-1">
                        <template v-for="item in section.items" :key="item.name">
                            <Link
                                v-if="!item.children"
                                :href="safeRoute(item.href)"
                                @click="isMobileSidebarOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-[16px] transition-all font-medium"
                                :class="isRouteActive(item.href)
                                    ? 'bg-blue-600 text-white font-bold shadow-[0_8px_24px_rgba(37,99,235,0.2)]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'"
                            >
                                <svg class="w-[20px] h-[20px] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span class="text-[13.5px]">{{ item.name }}</span>
                            </Link>
                            <div v-else>
                                <button
                                    @click="toggleAccordion(item.key)"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-[16px] text-slate-500 hover:bg-slate-50 transition-all font-medium hover:text-blue-600"
                                >
                                    <svg class="w-[20px] h-[20px] flex-shrink-0 text-slate-400 transition-colors group-hover:text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                    </svg>
                                    <span class="text-[13.5px] flex-1 text-left">{{ item.name }}</span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="openAccordions[item.key] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="openAccordions[item.key]" class="ml-5 pl-3 border-l border-slate-100 mt-1 space-y-1">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.name"
                                        :href="safeRoute(child.href)"
                                        @click="isMobileSidebarOpen = false"
                                        class="flex items-center gap-2 px-3 py-2 rounded-[12px] text-[12.5px] transition-all font-medium"
                                        :class="isRouteActive(child.href) ? 'text-blue-700 font-bold bg-blue-50' : 'text-slate-500 hover:text-blue-600 hover:bg-slate-50'"
                                    >
                                        <div class="w-1.5 h-1.5 rounded-full" :class="isRouteActive(child.href) ? 'bg-blue-600' : 'bg-slate-300'"></div>
                                        {{ child.name }}
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <!-- Mobile Logout -->
                <div class="pt-6 border-t border-[#E8EEF6]">
                    <Link :href="safeRoute('logout')" method="post" as="button" class="w-full flex items-center gap-3 px-3 py-3 rounded-[16px] text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-all font-semibold">
                        <svg class="w-[20px] h-[20px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        <span class="text-[13.5px]">Keluar Aplikasi</span>
                    </Link>
                </div>
            </nav>
        </aside>

        <!-- ═══ MAIN CONTENT AREA ═══ -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Bar -->
            <header class="h-20 bg-white/95 backdrop-blur-md border-b border-[#E8EEF6] flex items-center justify-between px-6 lg:px-8 flex-shrink-0 sticky top-0 z-20 shadow-[0_4px_20px_rgba(15,23,42,0.02)]">
                <!-- Left: Title / Mobile Toggle -->
                <div class="flex items-center gap-4">
                    <button @click="isMobileSidebarOpen = true" class="lg:hidden p-2 text-slate-500 hover:bg-slate-50 rounded-[16px] -ml-2 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <!-- Global Search -->
                    <div class="hidden md:flex items-center">
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" placeholder="Cari obat, racikan, supplier..." class="block w-[380px] pl-11 pr-4 py-3 bg-slate-50 border-transparent rounded-[16px] text-sm text-slate-700 placeholder-slate-400 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-inner" />
                        </div>
                    </div>
                </div>

                <!-- Right: Actions & Profile -->
                <div class="flex items-center gap-3 lg:gap-5">
                    <!-- Live Indicator -->
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-full border border-emerald-100">
                        <div class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-widest">Live</span>
                    </div>

                    <div class="h-8 w-px bg-slate-200 hidden sm:block mx-1"></div>

                    <!-- Notifications -->
                    <div class="flex items-center gap-2">
                        <button class="relative p-2.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-[12px] transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            <span class="absolute top-2 right-2 flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                            </span>
                        </button>
                        <button class="hidden sm:block relative p-2.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-[12px] transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                        </button>
                    </div>

                    <!-- User Profile -->
                    <div class="flex items-center gap-3 pl-2 lg:pl-5 border-l border-slate-200">
                        <div class="text-right hidden md:block">
                            <p class="text-[13px] font-bold text-slate-800 leading-tight">{{ user.name }}</p>
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-0.5">{{ roleLabel }}</p>
                        </div>
                        <button class="w-10 h-10 rounded-[14px] bg-blue-600 flex items-center justify-center text-white font-black text-sm shadow-md shadow-blue-600/20 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main id="main-content-scroll" class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 bg-[#F5F9FF] main-scroll">
                <div class="max-w-[1600px] mx-auto">
                    <!-- Flash Messages -->
                    <div v-if="$page.props.flash?.success && showFlash" class="mb-6 p-4 bg-emerald-600 text-white rounded-[24px] shadow-[0_8px_24px_rgba(16,185,129,0.2)] flex justify-between items-center transition-all">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            <span class="font-medium text-sm">{{ $page.props.flash.success }}</span>
                        </div>
                        <button @click="showFlash = false" class="text-emerald-100 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <div v-if="$page.props.flash?.error && showErrorFlash" class="mb-6 p-4 bg-rose-600 text-white rounded-[24px] shadow-[0_8px_24px_rgba(225,29,72,0.2)] flex justify-between items-center transition-all">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="font-medium text-sm">{{ $page.props.flash.error }}</span>
                        </div>
                        <button @click="showErrorFlash = false" class="text-rose-100 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <slot></slot>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.sidebar-scroll::-webkit-scrollbar { width: 4px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.main-scroll::-webkit-scrollbar { width: 6px; }
.main-scroll::-webkit-scrollbar-track { background: transparent; }
.main-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.main-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<style>
/* Global scroll lock: only main-content-scroll scrolls */
html, body {
    overflow: hidden !important;
    height: 100%;
    overscroll-behavior-y: none;
}
</style>
