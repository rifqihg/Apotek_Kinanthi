<template>
  <Card class="shadow-sm border-border bg-card overflow-hidden">
    <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
      <div>
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-primary animate-pulse"></span>
          <CardTitle class="text-base font-semibold tracking-tight text-foreground flex items-center gap-2">
            <span>Tren Penjualan & Transaksi</span>
            <span class="text-[11px] font-normal px-2 py-0.5 rounded-full bg-blue-500/10 text-primary border border-blue-500/20">
              Live Data
            </span>
          </CardTitle>
        </div>
        <CardDescription class="text-xs text-muted-foreground mt-1">
          {{ isHourly ? 'Dinamika omset per menit jam operasional 08:00 - 22:00 (Sumbu Kiri) dan frekuensi transaksi (Sumbu Kanan)' : 'Dinamika omset harian (Sumbu Kiri) dan frekuensi transaksi (Sumbu Kanan)' }}
        </CardDescription>
      </div>

      <!-- Filter Dropdown Shadcn Select -->
      <div class="flex items-center gap-2 shrink-0">
        <Select :model-value="String(selectedDays)" @update:model-value="handlePeriodChange">
          <SelectTrigger class="w-[190px] h-8 text-xs font-medium border-border bg-background transition-colors hover:bg-muted/50">
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
      <!-- SVG Line Chart Container with Hardware Acceleration -->
      <div
        class="relative w-full h-[270px] select-none"
        ref="containerRef"
        style="transform: translateZ(0); will-change: transform;"
        @mousemove="onMouseMove"
        @mouseleave="hoveredPoint = null"
      >
        <svg viewBox="0 0 800 220" class="w-full h-full overflow-visible" preserveAspectRatio="none">
          <defs>
            <linearGradient id="salesTrendGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#2563EB" stop-opacity="0.22" />
              <stop offset="60%" stop-color="#2563EB" stop-opacity="0.06" />
              <stop offset="100%" stop-color="#2563EB" stop-opacity="0.00" />
            </linearGradient>

            <clipPath id="salesTrendSweepClip">
              <rect
                :x="margin.left - 5"
                y="0"
                :width="Math.max(0, (innerWidth + 10) * animProgress)"
                height="220"
              />
            </clipPath>
          </defs>

          <!-- Horizontal Gridlines & Dual Y-Axis Labels -->
          <g v-for="(tick, i) in yTicks" :key="'grid-' + i">
            <line
              :x1="margin.left"
              :y1="tick.y"
              :x2="margin.left + innerWidth"
              :y2="tick.y"
              stroke="currentColor"
              stroke-dasharray="3 3"
              class="text-border/70"
              stroke-width="1"
            />
            <text
              :x="margin.left - 10"
              :y="tick.y + 4"
              text-anchor="end"
              class="text-[10px] font-medium fill-muted-foreground"
            >
              {{ tick.salesLabel }}
            </text>
            <text
              :x="margin.left + innerWidth + 10"
              :y="tick.y + 4"
              text-anchor="start"
              class="text-[10px] font-medium fill-muted-foreground"
            >
              {{ tick.txLabel }}
            </text>
          </g>

          <!-- Animated Left-to-Right Draw Layer -->
          <g clip-path="url(#salesTrendSweepClip)">
            <path v-if="salesAreaPath" :d="salesAreaPath" fill="url(#salesTrendGradient)" />
            <path
              v-if="salesLinePath"
              :d="salesLinePath"
              fill="none"
              stroke="#2563EB"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <path
              v-if="txLinePath"
              :d="txLinePath"
              fill="none"
              stroke="#10B981"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </g>

          <!-- Hover Crosshair & Dots -->
          <g v-if="hoveredPoint">
            <line
              :x1="hoveredPoint.x"
              :y1="margin.top"
              :x2="hoveredPoint.x"
              :y2="margin.top + innerHeight"
              stroke="currentColor"
              stroke-dasharray="2 2"
              stroke-width="1.5"
              class="text-slate-400 dark:text-slate-500"
            />
            <circle
              :cx="hoveredPoint.x"
              :cy="hoveredPoint.ySales"
              r="6"
              fill="#2563EB"
              stroke="#FFFFFF"
              stroke-width="2.5"
              class="drop-shadow-sm animate-pulse"
            />
            <circle
              :cx="hoveredPoint.x"
              :cy="hoveredPoint.yTx"
              r="5"
              fill="#10B981"
              stroke="#FFFFFF"
              stroke-width="2.5"
              class="drop-shadow-sm"
            />
          </g>

          <!-- X-Axis Labels (Hours for 1D or Dates for Others) -->
          <g v-for="(item, idx) in sampledXAxis" :key="'xaxis-' + idx">
            <text
              :x="item.x"
              :y="margin.top + innerHeight + 20"
              text-anchor="middle"
              class="text-[10px] font-medium fill-muted-foreground"
            >
              {{ item.label }}
            </text>
          </g>
        </svg>

        <!-- Floating Tooltip -->
        <div v-if="hoveredPoint" class="absolute pointer-events-none z-30 transition-all duration-75" :style="tooltipStyle">
          <div class="bg-popover text-popover-foreground border border-border rounded-lg shadow-lg p-3 min-w-[185px] backdrop-blur-sm">
            <p class="text-[11px] font-semibold text-foreground border-b border-border pb-1.5 mb-2 flex items-center justify-between">
              <span>{{ isHourly ? 'Pukul ' + hoveredPoint.data.label : hoveredPoint.data.label }}</span>
              <span class="text-[10px] font-normal text-muted-foreground">{{ isHourly ? 'Hari Ini' : hoveredPoint.data.date }}</span>
            </p>
            <div class="space-y-2 text-xs">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#2563EB]"></span>
                  <span class="text-muted-foreground text-[11px]">Penjualan:</span>
                </div>
                <span class="font-semibold text-foreground">{{ formatRupiah(hoveredPoint.data.sales) }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#10B981]"></span>
                  <span class="text-muted-foreground text-[11px]">Transaksi:</span>
                </div>
                <span class="font-semibold text-foreground">{{ hoveredPoint.data.transactions }} trx</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Legends & Summary -->
      <div class="flex flex-wrap items-center justify-between gap-4 mt-6 pt-4 border-t border-border">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded bg-[#2563EB] shadow-xs"></span>
            <span class="text-xs font-medium text-foreground">Penjualan (Rp)</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded bg-[#10B981] shadow-xs"></span>
            <span class="text-xs font-medium text-foreground">Transaksi</span>
          </div>
        </div>

        <div class="flex items-center gap-4 text-xs font-medium text-muted-foreground">
          <div>
            Total Omset:
            <strong class="text-foreground ml-1 font-semibold">{{ formatRupiah(totalSales) }}</strong>
          </div>
          <div class="w-px h-3 bg-border"></div>
          <div>
            Total Volume:
            <strong class="text-foreground ml-1 font-semibold">{{ totalTransactions }} Trx</strong>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/ui/select';

const props = defineProps({
  data: { type: Array, default: () => [] },
  salesTrend: { type: Array, default: () => [] },
  currentDays: { type: [Number, String], default: '7' },
});

const periodOptions = [
  { value: '1D', label: '1 Hari Berjalan' },
  { value: '7', label: '7 Hari Terakhir' },
  { value: '30', label: '30 Hari Terakhir' },
  { value: '90', label: '90 Hari Terakhir' },
  { value: 'YTD', label: 'Tahun Berjalan' },
  { value: '365', label: '1 Tahun Terakhir' },
];

const selectedDays = ref(String(props.currentDays || '7'));
watch(() => props.currentDays, (v) => { if (v) selectedDays.value = String(v); });

const isHourly = computed(() => String(selectedDays.value) === '1D');

function handlePeriodChange(val) {
  selectedDays.value = String(val);
  router.reload({
    data: { days: val },
    preserveState: true,
    preserveScroll: true,
    only: ['salesTrend', 'topMedicines', 'currentDays'],
  });
}

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

const activeData = computed(() => {
  const source = props.data?.length ? props.data : props.salesTrend;
  if (Array.isArray(source) && source.length > 0) return source;
  if (isHourly.value) {
    const start = new Date();
    start.setHours(8, 0, 0, 0);
    return Array.from({ length: 841 }, (_, i) => {
      const d = new Date(start.getTime() + i * 60000);
      const h = String(d.getHours()).padStart(2, '0');
      const m = String(d.getMinutes()).padStart(2, '0');
      const label = `${h}:${m}`;
      return {
        date: label,
        label,
        sales: 0,
        transactions: 0,
      };
    });
  }
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - (6 - i));
    return {
      date: d.toISOString().split('T')[0],
      label: d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }),
      sales: 0,
      transactions: 0,
    };
  });
});

