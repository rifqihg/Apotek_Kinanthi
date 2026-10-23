<template>
  <Card class="shadow-sm border-border bg-card flex flex-col justify-between overflow-hidden font-sans">
    <CardHeader class="pb-4 border-b border-border">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
          <CardTitle class="text-base font-semibold tracking-tight text-foreground font-sans">
            5 Obat Terlaris
          </CardTitle>
        </div>
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20 font-sans tracking-wide">
          Top Qty
        </span>
      </div>
      <CardDescription class="text-xs text-muted-foreground mt-1 font-sans">
        Peringkat obat berdasarkan kuantitas penjualan tertinggi
      </CardDescription>
    </CardHeader>

    <CardContent class="p-4 sm:p-6 flex-1 flex flex-col justify-between">
      <!-- Jika Ada Data Obat Terlaris -->
      <div
        v-if="activeItems.length > 0"
        class="relative w-full h-[270px]"
        style="transform: translateZ(0); will-change: transform;"
      >
        <svg viewBox="0 0 400 220" class="w-full h-full overflow-visible" preserveAspectRatio="none">
          <!-- Horizontal Gridlines -->
          <g v-for="(tick, i) in barYTicks" :key="'bar-grid-' + i">
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
              :x="margin.left - 8"
              :y="tick.y + 4"
              text-anchor="end"
              class="text-[10px] font-medium fill-muted-foreground"
            >
              {{ tick.label }}
            </text>
          </g>

          <!-- Bars with Curated Rank Palette & Vertical Growth Tween -->
          <g v-for="(bar, i) in computedBars" :key="'bar-' + i" class="cursor-pointer group">
            <!-- Background Column Highlight on Hover -->
            <rect
              :x="bar.colX"
              :y="margin.top"
              :width="bar.colWidth"
              :height="innerHeight"
              fill="currentColor"
              class="text-muted/0 hover:text-muted/30 transition-colors"
              @mouseenter="hoveredBar = bar"
              @mouseleave="hoveredBar = null"
            />

            <!-- Quantity Text above Bar -->
            <text
              :x="bar.x + bar.width / 2"
              :y="bar.y - 6"
              text-anchor="middle"
              class="text-[10px] font-bold transition-colors"
              :style="{ fill: bar.color.bg }"
            >
              {{ bar.qty }}
            </text>

            <!-- Main Bar Rect -->
            <rect
              :x="bar.x"
              :y="bar.y"
              :width="bar.width"
              :height="bar.height"
              rx="4"
              ry="4"
              :fill="bar.color.bg"
              class="transition-all duration-200 shadow-sm"
              :class="hoveredBar && hoveredBar.code === bar.code ? 'brightness-110' : 'group-hover:brightness-105'"
              @mouseenter="hoveredBar = bar"
              @mouseleave="hoveredBar = null"
            />

            <!-- Truncated Medicine Name on X-Axis -->
            <text
              :x="bar.x + bar.width / 2"
              :y="margin.top + innerHeight + 18"
              text-anchor="middle"
              class="text-[10px] font-medium fill-muted-foreground group-hover:fill-foreground transition-colors"
            >
              {{ truncateName(bar.name) }}
            </text>
          </g>
        </svg>

        <!-- Floating Tooltip with Rank Badge -->
        <div v-if="hoveredBar" class="absolute pointer-events-none z-30 transition-all duration-75" :style="tooltipStyle">
          <div class="bg-popover text-popover-foreground border border-border rounded-lg shadow-lg p-2.5 text-xs min-w-[150px] backdrop-blur-sm">
            <div class="flex items-center justify-between gap-2 pb-1 border-b border-border">
              <span class="font-semibold text-foreground truncate max-w-[140px]">{{ hoveredBar.name }}</span>
              <span
                class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                :style="{ backgroundColor: hoveredBar.color.bg + '20', color: hoveredBar.color.bg }"
              >
                #{{ hoveredBar.rank }}
              </span>
            </div>
            <p class="text-[10px] text-muted-foreground mt-1">Kode: {{ hoveredBar.code || '-' }}</p>
            <div class="flex items-center gap-1.5 mt-1 pt-1 border-t border-border">
              <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: hoveredBar.color.bg }"></span>
              <span class="font-semibold" :style="{ color: hoveredBar.color.bg }">{{ hoveredBar.qty }} {{ hoveredBar.unit }}</span>
              <span class="text-[10px] text-muted-foreground">terjual</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="h-[270px] flex flex-col items-center justify-center text-center p-6 border border-dashed border-border rounded-lg bg-muted/20">
        <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <p class="text-sm font-semibold text-foreground">Belum Ada Data Penjualan</p>
        <p class="text-xs text-muted-foreground mt-1 max-w-[220px]">
          Data 5 obat terlaris akan otomatis terakumulasi dari transaksi kasir pada periode ini.
        </p>
      </div>

      <!-- Footer: Rank Palette Pills & Total Unit Sold -->
      <div class="mt-4 pt-4 border-t border-border flex items-center justify-between text-xs font-medium">
        <div class="flex items-center gap-1.5">
          <span
            v-for="(c, idx) in rankPalette"
            :key="'rank-dot-' + idx"
            class="w-2 h-2 rounded-full"
            :style="{ backgroundColor: c.bg }"
            :title="'Rank ' + (idx + 1)"
          ></span>
          <span class="text-[10px] text-muted-foreground ml-1">Rank 1–5</span>
        </div>

        <div class="flex items-center gap-1 text-xs">
          <span class="text-muted-foreground uppercase tracking-wider text-[10px]">Total Qty:</span>
          <span class="font-semibold text-foreground text-sm">{{ totalQty }} Unit</span>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';

