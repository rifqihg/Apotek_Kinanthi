<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Toaster, toast } from '@/Components/ui/toast';
import { Avatar, AvatarImage, AvatarFallback } from '@/Components/ui/avatar';

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
            { name: 'Dashboard', href: 'control.admin.dashboard', component: 'Control/Admin/Dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        ]
    },
    {
        group: 'MASTER DATA',
        items: [
            { name: 'Katalog Faktur', href: 'control.admin.medicines.faktur', component: 'Control/Admin/Medicines', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
            { name: 'Supplier / PBF', href: 'control.admin.suppliers', component: 'Control/Admin/Suppliers', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
            { name: 'Bahan Baku', href: 'control.admin.raw-materials', component: 'Control/Admin/RawMaterials', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4' },
            { name: 'Produk Racikan', href: 'control.admin.compounded-products', component: 'Control/Admin/CompoundedProducts', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
            { name: 'Penetapan Harga', href: 'control.admin.pricing.index', component: 'Control/Admin/Pricing', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        ]
    },
    {
        group: 'INVENTORY',
        items: [
            { name: 'Stock Opname', href: 'control.admin.stock-opname.index', component: 'Control/Admin/StockOpname', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            { name: 'Distribusi Stok', href: 'control.admin.inventory.distribution', component: 'Control/Admin/Distribution', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
            { name: 'Faktur Masuk', href: 'control.admin.incoming-stocks', component: 'Control/Admin/IncomingStocks', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'Retur Obat', href: 'control.admin.returns.index', component: 'Control/Admin/Returns', icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' },
        ]
    },
    {
        group: 'LAPORAN',
        items: [
            { name: 'Laporan Penjualan', href: 'control.admin.reports.sales', component: 'Control/Admin/SalesReport', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
            { name: 'Monitoring Transaksi', href: 'control.admin.reports.cashier-monitoring', component: 'Control/Admin/CashierMonitoring', icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' },
            { name: 'Mutasi & Audit', href: 'control.admin.stock-logs', component: 'Control/Admin/StockLogs', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ]
    },
    {
        group: 'SISTEM',
        items: [
            { name: 'Pengguna', href: 'control.admin.users', component: 'Control/Admin/Users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
            { name: 'Pengaturan', href: 'control.admin.settings', component: 'Control/Admin/Settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
        ]
    }
];

const apotekerNav = [
    {
        group: 'DASHBOARD',
        items: [
            { name: 'Dashboard', href: 'control.apoteker.dashboard', component: 'Control/Apoteker/Dashboard', icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z' },
        ]
    },
    {
        group: 'OPERASIONAL KLINIS',
        items: [
            { name: 'Racikan Aktif', href: 'control.apoteker.submissions', component: 'Control/Apoteker/Submissions', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
            { name: 'Resep Pasien', href: 'control.apoteker.prescriptions.index', component: 'Control/Apoteker/Prescriptions/Index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'Formula Obat', href: 'control.apoteker.formulas', component: 'Control/Apoteker/Formulas', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
        ]
    },
    {
        group: 'INVENTORY',
        items: [
            { name: 'Cek Stok Obat', href: 'control.apoteker.stock-check', component: 'Control/Apoteker/StockCheck', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
            { name: 'BUD Tracker', href: 'control.apoteker.bud-tracker', component: 'Control/Apoteker/BudTracker', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        ]
    },
    {
        group: 'AUDIT & KEPATUHAN',
        items: [
            { name: 'Laporan SIPNAP', href: 'control.apoteker.reports.sipnap', component: 'Control/Apoteker/Reports/Sipnap', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ]
    }
];

const kasirNav = [
    {
        group: 'TRANSAKSI',
        items: [
            { name: 'Dashboard / Antrean', href: 'control.kasir.queue', component: 'Control/Kasir/Queue', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
            { name: 'Penjualan OTC', href: 'control.kasir.otc.index', component: 'Control/Kasir/OTC', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' }
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

const isRouteActive = (target) => {
    // Mengakses page.component dan page.url secara eksplisit mengikat reaktivitas Vue
    // sehingga template sidebar selalu di-render ulang secara real-time pada milidetik yang sama saat rute berubah.
    const currentComponent = page.component;
    const currentUrl = page.url;

    const routeName = typeof target === 'string' ? target : target?.href;
    const componentName = typeof target === 'object' ? target?.component : null;

    // 1. Verifikasi berbasis nilai mutlak komponen (usePage().component === 'NamaHalaman')
    if (componentName && currentComponent === componentName) {
        if (routeName === 'control.admin.medicines.faktur') {
            try {
                if (route().current(routeName)) return true;
                if (currentUrl.includes('/medicines/faktur')) return true;
            } catch {
                return false;
            }
        } else {
            return true;
        }
    }

    // 2. Pencocokan rute absolut dari Ziggy dengan dukungan wildcard route().current('nama_rute.*')
    if (routeName) {
        try {
            if (route().current(routeName)) return true;
            const baseRoute = routeName.endsWith('.index') ? routeName.replace(/\.index$/, '') : routeName;
            if (route().current(`${baseRoute}.*`)) return true;
        } catch {
            // fallback
        }
    }

    return false;
};

const safeRoute = (routeName) => {
    try { return route(routeName); } catch { return '#'; }
};

const userDashboardRoute = computed(() => {
    if (userRole.value === 'admin') return safeRoute('control.admin.dashboard');
    if (userRole.value === 'apoteker') return safeRoute('control.apoteker.dashboard');
    if (userRole.value === 'kasir') return safeRoute('control.kasir.queue');
    return safeRoute('dashboard');
});

const activePageTitle = computed(() => {
    if (page.props.title) return page.props.title;
    for (const group of currentNav.value) {
        for (const item of group.items) {
            if (isRouteActive(item)) return item.name;
            if (item.children) {
                for (const child of item.children) {
                    if (isRouteActive(child)) return child.name;
                }
            }
        }
    }
    const specialRoutes = {
        'control.admin.medicines.faktur': 'Katalog Faktur',
        'control.admin.stock-opname.show': 'Detail Stock Opname',
        'control.apoteker.submissions.show': 'Detail Racikan',
        'control.apoteker.prescriptions.show': 'Detail Resep',
        'control.apoteker.prescriptions.create': 'Input Resep Baru',
        'control.apoteker.reports.sipnap': 'Laporan SIPNAP',
        'control.kasir.checkout': 'Pembayaran Kasir',
        'control.kasir.receipt': 'Struk Penjualan',
        'control.admin.stock-logs': 'Mutasi & Audit',
        'control.admin.audit-trails': 'Mutasi & Audit',
        'profile.edit': 'Pengaturan Akun',
    };
    for (const [rName, title] of Object.entries(specialRoutes)) {
        if (isRouteActive(rName)) return title;
    }
    return '';
});

// Auto-expand accordion if child is currently active
const checkActiveAccordions = () => {
    for (const group of currentNav.value) {
        for (const item of group.items) {
            if (item.children && item.key) {
                if (item.children.some(c => isRouteActive(c))) {
                    openAccordions.value[item.key] = true;
                }
            }
        }
    }
};

watch(() => page.url, () => {
    checkActiveAccordions();
}, { immediate: true });

// Flash Messages (Global Shadcn Floating Toast Listener)
watch(() => page.props.flash?.success, (newVal) => {
    if (newVal) {
        toast.success('Berhasil', { description: newVal });
    }
}, { immediate: true });

watch(() => page.props.flash?.error, (newVal) => {
    if (newVal) {
        toast.error('Gagal', { description: newVal });
    }
}, { immediate: true });

watch(() => page.props.flash?.warning, (newVal) => {
    if (newVal) {
        toast.warning('Peringatan', { description: newVal });
    }
}, { immediate: true });

watch(() => page.props.flash?.info, (newVal) => {
    if (newVal) {
        toast.info('Informasi', { description: newVal });
    }
}, { immediate: true });
</script>

<template>
    <div class="h-screen bg-background flex font-sans text-foreground overflow-hidden">

        <!-- ═══ DESKTOP SIDEBAR ═══ -->
        <aside
            class="hidden lg:flex flex-col h-screen bg-[#111827] border-r border-[#1F2937] transition-all duration-300 ease-in-out z-30 sticky top-0"
            :class="isDesktopCollapsed ? 'w-16' : 'w-64'"
        >
            <!-- Logo -->
            <Link 
                :href="userDashboardRoute" 
                prefetch 
                class="h-16 md:h-[72px] lg:h-[76px] flex items-center bg-[#0F172A] border-b border-[#1F2937] shrink-0 hover:opacity-95 transition-all"
                :class="isDesktopCollapsed ? 'justify-center px-2' : 'px-4 gap-3'"
            >
                <img src="/images/logo-clean.webp" alt="Logo Apotek Kinanthi" class="w-11 h-11 lg:w-12 lg:h-12 aspect-square object-contain shrink-0" />
                <span v-show="!isDesktopCollapsed" class="font-bold text-base lg:text-[17px] tracking-tight text-white truncate">Apotek Kinanthi</span>
            </Link>

            <!-- Navigation -->
            <ScrollArea class="flex-1 px-3 py-3">
                <div v-for="section in currentNav" :key="section.group" class="mb-4">
                    <p v-show="!isDesktopCollapsed" class="px-2 mb-1.5 text-xs font-semibold uppercase tracking-wider text-[#94A3B8]">{{ section.group }}</p>
                    <div v-show="isDesktopCollapsed" class="h-px bg-[#1F2937] mx-2 mb-2"></div>

                    <div class="space-y-0.5">
                        <template v-for="item in section.items" :key="item.name">
                            <!-- Simple Link (no children) -->
                            <Link
                                v-if="!item.children"
                                :href="safeRoute(item.href)"
                                prefetch="hover"
                                cache-for="1m"
                                class="flex items-center gap-2.5 px-2.5 py-2 rounded-md text-sm font-medium transition-colors group relative"
                                :class="isRouteActive(item)
                                    ? 'bg-[#1E293B] text-white font-semibold'
                                    : 'text-[#CBD5E1] hover:bg-[#1E293B] hover:text-white'"
                                :title="isDesktopCollapsed ? item.name : ''"
                            >
                                <svg class="w-4 h-4 shrink-0 transition-colors" :class="isRouteActive(item) ? 'text-[#60A5FA]' : 'text-[#94A3B8] group-hover:text-[#60A5FA]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                </svg>
                                <span v-show="!isDesktopCollapsed" class="truncate">{{ item.name }}</span>
                                <div v-if="isRouteActive(item) && !isDesktopCollapsed" class="absolute right-2 w-1.5 h-1.5 rounded-full bg-[#2563EB]"></div>
                            </Link>

                            <!-- Accordion (has children) -->
                            <div v-else>
                                <button
                                    @click="toggleAccordion(item.key)"
                                    class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-md text-sm font-medium transition-colors group"
                                    :class="item.children?.some(c => isRouteActive(c))
                                        ? 'text-white font-semibold'
                                        : 'text-[#CBD5E1] hover:bg-[#1E293B] hover:text-white'"
                                    :title="isDesktopCollapsed ? item.name : ''"
                                >
                                    <svg class="w-4 h-4 shrink-0 transition-colors" :class="item.children?.some(c => isRouteActive(c)) ? 'text-[#60A5FA]' : 'text-[#94A3B8] group-hover:text-[#60A5FA]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                    <span v-show="!isDesktopCollapsed" class="truncate flex-1 text-left">{{ item.name }}</span>
                                    <svg v-show="!isDesktopCollapsed" class="w-3.5 h-3.5 text-[#94A3B8] transition-transform duration-200" :class="openAccordions[item.key] ? 'rotate-180 text-[#60A5FA]' : 'group-hover:text-[#60A5FA]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="openAccordions[item.key] && !isDesktopCollapsed" class="ml-4 pl-2 border-l border-[#1F2937] mt-1 space-y-0.5">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.name"
                                        :href="safeRoute(child.href)"
                                        prefetch="hover"
                                        cache-for="1m"
                                        class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors"
                                        :class="isRouteActive(child)
                                            ? 'bg-[#1E293B] text-white font-semibold'
                                            : 'text-[#CBD5E1] hover:text-white hover:bg-[#1E293B]'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full transition-colors" :class="isRouteActive(child) ? 'bg-[#2563EB]' : 'bg-[#475569]'"></span>
                                        <span class="truncate">{{ child.name }}</span>
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </ScrollArea>

            <!-- Minimalist Sidebar Footer: Profile Dropdown & Collapse Control -->
            <div class="border-t border-[#1F2937] p-2 shrink-0 bg-[#0F172A]">
                <div class="flex items-center gap-1">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                class="h-10 w-full justify-start gap-2.5 px-2 hover:bg-[#1E293B]"
                                :class="isDesktopCollapsed ? 'justify-center px-0 w-10 mx-auto' : ''"
                                :title="isDesktopCollapsed ? user.name : ''"
                            >
                                <Avatar class="w-8 h-8 rounded-full border border-slate-600/50 bg-slate-800/80 shrink-0 shadow-sm">
                                    <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                    <AvatarFallback class="bg-slate-700/80 text-slate-200 font-bold text-xs">
                                        {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </AvatarFallback>
                                </Avatar>
                                <div v-show="!isDesktopCollapsed" class="text-left flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-white truncate leading-tight">{{ user.name }}</p>
                                    <p class="text-xs text-[#94A3B8] truncate capitalize leading-tight">{{ roleLabel }}</p>
                                </div>
                                <svg v-show="!isDesktopCollapsed" class="w-3.5 h-3.5 text-[#94A3B8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                </svg>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent :side="isDesktopCollapsed ? 'right' : 'top'" :align="isDesktopCollapsed ? 'start' : 'center'" class="w-56">
                            <DropdownMenuLabel class="font-normal">
                                <p class="font-semibold text-sm text-foreground leading-tight">{{ user.name }}</p>
                                <p class="text-muted-foreground text-xs truncate mt-0.5">{{ user.email || roleLabel }}</p>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="safeRoute('profile.edit')" prefetch class="w-full cursor-pointer text-sm flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    Profil Akun
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="userRole === 'admin'" as-child>
                                <Link :href="safeRoute('control.admin.settings')" prefetch class="w-full cursor-pointer text-sm flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
                                    Pengaturan Sistem
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child class="text-destructive focus:text-destructive cursor-pointer text-sm">
                                <Link :href="safeRoute('logout')" method="post" as="button" class="w-full text-left flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                    Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        v-show="!isDesktopCollapsed"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-[#94A3B8] hover:text-white hover:bg-[#1E293B] shrink-0"
                        @click="isDesktopCollapsed = true"
                        title="Ciutkan Sidebar"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                    </Button>
                </div>
                <div v-show="isDesktopCollapsed" class="mt-1 flex justify-center">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 text-[#94A3B8] hover:text-white hover:bg-[#1E293B]"
                        @click="isDesktopCollapsed = false"
                        title="Perluas Sidebar"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </Button>
                </div>
            </div>
        </aside>

        <!-- ═══ MOBILE DRAWER (SHADCN SHEET) ═══ -->
        <Sheet v-model:open="isMobileSidebarOpen">
            <SheetContent side="left" class="w-72 p-0 flex flex-col bg-[#111827] border-[#1F2937]">
                <SheetHeader class="h-16 px-4 bg-[#0F172A] border-b border-[#1F2937] flex flex-row items-center justify-between shrink-0">
                    <Link :href="userDashboardRoute" prefetch @click="isMobileSidebarOpen = false" class="flex items-center gap-3">
                        <img src="/images/logo-clean.webp" alt="Logo Apotek Kinanthi" class="w-11 h-11 aspect-square object-contain shrink-0" />
                        <SheetTitle class="text-base font-bold tracking-tight text-white">Apotek Kinanthi</SheetTitle>
                    </Link>
                </SheetHeader>
                <ScrollArea class="flex-1 px-3 py-3">
                    <div v-for="section in currentNav" :key="section.group" class="mb-4">
                        <p class="px-2 mb-1.5 text-xs font-semibold uppercase tracking-wider text-[#94A3B8]">{{ section.group }}</p>
                        <div class="space-y-0.5">
                            <template v-for="item in section.items" :key="item.name">
                                <Link
                                    v-if="!item.children"
                                    :href="safeRoute(item.href)"
                                    prefetch="hover"
                                    cache-for="1m"
                                    @click="isMobileSidebarOpen = false"
                                    class="flex items-center gap-2.5 px-2.5 py-2 rounded-md text-sm font-medium transition-colors"
                                    :class="isRouteActive(item)
                                        ? 'bg-[#1E293B] text-white font-semibold'
                                        : 'text-[#CBD5E1] hover:bg-[#1E293B] hover:text-white'"
                                >
                                    <svg class="w-4 h-4 shrink-0 transition-colors" :class="isRouteActive(item) ? 'text-[#60A5FA]' : 'text-[#94A3B8]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                    <span class="truncate">{{ item.name }}</span>
                                    <div v-if="isRouteActive(item)" class="ml-auto w-1.5 h-1.5 rounded-full bg-[#2563EB]"></div>
                                </Link>
                                <div v-else>
                                    <button
                                        @click="toggleAccordion(item.key)"
                                        class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-md text-sm font-medium transition-colors"
                                        :class="item.children?.some(c => isRouteActive(c))
                                            ? 'text-white font-semibold'
                                            : 'text-[#CBD5E1] hover:bg-[#1E293B] hover:text-white'"
                                    >
                                        <svg class="w-4 h-4 shrink-0 transition-colors" :class="item.children?.some(c => isRouteActive(c)) ? 'text-[#60A5FA]' : 'text-[#94A3B8]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                        </svg>
                                        <span class="truncate flex-1 text-left">{{ item.name }}</span>
                                        <svg class="w-3.5 h-3.5 text-[#94A3B8] transition-transform duration-200" :class="openAccordions[item.key] ? 'rotate-180 text-[#60A5FA]' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div v-show="openAccordions[item.key]" class="ml-4 pl-2 border-l border-[#1F2937] mt-1 space-y-0.5">
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.name"
                                            :href="safeRoute(child.href)"
                                            prefetch="hover"
                                            cache-for="1m"
                                            @click="isMobileSidebarOpen = false"
                                            class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors"
                                            :class="isRouteActive(child)
                                                ? 'bg-[#1E293B] text-white font-semibold'
                                                : 'text-[#CBD5E1] hover:text-white hover:bg-[#1E293B]'"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="isRouteActive(child) ? 'bg-[#2563EB]' : 'bg-[#475569]'"></span>
                                            <span class="truncate">{{ child.name }}</span>
                                        </Link>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </ScrollArea>
                <div class="p-2 border-t border-[#1F2937] bg-[#0F172A]">
                    <div class="flex items-center gap-2 p-1.5 rounded-md bg-[#111827]/60">
                        <Avatar class="w-8 h-8 rounded-full border border-slate-600/50 bg-slate-800/80 shrink-0 shadow-sm">
                            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                            <AvatarFallback class="bg-slate-700/80 text-slate-200 font-bold text-xs">
                                {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-white truncate leading-tight">{{ user.name }}</p>
                            <p class="text-xs text-[#94A3B8] truncate capitalize leading-tight">{{ roleLabel }}</p>
                        </div>
                        <Button variant="ghost" size="icon" as-child class="h-8 w-8 text-[#94A3B8] hover:text-destructive hover:bg-destructive/10" title="Keluar">
                            <Link :href="safeRoute('logout')" method="post" as="button">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </Link>
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- ═══ MAIN CONTENT AREA ═══ -->
        <div class="flex-1 flex flex-col min-w-0 relative">
            <!-- Top Bar / Application Header -->
            <header class="h-16 md:h-[72px] lg:h-[76px] bg-white/95 dark:bg-card/95 backdrop-blur-sm border-b border-slate-200/80 dark:border-border/80 shadow-xs flex items-center justify-between px-4 sm:px-5 md:px-6 lg:px-8 shrink-0 sticky top-0 z-20 transition-all">
                <!-- Left: Title / Mobile Toggle -->
                <div class="flex items-center gap-3 min-w-0">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="lg:hidden h-9 w-9 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-md shrink-0"
                        @click="isMobileSidebarOpen = true"
                        title="Buka Menu"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </Button>
                    <div v-if="$slots['header-title'] || activePageTitle" class="flex items-center min-w-0">
                        <h1 class="text-[18px] md:text-[19px] lg:text-[20px] font-semibold tracking-tight text-[#0F172A] leading-tight truncate">
                            <slot name="header-title">{{ activePageTitle }}</slot>
                        </h1>
                    </div>
                </div>

                <!-- Right: Clean Minimalist Role Badge (Contextual Status) -->
                <div class="flex items-center gap-2 shrink-0">
                    <div class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border border-[#E2E8F0] bg-[#FFFFFF] text-[#64748B] shadow-[0_1px_2px_rgba(0,0,0,0.03)] select-none">
                        {{ roleLabel }}
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main id="main-content-scroll" class="flex-1 overflow-y-auto p-4 md:p-6 lg:px-8 lg:pt-8 lg:pb-16 bg-background custom-scrollbar">
                <div class="max-w-[1600px] mx-auto">
                    <slot></slot>
                </div>
            </main>
        </div>

        <!-- Global Shadcn Floating Toast System -->
        <Toaster />
    </div>
</template>
