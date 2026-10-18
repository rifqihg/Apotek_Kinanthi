<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    kpi: {
        type: Object,
        default: () => ({
            rx_menunggu: 0,
            rx_diproses: 0,
            rx_siap_ambil: 0,
            rx_selesai_hari_ini: 0,
            bud_alerts: 0,
            compounding_queue: 0,
            raw_material_alerts: 0,
            sipnap_count: 0,
        })
    },
    prescriptionTrend: {
        type: Array,
        default: () => []
    },
    currentDays: {
        type: [String, Number],
        default: '7'
    },
    compoundingDistribution: {
        type: Array,
        default: () => []
    },
    activePrescriptions: {
        type: Array,
        default: () => []
    },
    activeSubmissions: {
        type: Array,
        default: () => []
    },
    rawMaterialAlerts: {
        type: Array,
        default: () => []
    }
});

// Segmented Tab for Unified Operational Queue (Mobile-friendly, zero vertical clutter)
const activeMonitorTab = ref('prescriptions'); // 'prescriptions' | 'compounding'

// Period filter options for Prescription Trend Chart
const periodOptions = [
    { value: '1D', label: '1 Hari Berjalan' },
    { value: '7', label: '7 Hari Terakhir' },
    { value: '30', label: '30 Hari Terakhir' },
    { value: 'MTD', label: 'Bulan Ini' },
];

const selectedDays = ref(String(props.currentDays || '7'));
watch(() => props.currentDays, (v) => { if (v) selectedDays.value = String(v); });

const isHourly = computed(() => String(selectedDays.value) === '1D');

const handlePeriodChange = (val) => {
    selectedDays.value = String(val);
    router.get(route('control.apoteker.dashboard'), { days: val }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['prescriptionTrend', 'currentDays'],
    });
};

// 60 FPS Left-to-Right Draw Animation Engine (easeOutCubic)
const animProgress = ref(0);
let animFrameId = null;

function runDrawAnimation(duration = 750) {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    const start = performance.now();
    animProgress.value = 0;

    function step(now) {
        const elapsed = now - start;
        const progress = Math.min(1, elapsed / duration);
        animProgress.value = 1 - Math.pow(1 - progress, 3);

        if (progress < 1) {
            animFrameId = requestAnimationFrame(step);
        } else {
            animProgress.value = 1;
        }
    }

    animFrameId = requestAnimationFrame(step);
}

onMounted(() => runDrawAnimation(800));
onUnmounted(() => { if (animFrameId) cancelAnimationFrame(animFrameId); });
watch(() => props.prescriptionTrend, () => runDrawAnimation(750), { deep: true });

// Chart Container & Dimensions
const containerRef = ref(null);
const hoveredPoint = ref(null);
const chartWidth = 700;
const chartHeight = 200;
const chartMargin = { top: 20, right: 30, bottom: 30, left: 35 };
const innerWidth = chartWidth - chartMargin.left - chartMargin.right;
const innerHeight = chartHeight - chartMargin.top - chartMargin.bottom;

function getNiceMax(val, defaultMin = 5) {
    if (val <= 0) return defaultMin;
    const exp = Math.pow(10, Math.floor(Math.log10(val)));
    const frac = val / exp;
    let niceFrac = 10;
    if (frac <= 1.5) niceFrac = 2;
    else if (frac <= 3) niceFrac = 3;
    else if (frac <= 5) niceFrac = 5;
    return niceFrac * exp;
}

const maxTrendVal = computed(() => {
    const data = props.prescriptionTrend || [];
    const max = Math.max(...data.map(d => Math.max(d.total || 0, d.selesai || 0)), 0);
    return getNiceMax(max, 5);
});

const yTicks = computed(() => {
    return [0, 0.5, 1].map((frac) => ({
        y: chartMargin.top + innerHeight * (1 - frac),
        label: String(Math.round(maxTrendVal.value * frac)),
    }));
});

const chartPoints = computed(() => {
    const data = props.prescriptionTrend || [];
    const n = data.length;
    const denom = maxTrendVal.value || 5;

    return data.map((item, i) => {
        const x = chartMargin.left + (n > 1 ? (i / (n - 1)) * innerWidth : innerWidth / 2);
        const yTotal = chartMargin.top + innerHeight * (1 - Math.min(1, Math.max(0, (Number(item.total) || 0) / denom)));
        const ySelesai = chartMargin.top + innerHeight * (1 - Math.min(1, Math.max(0, (Number(item.selesai) || 0) / denom)));
        return {
            x,
            yTotal,
            ySelesai,
            data: item,
        };
    });
});

