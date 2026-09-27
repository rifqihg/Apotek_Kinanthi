<script setup>
import { reactiveOmit } from "@vueuse/core";
import { AlertDialogTitle, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <AlertDialogTitle
    v-bind="forwardedProps"
    :class="cn('text-lg font-semibold text-foreground', props.class)"
  >
    <slot />
  </AlertDialogTitle>
</template>
