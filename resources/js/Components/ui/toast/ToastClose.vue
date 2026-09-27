<script setup>
import { ToastClose } from "reka-ui";
import { X } from "lucide-vue-next";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: [String, Object, Array], required: false },
});
</script>

<template>
  <ToastClose
    :as="props.as"
    :as-child="props.asChild"
    :class="
      cn(
        'absolute right-2.5 top-2.5 rounded-md p-1 text-muted-foreground/60 transition-opacity hover:text-foreground hover:bg-muted focus:opacity-100 focus:outline-none focus:ring-1 group-hover:opacity-100',
        props.class,
      )
    "
  >
    <X class="h-3.5 w-3.5" />
  </ToastClose>
</template>
