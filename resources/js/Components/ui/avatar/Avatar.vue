<script setup>
import { AvatarRoot } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <AvatarRoot
    :class="cn(
      'relative flex h-8 w-8 shrink-0 overflow-hidden rounded-full',
      props.class
    )"
  >
    <slot />
  </AvatarRoot>
</template>
