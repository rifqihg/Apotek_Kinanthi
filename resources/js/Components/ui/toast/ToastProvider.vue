<script setup>
import { ToastProvider } from "reka-ui";

const props = defineProps({
  duration: { type: Number, required: false, default: 2500 },
  label: { type: String, required: false },
  swipeDirection: { type: String, required: false, default: "right" },
  swipeThreshold: { type: Number, required: false },
});
</script>

<template>
  <ToastProvider v-bind="props">
    <slot />
  </ToastProvider>
</template>
