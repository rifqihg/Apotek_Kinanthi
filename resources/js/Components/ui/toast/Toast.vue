<script setup>
import { computed } from "vue";
import { ToastRoot, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";
import { toastVariants } from "./toast-variants";

const props = defineProps({
  class: { type: [String, Object, Array], required: false },
  variant: { type: String, required: false, default: "default" },
  defaultOpen: { type: Boolean, required: false },
  open: { type: Boolean, required: false },
  duration: { type: Number, required: false },
  type: { type: String, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
});

const emits = defineEmits([
  "close",
  "escapeKeyDown",
  "pause",
  "resume",
  "swipeStart",
  "swipeMove",
  "swipeCancel",
  "swipeEnd",
  "update:open",
]);

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <ToastRoot
    v-bind="forwarded"
    :class="cn(toastVariants({ variant: props.variant }), props.class)"
  >
    <slot />
  </ToastRoot>
</template>
