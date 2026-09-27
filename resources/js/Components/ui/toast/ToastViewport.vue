<script setup>
import { ToastViewport } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: [String, Object, Array], required: false },
});
</script>

<template>
  <ToastViewport
    :as="props.as"
    :as-child="props.asChild"
    :class="
      cn(
        'fixed top-4 right-4 z-[100] flex max-h-screen w-full flex-col gap-2 p-0 sm:max-w-[380px] pointer-events-none',
        props.class,
      )
    "
  />
</template>
