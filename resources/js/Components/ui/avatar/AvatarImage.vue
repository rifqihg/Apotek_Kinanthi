<script setup>
import { AvatarImage } from 'radix-vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  src: { type: String, required: false },
  alt: { type: String, default: '' },
  class: { type: null, required: false },
});
</script>

<template>
  <AvatarImage
    :src="src"
    :alt="alt"
    :class="cn('aspect-square h-full w-full object-cover', props.class)"
  />
</template>
