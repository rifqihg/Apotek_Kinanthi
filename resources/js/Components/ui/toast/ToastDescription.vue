<script setup>
import { ToastDescription } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: [String, Object, Array], required: false },
});
</script>

<template>
  <ToastDescription
    :as="props.as"
    :as-child="props.asChild"
    :class="cn('text-xs text-muted-foreground leading-relaxed mt-0.5', props.class)"
  >
    <slot />
  </ToastDescription>
</template>