watch(activeData, () => runDrawAnimation(750), { deep: true });

// Chart Dimensions & Grid Calculations
const containerRef = ref(null);
const hoveredPoint = ref(null);
const margin = { top: 20, right: 45, bottom: 30, left: 65 };
const innerWidth = 800 - margin.left - margin.right;
const innerHeight = 220 - margin.top - margin.bottom;

function getNiceMax(val, defaultMin) {
  if (val <= 0) return defaultMin;
  const exp = Math.pow(10, Math.floor(Math.log10(val)));
  const frac = val / exp;
  let niceFrac = 10;
  if (frac <= 1.5) niceFrac = 2;
  else if (frac <= 3) niceFrac = 3;
  else if (frac <= 5) niceFrac = 5;
  return niceFrac * exp;
}

const totalSales = computed(() => activeData.value.reduce((acc, c) => acc + (c.sales || 0), 0));
const totalTransactions = computed(() => activeData.value.reduce((acc, c) => acc + (c.transactions || 0), 0));
const maxSales = computed(() => getNiceMax(Math.max(...activeData.value.map(d => d.sales || 0), 0), 1000000));
const maxTx = computed(() => getNiceMax(Math.max(...activeData.value.map(d => d.transactions || 0), 0), 10));

const yTicks = computed(() => {
  return [0, 0.25, 0.5, 0.75, 1].map((frac) => ({
    y: margin.top + innerHeight * (1 - frac),
    salesLabel: formatShortCurrency(maxSales.value * frac),
    txLabel: String(Math.round(maxTx.value * frac)),
  }));
});