const props = defineProps({
  data: { type: Array, default: () => [] },
  topMedicines: { type: Array, default: () => [] },
});

// Curated Sequential Healthcare Palette (Rank 1 to 5)
const rankPalette = [
  { bg: '#2563EB', hover: '#1D4ED8' }, // Rank 1: Royal Blue
  { bg: '#4F46E5', hover: '#4338CA' }, // Rank 2: Deep Indigo
  { bg: '#059669', hover: '#047857' }, // Rank 3: Emerald
  { bg: '#7C3AED', hover: '#6D28D9' }, // Rank 4: Violet
  { bg: '#0284C7', hover: '#0369A1' }, // Rank 5: Sky Blue
];

// 60 FPS Vertical Growth Animation Engine
const animProgress = ref(0);
let animFrameId = null;

function runGrowthAnimation(duration = 700) {
  if (animFrameId) cancelAnimationFrame(animFrameId);
  const start = performance.now();
  animProgress.value = 0;

  function step(now) {
    const elapsed = now - start;
    const progress = Math.min(1, elapsed / duration);
    animProgress.value = 1 - Math.pow(1 - progress, 3); // easeOutCubic

    if (progress < 1) {
      animFrameId = requestAnimationFrame(step);
    } else {
      animProgress.value = 1;
    }
  }

  animFrameId = requestAnimationFrame(step);
}

onMounted(() => runGrowthAnimation(750));
onUnmounted(() => { if (animFrameId) cancelAnimationFrame(animFrameId); });

const activeItems = computed(() => (props.data?.length ? props.data : props.topMedicines) || []);
watch(activeItems, () => runGrowthAnimation(700), { deep: true });

const totalQty = computed(() => activeItems.value.reduce((acc, c) => acc + (c.qty || 0), 0));

// Layout & Grid Constants
const hoveredBar = ref(null);
const margin = { top: 25, right: 15, bottom: 35, left: 45 };
const innerWidth = 400 - margin.left - margin.right;
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

const maxQty = computed(() => getNiceMax(Math.max(...activeItems.value.map(b => b.qty || 0), 0), 20));

const barYTicks = computed(() => {
  return [0, 0.25, 0.5, 0.75, 1].map((frac) => ({
    y: margin.top + innerHeight * (1 - frac),
    label: String(Math.round(maxQty.value * frac)),
  }));
});

const computedBars = computed(() => {
  const items = activeItems.value;
  const n = items.length;
  if (n === 0) return [];
  const colWidth = innerWidth / n;
  const barWidth = Math.min(34, Math.max(22, colWidth * 0.55));
  const progress = animProgress.value;

  return items.map((item, i) => {
    const colX = margin.left + i * colWidth;
    const x = colX + (colWidth - barWidth) / 2;
    const fullHeight = Math.max(4, (item.qty / maxQty.value) * innerHeight);
    const animatedHeight = fullHeight * progress;
    const y = margin.top + innerHeight - animatedHeight;

    return {
      name: item.name,
      code: item.code,
      unit: item.unit,
      qty: item.qty,
      rank: i + 1,
      color: rankPalette[i % rankPalette.length],
      colX,
      colWidth,
      x,
      y,
      width: barWidth,
      height: animatedHeight,
    };
  });
});

function truncateName(name) {
  if (!name) return '';
  return name.length > 9 ? name.slice(0, 8) + '…' : name;
}

const tooltipStyle = computed(() => {
  if (!hoveredBar.value) return {};
  const percentX = ((hoveredBar.value.x + hoveredBar.value.width / 2) / 400) * 100;
  return {
    left: `${percentX}%`,
    top: '30px',
    transform: percentX > 60 ? 'translateX(-105%)' : 'translateX(10%)',
  };
});
</script>

<style scoped>
svg text {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>

