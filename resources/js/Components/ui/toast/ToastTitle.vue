<script setup>
import { ToastTitle } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: [String, Object, Array], required: false },
});
</script>

<template>
  <ToastTitle
    :as="props.as"
    :as-child="props.asChild"
    :class="cn('text-sm font-semibold tracking-tight text-foreground leading-snug', props.class)"
  >
    <slot />
  </ToastTitle>
</template>
