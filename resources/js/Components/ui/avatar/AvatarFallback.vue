<script setup>
import { AvatarFallback } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
  delayMs: { type: Number, default: 0 },
});
</script>

<template>
  <AvatarFallback
    :delay-ms="delayMs"
    :class="cn(
      'flex h-full w-full items-center justify-center rounded-full bg-slate-700/60 text-slate-200 font-semibold text-xs select-none',
      props.class
    )"
  >
    <slot />
  </AvatarFallback>
</template>