const chartPoints = computed(() => {
  const data = activeData.value;
  const n = data.length;
  const denomSales = maxSales.value || 1000000;
  const denomTx = maxTx.value || 10;

  return data.map((item, i) => ({
    x: margin.left + (n > 1 ? (i / (n - 1)) * innerWidth : innerWidth / 2),
    ySales: margin.top + innerHeight * (1 - Math.min(1, Math.max(0, (Number(item.sales) || 0) / denomSales))),
    yTx: margin.top + innerHeight * (1 - Math.min(1, Math.max(0, (Number(item.transactions) || 0) / denomTx))),
    data: item,
  }));
});

// Fritsch-Carlson Monotone Spline Generator
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

// Line Generator with high-density intraday support (Stockbit style)
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

const salesLinePath = computed(() => generateLinePath(chartPoints.value, 'ySales'));
const txLinePath = computed(() => generateLinePath(chartPoints.value, 'yTx'));

const salesAreaPath = computed(() => {
  const line = salesLinePath.value;
  const pts = chartPoints.value;
  if (!line || pts.length === 0) return '';
  const baseLine = margin.top + innerHeight;
  return `${line} L ${pts[pts.length - 1].x.toFixed(1)} ${baseLine.toFixed(1)} L ${pts[0].x.toFixed(1)} ${baseLine.toFixed(1)} Z`;
});

// Sumbu X: 08:00 - 22:00 untuk 1D, atau tanggal untuk harian
const sampledXAxis = computed(() => {
  const pts = chartPoints.value;
  if (!pts || pts.length === 0) return [];
  if (isHourly.value) {
    // Tampilkan penanda jam operasional apotek genap (08:00, 10:00, ..., 22:00)
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
  if (pts.length <= 8) return pts.map(p => ({ x: p.x, label: p.data.label }));
  const step = Math.ceil(pts.length / 7);
  return pts.filter((_, i) => i % step === 0 || i === pts.length - 1).map(p => ({ x: p.x, label: p.data.label }));
});

function onMouseMove(event) {
  if (!containerRef.value || chartPoints.value.length === 0) return;
  const rect = containerRef.value.getBoundingClientRect();
  const svgX = (event.clientX - rect.left) * (800 / rect.width);
  const n = chartPoints.value.length;
  if (isHourly.value && n > 1) {
    const clampedX = Math.max(margin.left, Math.min(margin.left + innerWidth, svgX));
    const ratio = (clampedX - margin.left) / innerWidth;
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
  const percentX = (hoveredPoint.value.x / 800) * 100;
  return {
    left: `${percentX}%`,
    top: '20px',
    transform: percentX > 65 ? 'translateX(-105%)' : 'translateX(10%)',
  };
});

function formatRupiah(num) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
}

function formatShortCurrency(num) {
  if (num >= 1000000) return `${(num / 1000000).toFixed(num % 1000000 === 0 ? 0 : 1)} Jt`;
  if (num >= 1000) return `${(num / 1000).toFixed(0)} Rb`;
  return String(num || 0);
}
</script>
