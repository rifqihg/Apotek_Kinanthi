<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <SalesTrendChart
      class="lg:col-span-2"
      :data="salesTrend"
      :current-days="currentDays"
    />
    <TopMedicinesChart
      class="lg:col-span-1"
      :data="topMedicines"
    />
  </div>
</template>

<script setup>
import SalesTrendChart from '@/Components/SalesTrendChart.vue';
import TopMedicinesChart from '@/Components/TopMedicinesChart.vue';

defineProps({
  salesTrend: {
    type: Array,
    default: () => [],
  },
  topMedicines: {
    type: Array,
    default: () => [],
  },
  currentDays: {
    type: [Number, String],
    default: 7,
  },
});
</script>
