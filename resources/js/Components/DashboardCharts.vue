<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <!-- Grafik Penjualan (Line Chart - Custom SVG) -->
    <div class="lg:col-span-2 bg-white p-6 rounded-[1.5rem] border border-slate-200/80 shadow-sm relative">
      <div class="flex items-center justify-between mb-8 relative z-10">
        <h3 class="font-black text-slate-900 tracking-tight text-sm uppercase">Grafik Penjualan</h3>
        <select class="text-[11px] font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none cursor-pointer hover:bg-slate-100 transition-colors shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
          <option>7 Hari Terakhir</option>
          <option>30 Hari Terakhir</option>
          <option>90 Hari Terakhir</option>
          <option>1 Tahun Terakhir</option>
        </select>
      </div>

      <!-- Custom SVG Line Chart -->
      <div class="relative w-full h-72">
        <!-- Mock SVG -->
        <svg viewBox="0 0 800 200" class="w-full h-full overflow-visible" preserveAspectRatio="none">
          <!-- Grid lines -->
          <line x1="0" y1="200" x2="800" y2="200" stroke="#f1f5f9" stroke-width="2" />
          <line x1="0" y1="150" x2="800" y2="150" stroke="#f1f5f9" stroke-width="2" />
          <line x1="0" y1="100" x2="800" y2="100" stroke="#f1f5f9" stroke-width="2" />
          <line x1="0" y1="50" x2="800" y2="50" stroke="#f1f5f9" stroke-width="2" />
          <line x1="0" y1="0" x2="800" y2="0" stroke="#f1f5f9" stroke-width="2" />
          
          <!-- Y Axis labels (Left) -->
          <text x="-10" y="5" font-size="12" fill="#94a3b8" text-anchor="end" font-weight="bold">10 Jt</text>
          <text x="-10" y="55" font-size="12" fill="#94a3b8" text-anchor="end" font-weight="bold">8 Jt</text>
          <text x="-10" y="105" font-size="12" fill="#94a3b8" text-anchor="end" font-weight="bold">6 Jt</text>
          <text x="-10" y="155" font-size="12" fill="#94a3b8" text-anchor="end" font-weight="bold">4 Jt</text>
          <text x="-10" y="205" font-size="12" fill="#94a3b8" text-anchor="end" font-weight="bold">0</text>

          <!-- Y Axis labels (Right) -->
          <text x="810" y="5" font-size="12" fill="#94a3b8" text-anchor="start" font-weight="bold">100</text>
          <text x="810" y="55" font-size="12" fill="#94a3b8" text-anchor="start" font-weight="bold">80</text>
          <text x="810" y="105" font-size="12" fill="#94a3b8" text-anchor="start" font-weight="bold">60</text>
          <text x="810" y="155" font-size="12" fill="#94a3b8" text-anchor="start" font-weight="bold">40</text>
          <text x="810" y="205" font-size="12" fill="#94a3b8" text-anchor="start" font-weight="bold">0</text>
          
          <!-- Blue Fill under line -->
          <path d="M 0 150 C 80 130, 120 160, 200 140 S 300 120, 380 60 S 480 80, 550 50 S 650 130, 800 90 L 800 200 L 0 200 Z" fill="rgba(59, 130, 246, 0.05)" />
          
          <!-- Blue Line (Penjualan) -->
          <path d="M 0 150 C 80 130, 120 160, 200 140 S 300 120, 380 60 S 480 80, 550 50 S 650 130, 800 90" fill="none" stroke="#3b82f6" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
          
          <!-- Green Line (Transaksi) -->
          <path d="M 0 180 C 80 160, 120 170, 200 150 S 300 130, 380 100 S 480 110, 550 80 S 650 150, 800 120" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
          
          <!-- Data Points -->
          <circle cx="550" cy="50" r="6" fill="#3b82f6" stroke="#fff" stroke-width="3" class="shadow-md" />
          <circle cx="550" cy="80" r="6" fill="#10b981" stroke="#fff" stroke-width="3" class="shadow-md" />
        </svg>

        <!-- X Axis labels -->
        <div class="absolute bottom-[-30px] left-0 w-full flex justify-between text-[11px] font-bold text-slate-400">
          <span>13 Apr</span>
          <span>18 Apr</span>
          <span>23 Apr</span>
          <span>28 Apr</span>
          <span>3 Mei</span>
          <span>8 Mei</span>
          <span>12 Mei</span>
        </div>

        <!-- Tooltip Mock -->
        <div class="absolute top-[10px] left-[450px] bg-white p-3 rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-slate-100 z-20">
          <span class="block text-[10px] font-black text-slate-700 mb-2">6 Mei 2026</span>
          <div class="flex items-center gap-2 mb-1.5">
            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
            <span class="text-[10px] font-bold text-slate-500">Penjualan: <strong class="text-slate-800">Rp 6.850.000</strong></span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
            <span class="text-[10px] font-bold text-slate-500">Transaksi: <strong class="text-slate-800">68</strong></span>
          </div>
        </div>
      </div>
      
      <!-- Legends -->
      <div class="flex items-center gap-3 mt-12 mb-2 pl-4">
        <div class="flex items-center gap-2 bg-blue-50/50 border border-blue-100 px-3 py-1.5 rounded-[12px]">
          <div class="w-3 h-3 bg-blue-500 rounded-md shadow-sm"></div>
          <span class="text-[11px] font-bold text-blue-700 tracking-wide">Penjualan (Rp)</span>
        </div>
        <div class="flex items-center gap-2 bg-emerald-50/50 border border-emerald-100 px-3 py-1.5 rounded-[12px]">
          <div class="w-3 h-3 bg-emerald-500 rounded-md shadow-sm"></div>
          <span class="text-[11px] font-bold text-emerald-700 tracking-wide">Transaksi</span>
        </div>
      </div>
    </div>

    <!-- Penjualan 7 Hari Terakhir (Bar Chart) -->
    <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200/80 shadow-sm relative">
      <div class="flex items-center justify-between mb-8 relative z-10">
        <h3 class="font-black text-slate-900 tracking-tight text-sm uppercase">Penjualan 7 Hari Terakhir</h3>
        <select class="text-[11px] font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none cursor-pointer hover:bg-slate-100 transition-colors shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
          <option>7 Hari</option>
        </select>
      </div>

      <div class="relative w-full h-72 pt-6">
        <!-- Grid lines for bars -->
        <div class="absolute top-0 left-0 w-full h-full flex flex-col justify-between pb-8">
          <div class="w-full border-t border-slate-100"></div>
          <div class="w-full border-t border-slate-100"></div>
          <div class="w-full border-t border-slate-100"></div>
          <div class="w-full border-t border-slate-100"></div>
          <div class="w-full border-t border-slate-100"></div>
        </div>
        
        <!-- Y Axis Text -->
        <div class="absolute top-[-10px] left-0 h-full flex flex-col justify-between pb-8 text-[10px] font-bold text-slate-400">
          <span>10 Jt</span>
          <span>8 Jt</span>
          <span>6 Jt</span>
          <span>4 Jt</span>
          <span>2 Jt</span>
          <span>0</span>
        </div>

        <!-- Bars Container -->
        <div class="flex items-end justify-around w-full h-full pb-8 pl-8 relative z-10">
          <div class="flex flex-col items-center gap-2 w-full group cursor-pointer" v-for="bar in barData" :key="bar.day">
            <span class="text-[10px] font-black text-slate-600 opacity-0 group-hover:opacity-100 group-hover:-translate-y-1 transition-all">{{ bar.value }}</span>
            <div class="w-full max-w-[28px] bg-blue-500 rounded-t-lg group-hover:bg-blue-400 transition-colors shadow-sm" :style="{ height: bar.height + '%' }"></div>
            <span class="absolute bottom-1 text-[11px] font-bold text-slate-400">{{ bar.day }}</span>
          </div>
        </div>
      </div>
      
      <!-- Total -->
      <div class="mt-4 pt-4 border-t border-slate-100 text-center flex items-center justify-center gap-2">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Total 7 Hari: </span>
        <span class="text-sm font-black text-blue-600 tracking-tighter">Rp 39.900.000</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const barData = [
  { day: '6 Mei', value: '4.2 Jt', height: 40 },
  { day: '7 Mei', value: '5.1 Jt', height: 50 },
  { day: '8 Mei', value: '6.3 Jt', height: 60 },
  { day: '9 Mei', value: '5.8 Jt', height: 55 },
  { day: '10 Mei', value: '7.2 Jt', height: 70 },
  { day: '11 Mei', value: '6.1 Jt', height: 60 },
  { day: '12 Mei', value: '5.2 Jt', height: 50 },
];
</script>