// Fritsch-Carlson Monotone Spline Generator for smooth curves
function generateMonotoneSpline(pts, yKey) {
    const valid = pts.filter(p => typeof p.x === 'number' && !isNaN(p.x) && typeof p[yKey] === 'number' && !isNaN(p[yKey]));
    if (valid.length <= 1) return valid.length === 1 ? `M ${valid[0].x.toFixed(1)} ${valid[0][yKey].toFixed(1)}` : '';
    if (valid.length === 2) return `M ${valid[0].x.toFixed(1)} ${valid[0][yKey].toFixed(1)} L ${valid[1].x.toFixed(1)} ${valid[1][yKey].toFixed(1)}`;

    const n = valid.length;
    const dx = [];
    const dy = [];
    const m = [];
    for (let i = 0; i < n - 1; i++) {
        const dX = (valid[i + 1].x - valid[i].x) || 1;
        const dY = valid[i + 1][yKey] - valid[i][yKey];
        dx.push(dX);
        dy.push(dY);
        m.push(dY / dX);
    }

    const tangents = [m[0]];
    for (let i = 1; i < n - 1; i++) {
        tangents.push(m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2);
    }
    tangents.push(m[m.length - 1]);

    let path = `M ${valid[0].x.toFixed(1)} ${valid[0][yKey].toFixed(1)}`;
    for (let i = 0; i < n - 1; i++) {
        const d = dx[i] / 3;
        const cp1x = valid[i].x + d;
        const cp1y = valid[i][yKey] + tangents[i] * d;
        const cp2x = valid[i + 1].x - d;
        const cp2y = valid[i + 1][yKey] - tangents[i + 1] * d;
        path += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${valid[i + 1].x.toFixed(1)} ${valid[i + 1][yKey].toFixed(1)}`;
    }
    return path;
}

function generateLinePath(pts, yKey) {
    if (!pts || pts.length === 0) return '';
    if (isHourly.value && pts.length > 50) {
        let path = `M ${pts[0].x.toFixed(1)} ${pts[0][yKey].toFixed(1)}`;
        for (let i = 1; i < pts.length; i++) {
            path += ` L ${pts[i].x.toFixed(1)} ${pts[i][yKey].toFixed(1)}`;
        }
        return path;
    }
    return generateMonotoneSpline(pts, yKey);
}

const totalLinePath = computed(() => generateLinePath(chartPoints.value, 'yTotal'));
const selesaiLinePath = computed(() => generateLinePath(chartPoints.value, 'ySelesai'));

const totalAreaPath = computed(() => {
    const line = totalLinePath.value;
    const pts = chartPoints.value;
    if (!line || pts.length === 0) return '';
    const baseLine = chartMargin.top + innerHeight;
    return `${line} L ${pts[pts.length - 1].x.toFixed(1)} ${baseLine.toFixed(1)} L ${pts[0].x.toFixed(1)} ${baseLine.toFixed(1)} Z`;
});

// Sumbu X: 08:00 - 22:00 genap untuk 1D, atau tanggal untuk harian
const sampledXAxis = computed(() => {
    const pts = chartPoints.value;
    if (!pts || pts.length === 0) return [];
    if (isHourly.value) {
        const hours = pts.filter((p) => {
            const lbl = p.data?.label || '';
            if (!lbl.endsWith(':00')) return false;
            const h = parseInt(lbl.split(':')[0], 10);
            return h % 2 === 0;
        });
        if (hours.length > 0) {
            return hours.map(p => ({ x: p.x, label: p.data.label }));
        }
    }
    if (pts.length <= 8) return pts.map(p => ({ x: p.x, label: p.data.label.includes(',') ? p.data.label.split(',')[0] : p.data.label }));
    const step = Math.ceil(pts.length / 7);
    return pts.filter((_, i) => i % step === 0 || i === pts.length - 1).map(p => ({
        x: p.x,
        label: p.data.label.includes(',') ? p.data.label.split(',')[0] : p.data.label
    }));
});

function onMouseMove(event) {
    if (!containerRef.value || chartPoints.value.length === 0) return;
    const rect = containerRef.value.getBoundingClientRect();
    const svgX = (event.clientX - rect.left) * (chartWidth / rect.width);
    const n = chartPoints.value.length;
    if (isHourly.value && n > 1) {
        const clampedX = Math.max(chartMargin.left, Math.min(chartMargin.left + innerWidth, svgX));
        const ratio = (clampedX - chartMargin.left) / innerWidth;
        const index = Math.round(ratio * (n - 1));
        hoveredPoint.value = chartPoints.value[Math.max(0, Math.min(n - 1, index))];
    } else {
        hoveredPoint.value = chartPoints.value.reduce((prev, curr) =>
            Math.abs(curr.x - svgX) < Math.abs(prev.x - svgX) ? curr : prev
        );
    }
}

const tooltipStyle = computed(() => {
    if (!hoveredPoint.value) return {};
    const percentX = (hoveredPoint.value.x / chartWidth) * 100;
    return {
        left: `${percentX}%`,
        top: '15px',
        transform: percentX > 65 ? 'translateX(-105%)' : 'translateX(10%)',
    };
});

// Total compounding count for distribution percentage calculation
const totalCompoundingCount = computed(() => {
    return props.compoundingDistribution.reduce((acc, curr) => acc + (Number(curr.count) || 0), 0);
});

// Curated palette for Donut chart (5 items max)
const unitHexColors = ['#6366F1', '#10B981', '#F59E0B', '#0EA5E9', '#8B5CF6'];
const unitColors = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-purple-500'];

// Donut Chart math properties
const donutRadius = 55;
const donutStrokeWidth = 20;
const donutCircumference = 2 * Math.PI * donutRadius; // ~345.575

const donutSlices = computed(() => {
    if (!props.compoundingDistribution.length || totalCompoundingCount.value === 0) {
        return [];
    }

    let accumulatedPercent = 0;
    return props.compoundingDistribution.map((item, index) => {
        const percent = (Number(item.count) || 0) / totalCompoundingCount.value;
        const strokeDasharray = `${percent * donutCircumference} ${donutCircumference}`;
        const strokeDashoffset = -accumulatedPercent * donutCircumference;
        accumulatedPercent += percent;

        return {
            ...item,
            percent: Math.round(percent * 100),
            strokeDasharray,
            strokeDashoffset,
            color: unitHexColors[index % unitHexColors.length],
            bgClass: unitColors[index % unitColors.length],
        };
    });
});
</script>

<template>
    <Head title="Dashboard Apoteker" />

    <div class="space-y-6">
        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- ROW 1: LAYANAN RESEP HARI INI (4 CARDS)                     -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="space-y-3">
            <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Layanan Resep Hari Ini
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Menunggu Skrining -->
                <Link
                    :href="route('control.apoteker.prescriptions.index', { status: 'menunggu' })"
                    prefetch
                    cache-for="1m"
                    class="block focus:outline-none select-none"
                >
                    <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-indigo-400/60 dark:hover:border-indigo-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                        <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Menunggu Skrining
                            </CardTitle>
                            <div class="w-8 h-8 rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ kpi.rx_menunggu }}</span>
                                <span class="text-xs font-semibold uppercase text-muted-foreground">Resep</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">Antrean baru perlu telaah klinis & administratif</p>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Card 2: Sedang Diracik -->
                <Link
                    :href="route('control.apoteker.prescriptions.index', { status: 'diproses' })"
                    prefetch
                    cache-for="1m"
                    class="block focus:outline-none select-none"
                >
                    <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-amber-400/60 dark:hover:border-amber-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                        <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Sedang Diracik
                            </CardTitle>
                            <div class="w-8 h-8 rounded-md bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl lg:text-3xl font-semibold tracking-tight" :class="kpi.rx_diproses > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'">{{ kpi.rx_diproses }}</span>
                                <span class="text-xs font-semibold uppercase" :class="kpi.rx_diproses > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">Resep</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">Dalam proses penimbangan & peracikan sediaan</p>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Card 3: Siap Diserahkan -->
                <Link
                    :href="route('control.apoteker.prescriptions.index', { status: 'siap_ambil' })"
                    prefetch
                    cache-for="1m"
                    class="block focus:outline-none select-none"
                >
                    <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-emerald-400/60 dark:hover:border-emerald-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                        <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Siap Diserahkan
                            </CardTitle>
                            <div class="w-8 h-8 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl lg:text-3xl font-semibold tracking-tight" :class="kpi.rx_siap_ambil > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">{{ kpi.rx_siap_ambil }}</span>
                                <span class="text-xs font-semibold uppercase" :class="kpi.rx_siap_ambil > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">Resep</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">Sediaan selesai dikemas, siap konseling KIE</p>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Card 4: Resep Selesai Hari Ini -->
                <Link
                    :href="route('control.apoteker.prescriptions.index', { status: 'selesai' })"
                    prefetch
                    cache-for="1m"
                    class="block focus:outline-none select-none"
                >
                    <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-blue-400/60 dark:hover:border-blue-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                        <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Resep Selesai Hari Ini
                            </CardTitle>
                            <div class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ kpi.rx_selesai_hari_ini }}</span>
                                <span class="text-xs font-semibold uppercase text-muted-foreground">Resep</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">Tervalidasi & diserahkan ke pasien</p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- ROW 2: MUTU FARMASI & KEPATUHAN LEGAL (4 CARDS)             -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Card 5: Peringatan BUD (< 7 Hari) -->
            <Link
                :href="route('control.apoteker.bud-tracker')"
                prefetch
                cache-for="1m"
                class="block focus:outline-none select-none"
            >
                <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-rose-400/60 dark:hover:border-rose-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full"
                      :class="{ 'border-rose-500/30 bg-rose-500/5': kpi.bud_alerts > 0 }">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider" :class="kpi.bud_alerts > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'">
                            Peringatan BUD
                        </CardTitle>
                        <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0" :class="kpi.bud_alerts > 0 ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight" :class="kpi.bud_alerts > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'">{{ kpi.bud_alerts }}</span>
                            <span class="text-xs font-semibold uppercase" :class="kpi.bud_alerts > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'">Batch</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Beyond Use Date kedaluwarsa &le; 7 hari</p>
                    </CardContent>
                </Card>
            </Link>

            <!-- Card 6: Antrean Lab Racikan (Compounding) -->
            <Link
                :href="route('control.apoteker.submissions')"
                prefetch
                cache-for="1m"
                class="block focus:outline-none select-none"
            >
                <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-purple-400/60 dark:hover:border-purple-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Antrean Lab Racikan
                        </CardTitle>
                        <div class="w-8 h-8 rounded-md bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ kpi.compounding_queue }}</span>
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Batch</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Verifikasi fisik / penetapan harga admin</p>
                    </CardContent>
                </Card>
            </Link>

            <!-- Card 7: Bahan Baku Kritis -->
            <Link
                :href="route('control.apoteker.stock-check')"
                prefetch
                cache-for="1m"
                class="block focus:outline-none select-none"
            >
                <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-orange-400/60 dark:hover:border-orange-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full"
                      :class="{ 'border-orange-500/30 bg-orange-500/5': kpi.raw_material_alerts > 0 }">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold uppercase tracking-wider" :class="kpi.raw_material_alerts > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-muted-foreground'">
                            Bahan Baku Kritis
                        </CardTitle>
                        <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0" :class="kpi.raw_material_alerts > 0 ? 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight" :class="kpi.raw_material_alerts > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-foreground'">{{ kpi.raw_material_alerts }}</span>
                            <span class="text-xs font-semibold uppercase" :class="kpi.raw_material_alerts > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-muted-foreground'">Bahan</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Stok di bawah batas minimum formularium</p>
                    </CardContent>
                </Card>
            </Link>

            <!-- Card 8: Monitoring SIPNAP (Narkotika/Psikotropika) -->
            <Link
                :href="route('control.apoteker.reports.sipnap')"
                prefetch
                cache-for="1m"
                class="block focus:outline-none select-none"
            >
                <Card class="bg-card border-border shadow-sm transition-all duration-150 hover:border-cyan-400/60 dark:hover:border-cyan-500/50 active:scale-[0.985] active:brightness-95 cursor-pointer h-full">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Monitoring SIPNAP
                        </CardTitle>
                        <div class="w-8 h-8 rounded-md bg-cyan-50 text-cyan-600 dark:bg-cyan-950/40 dark:text-cyan-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl lg:text-3xl font-semibold tracking-tight text-foreground">{{ kpi.sipnap_count }}</span>
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Item Regulasi</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Obat Narkotika & Psikotropika terkontrol</p>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- VISUAL ANALYTICS: TREN RESEP & PROPORSI SEDIAAN RACIKAN    -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Tren Beban Layanan Resep (8 Cols) -->
            <Card class="lg:col-span-8 shadow-sm border-border bg-card overflow-hidden">
                <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-border">
                    <div>
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground flex items-center gap-2">
                            <span>Tren Layanan Resep</span>
                            <span class="text-[10px] font-normal px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                Live Data
                            </span>
                        </CardTitle>
                        <CardDescription class="text-xs text-muted-foreground mt-0.5">
                            {{ isHourly ? 'Dinamika resep masuk per menit jam operasional (08:00 - 22:00) vs tuntas diserahkan' : 'Dinamika volume resep harian masuk vs tuntas diserahkan' }}
                        </CardDescription>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <!-- Legend Indicators -->
                        <div class="hidden sm:flex items-center gap-3 text-xs text-muted-foreground mr-1">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                <span>Masuk</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span>Selesai</span>
                            </div>
                        </div>

                        <!-- Filter Dropdown Shadcn Select -->
                        <Select :model-value="String(selectedDays)" @update:model-value="handlePeriodChange">
                            <SelectTrigger class="w-[145px] h-8 text-xs font-medium border-border bg-background transition-colors hover:bg-muted/50 cursor-pointer">
                                <SelectValue placeholder="Pilih Periode" />
                            </SelectTrigger>
                            <SelectContent class="border-border bg-popover text-popover-foreground shadow-md">
                                <SelectItem v-for="opt in periodOptions" :key="opt.value" :value="opt.value" class="text-xs cursor-pointer">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>
                <CardContent class="p-4 sm:p-6">
                    <!-- SVG Area Chart Container with Hardware Acceleration & Smooth Mousemove -->
                    <div
                        class="relative w-full h-[220px] select-none"
                        ref="containerRef"
                        style="transform: translateZ(0); will-change: transform;"
                        @mousemove="onMouseMove"
                        @mouseleave="hoveredPoint = null"
                    >
                        <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="w-full h-full overflow-visible" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="rxTrendGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6366F1" stop-opacity="0.22" />
                                    <stop offset="100%" stop-color="#6366F1" stop-opacity="0.0" />
                                </linearGradient>

                                <clipPath id="rxTrendSweepClip">
                                    <rect
                                        :x="chartMargin.left - 5"
                                        y="0"
                                        :width="Math.max(0, (innerWidth + 10) * animProgress)"
                                        :height="chartHeight"
                                    />
                                </clipPath>
                            </defs>

                            <!-- Horizontal Grid lines & Y-Axis Labels -->
                            <g v-for="(tick, idx) in yTicks" :key="'grid-' + idx">
                                <line
                                    :x1="chartMargin.left"
                                    :y1="tick.y"
                                    :x2="chartMargin.left + innerWidth"
                                    :y2="tick.y"
                                    stroke="currentColor"
                                    stroke-dasharray="3 3"
                                    class="text-border/60"
                                    stroke-width="1"
                                />
                                <text
                                    :x="chartMargin.left - 8"
                                    :y="tick.y + 3.5"
                                    text-anchor="end"
                                    class="text-[10px] font-medium fill-muted-foreground"
                                >
                                    {{ tick.label }}
                                </text>
                            </g>

                            <!-- Animated Left-to-Right Draw Layer -->
                            <g clip-path="url(#rxTrendSweepClip)">
                                <path v-if="totalAreaPath" :d="totalAreaPath" fill="url(#rxTrendGradient)" />
                                <path
                                    v-if="totalLinePath"
                                    :d="totalLinePath"
                                    fill="none"
                                    stroke="#6366F1"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    v-if="selesaiLinePath"
                                    :d="selesaiLinePath"
                                    fill="none"
                                    stroke="#10B981"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </g>

                            <!-- Hover Crosshair & Dots -->
                            <g v-if="hoveredPoint">
                                <line
                                    :x1="hoveredPoint.x"
                                    :y1="chartMargin.top"
                                    :x2="hoveredPoint.x"
                                    :y2="chartMargin.top + innerHeight"
                                    stroke="currentColor"
                                    stroke-dasharray="2 2"
                                    stroke-width="1.5"
                                    class="text-slate-400 dark:text-slate-500"
                                />
                                <circle
                                    :cx="hoveredPoint.x"
                                    :cy="hoveredPoint.yTotal"
                                    r="5.5"
                                    fill="#6366F1"
                                    stroke="#FFFFFF"
                                    stroke-width="2"
                                    class="drop-shadow-sm animate-pulse"
                                />
                                <circle
                                    :cx="hoveredPoint.x"
                                    :cy="hoveredPoint.ySelesai"
                                    r="4.5"
                                    fill="#10B981"
                                    stroke="#FFFFFF"
                                    stroke-width="2"
                                    class="drop-shadow-sm"
                                />
                            </g>

                            <!-- X-Axis Labels -->
                            <g v-for="(item, idx) in sampledXAxis" :key="'xaxis-' + idx">
                                <text
                                    :x="item.x"
                                    :y="chartMargin.top + innerHeight + 18"
                                    text-anchor="middle"
                                    class="text-[10px] font-medium fill-muted-foreground"
                                >
                                    {{ item.label }}
                                </text>
                            </g>
                        </svg>

                        <!-- Floating Tooltip Card Following Cursor -->
                        <div v-if="hoveredPoint" class="absolute pointer-events-none z-30 transition-all duration-75" :style="tooltipStyle">
                            <div class="bg-popover text-popover-foreground border border-border rounded-lg shadow-lg p-3 min-w-[170px] backdrop-blur-sm">
                                <p class="text-[11px] font-semibold text-foreground border-b border-border pb-1.5 mb-2 flex items-center justify-between">
                                    <span>{{ isHourly ? 'Pukul ' + hoveredPoint.data.label : hoveredPoint.data.label }}</span>
                                    <span class="text-[10px] font-normal text-muted-foreground">{{ isHourly ? 'Hari Ini' : hoveredPoint.data.date }}</span>
                                </p>
                                <div class="space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                            <span class="text-muted-foreground text-[11px]">Resep Masuk:</span>
                                        </div>
                                        <span class="font-semibold text-foreground tabular-nums">{{ hoveredPoint.data.total }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                            <span class="text-muted-foreground text-[11px]">Resep Selesai:</span>
                                        </div>
                                        <span class="font-semibold text-foreground tabular-nums">{{ hoveredPoint.data.selesai }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: Proporsi Sediaan Racikan Lab (SVG Donut Chart) (4 Cols) -->
            <Card class="lg:col-span-4 shadow-sm border-border bg-card flex flex-col justify-between overflow-hidden">
                <CardHeader class="pb-3 border-b border-border">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground flex items-center gap-2">
                            <span>Bentuk Sediaan Racikan</span>
                        </CardTitle>
                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                            Unit Lab
                        </span>
                    </div>
                    <CardDescription class="text-xs text-muted-foreground mt-0.5">
                        Distribusi kemasan batch racikan farmasi
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4 sm:p-6 flex-1 flex flex-col justify-center">
                    <div v-if="compoundingDistribution.length > 0" class="flex flex-col sm:flex-row items-center gap-5 justify-center py-1">
                        <!-- SVG Donut Chart -->
                        <div class="relative w-36 h-36 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 160 160" class="w-full h-full -rotate-90">
                                <!-- Background Track Ring -->
                                <circle
                                    cx="80"
                                    cy="80"
                                    :r="donutRadius"
                                    fill="transparent"
                                    stroke="currentColor"
                                    class="text-muted/30"
                                    :stroke-width="donutStrokeWidth"
                                />
                                <!-- Active Slices with Arc -->
                                <circle
                                    v-for="(slice, i) in donutSlices"
                                    :key="'donut-' + i"
                                    cx="80"
                                    cy="80"
                                    :r="donutRadius"
                                    fill="transparent"
                                    :stroke="slice.color"
                                    :stroke-width="donutStrokeWidth"
                                    :stroke-dasharray="slice.strokeDasharray"
                                    :stroke-dashoffset="slice.strokeDashoffset"
                                    class="transition-all duration-500"
                                />
                            </svg>
                            <!-- Center Total Counter -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                                <span class="text-2xl font-bold tracking-tight text-foreground leading-none">{{ totalCompoundingCount }}</span>
                                <span class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider mt-0.5">Batch</span>
                            </div>
                        </div>

                        <!-- Legend & Percentages List -->
                        <div class="flex-1 w-full space-y-2.5">
                            <div v-for="slice in donutSlices" :key="slice.unit" class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="slice.bgClass"></span>
                                    <span class="font-medium text-foreground">{{ slice.unit }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-foreground tabular-nums">{{ slice.count }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-muted text-muted-foreground border border-border">
                                        {{ slice.percent }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <p class="text-xs text-muted-foreground">Belum ada batch racikan tersimpan.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- UNIFIED OPERATIONAL MONITORING CARD (WITH TAB SWITCHER)     -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <Card class="overflow-hidden shadow-sm border-border bg-card">
            <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-border">
                <div class="flex items-center gap-3">
                    <!-- Segmented Button Tab 1: Antrean Resep -->
                    <button
                        type="button"
                        @click="activeMonitorTab = 'prescriptions'"
                        class="text-xs sm:text-sm font-semibold pb-1.5 border-b-2 transition-all duration-150 cursor-pointer px-1 flex items-center gap-2"
                        :class="activeMonitorTab === 'prescriptions'
                            ? 'border-indigo-600 text-foreground dark:border-indigo-400'
                            : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        <span>Antrean Resep</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                              :class="activeMonitorTab === 'prescriptions' ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'bg-muted text-muted-foreground'">
                            {{ activePrescriptions.length }}
                        </span>
                    </button>

                    <span class="text-muted-foreground/40 text-xs font-light">|</span>

                    <!-- Segmented Button Tab 2: Batch Lab Peracikan -->
                    <button
                        type="button"
                        @click="activeMonitorTab = 'compounding'"
                        class="text-xs sm:text-sm font-semibold pb-1.5 border-b-2 transition-all duration-150 cursor-pointer px-1 flex items-center gap-2"
                        :class="activeMonitorTab === 'compounding'
                            ? 'border-purple-600 text-foreground dark:border-purple-400'
                            : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        <span>Batch Lab Peracikan</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                              :class="activeMonitorTab === 'compounding' ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400' : 'bg-muted text-muted-foreground'">
                            {{ activeSubmissions.length }}
                        </span>
                    </button>
                </div>

                <!-- Direct Links According to Active Tab -->
                <div>
                    <Button variant="outline" size="sm" as-child class="h-8 text-xs font-medium border-border hover:bg-muted shadow-xs">
                        <Link
                            v-if="activeMonitorTab === 'prescriptions'"
                            :href="route('control.apoteker.prescriptions.index')"
                            prefetch
                            cache-for="1m"
                        >
                            Lihat Semua
                        </Link>
                        <Link
                            v-else
                            :href="route('control.apoteker.submissions')"
                            prefetch
                            cache-for="1m"
                        >
                            Lihat Semua
                        </Link>
                    </Button>
                </div>
            </CardHeader>

            <!-- Table 1 Content: Antrean Resep (5 Kolom Simetris & Horizontal Scroll Mobile) -->
            <div v-show="activeMonitorTab === 'prescriptions'" class="overflow-x-auto w-full">
                <table class="w-full text-left text-xs min-w-[720px]">
                    <thead>
                        <tr class="bg-muted/40 text-muted-foreground uppercase tracking-wider font-semibold border-b border-border">
                            <th class="px-4 py-2.5 min-w-[200px] whitespace-nowrap">No. Resep & Pasien</th>
                            <th class="px-4 py-2.5 min-w-[170px] whitespace-nowrap">Dokter & Faskes</th>
                            <th class="px-4 py-2.5 text-center min-w-[130px] whitespace-nowrap">Waktu & Jenis</th>
                            <th class="px-4 py-2.5 text-center min-w-[140px] whitespace-nowrap">Status Antrean</th>
                            <th class="px-4 py-2.5 text-right min-w-[80px] whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="rx in activePrescriptions" :key="rx.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 align-middle">
                                <span class="font-medium text-foreground block">{{ rx.prescription_number }}</span>
                                <span class="text-muted-foreground text-[11px] block mt-0.5">
                                    {{ rx.patient_name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <span class="font-medium text-foreground block">{{ rx.doctor_name }}</span>
                                <span class="text-muted-foreground text-[11px] block mt-0.5">{{ rx.clinic_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-center align-middle">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-foreground font-medium tabular-nums">{{ rx.created_at }} WIB</span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wider border whitespace-nowrap"
                                        :class="rx.has_racikan
                                            ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20'
                                            : 'bg-muted text-muted-foreground border-border'"
                                    >
                                        {{ rx.has_racikan ? 'Racikan' : 'Obat Jadi' }} · {{ rx.items_count }} Item
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center align-middle">
                                <span
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider border inline-block whitespace-nowrap"
                                    :class="{
                                        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20': rx.status === 'menunggu',
                                        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': rx.status === 'diproses',
                                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': rx.status === 'siap_ambil',
                                        'bg-muted text-muted-foreground border-border': !['menunggu', 'diproses', 'siap_ambil'].includes(rx.status)
                                    }"
                                >
                                    {{ rx.status === 'menunggu' ? 'Menunggu Skrining' :
                                       rx.status === 'diproses' ? 'Sedang Diracik' :
                                       rx.status === 'siap_ambil' ? 'Siap Ambil' : rx.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right align-middle">
                                <Button variant="outline" size="sm" as-child class="h-7 px-2.5 text-xs font-medium hover:bg-muted border-border shadow-2xs">
                                    <Link
                                        :href="route('control.apoteker.prescriptions.show', rx.id)"
                                        prefetch
                                        cache-for="1m"
                                    >
                                        Detail
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="activePrescriptions.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                Tidak ada antrean resep yang menunggu saat ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Table 2 Content: Monitor Batch Lab Compounding (5 Kolom Simetris & Horizontal Scroll Mobile) -->
            <div v-show="activeMonitorTab === 'compounding'" class="overflow-x-auto w-full">
                <table class="w-full text-left text-xs min-w-[720px]">
                    <thead>
                        <tr class="bg-muted/40 text-muted-foreground uppercase tracking-wider font-semibold border-b border-border">
                            <th class="px-4 py-2.5 min-w-[200px] whitespace-nowrap">Nama Racikan & ID</th>
                            <th class="px-4 py-2.5 min-w-[170px] whitespace-nowrap">Formula / Kategori</th>
                            <th class="px-4 py-2.5 text-center min-w-[130px] whitespace-nowrap">Target Kuantitas</th>
                            <th class="px-4 py-2.5 text-center min-w-[140px] whitespace-nowrap">Status Validasi</th>
                            <th class="px-4 py-2.5 text-right min-w-[80px] whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in activeSubmissions" :key="item.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 align-middle">
                                <span class="font-medium text-foreground block">{{ item.nama_racikan }}</span>
                                <span class="text-muted-foreground text-[11px] block mt-0.5 font-mono">ID-{{ item.id }}</span>
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <span class="text-xs px-2 py-0.5 bg-muted text-foreground rounded border border-border inline-block whitespace-nowrap">
                                    {{ item.formula?.nama_resep || 'Non-Formula' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center align-middle font-medium text-foreground">
                                <span class="tabular-nums font-semibold">{{ item.target_quantity }}</span>
                                <span class="text-muted-foreground text-xs ml-1">{{ item.target_unit }}</span>
                            </td>
                            <td class="px-4 py-3 text-center align-middle">
                                <span
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider border inline-block whitespace-nowrap"
                                    :class="{
                                        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': item.status === 'pending_final_verification',
                                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': item.status === 'active',
                                        'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20': item.status === 'rejected',
                                        'bg-muted text-muted-foreground border-border': !['pending_final_verification','active','rejected'].includes(item.status)
                                    }"
                                >
                                    {{ item.status === 'pending_final_verification' ? 'Menunggu Harga' :
                                       item.status === 'active' ? 'Aktif' :
                                       item.status === 'draft' ? 'Draf' :
                                       item.status === 'rejected' ? 'Ditolak' : item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right align-middle">
                                <Button variant="outline" size="sm" as-child class="h-7 px-2.5 text-xs font-medium hover:bg-muted border-border shadow-2xs">
                                    <Link
                                        :href="route('control.apoteker.submissions.show', item.id)"
                                        prefetch
                                        cache-for="1m"
                                    >
                                        Detail
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="activeSubmissions.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                Tidak ada antrean racikan lab saat ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- CONDITIONAL ALERT: BAHAN BAKU KRITIS DI BAWAH THRESHOLD   -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <div v-if="rawMaterialAlerts.length > 0" class="bg-card rounded-lg border border-orange-500/20 overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-orange-500/5">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-md bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-foreground text-sm tracking-tight">Peringatan Stok Bahan Baku di Bawah Ambang Batas</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Segera lakukan pengadaan atau penyesuaian formularium agar peracikan tidak terhambat.</p>
                    </div>
                </div>
                <Button variant="outline" size="sm" as-child class="h-8 px-3 text-xs font-medium border-orange-500/30 text-orange-600 dark:text-orange-400 hover:bg-orange-500/10">
                    <Link
                        :href="route('control.apoteker.stock-check')"
                        prefetch
                        cache-for="1m"
                    >
                        Cek Inventaris Bahan
                    </Link>
                </Button>
            </div>
            <div class="divide-y divide-border">
                <div v-for="mat in rawMaterialAlerts" :key="mat.id" class="px-5 py-3 flex items-center justify-between hover:bg-muted/30 transition-colors text-xs">
                    <div>
                        <span class="font-medium text-foreground text-sm">{{ mat.nama_bahan }}</span>
                        <div class="flex items-center gap-2 text-muted-foreground mt-0.5">
                            <span>Kode: {{ mat.kode_bahan }}</span>
                            <span>&bull;</span>
                            <span>Bentuk: {{ mat.bentuk_sediaan || '-' }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-baseline justify-end gap-1 font-semibold text-orange-600 dark:text-orange-400 text-sm">
                            <span>{{ Number(mat.stok_total).toLocaleString('id-ID') }}</span>
                            <span class="text-xs text-muted-foreground font-normal">{{ mat.satuan_dasar_penggunaan }}</span>
                        </div>
                        <span class="text-[11px] text-muted-foreground">Batas Aman: {{ Number(mat.threshold_min).toLocaleString('id-ID') }} {{ mat.satuan_dasar_penggunaan }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
