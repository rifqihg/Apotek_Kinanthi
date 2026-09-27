<script setup>
import { isVNode } from "vue";
import {
  Toast,
  ToastClose,
  ToastDescription,
  ToastProvider,
  ToastTitle,
  ToastViewport,
  ToastAction,
} from ".";
import { useToast } from "./use-toast";
import {
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Info,
} from "lucide-vue-next";

const { toasts, dismiss } = useToast();

const getIcon = (variant) => {
  switch (variant) {
    case "success":
      return CheckCircle2;
    case "destructive":
      return AlertCircle;
    case "warning":
      return AlertTriangle;
    case "info":
    default:
      return Info;
  }
};

const getIconBadgeClass = (variant) => {
  switch (variant) {
    case "success":
      return "text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20";
    case "destructive":
      return "text-destructive bg-destructive/10 border border-destructive/20";
    case "warning":
      return "text-amber-600 dark:text-amber-400 bg-amber-500/10 border border-amber-500/20";
    case "info":
    default:
      return "text-blue-600 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20";
  }
};
</script>

<template>
  <ToastProvider :duration="2500">
    <template v-for="t in toasts" :key="t.id">
      <Toast
        v-bind="t"
        :open="t.open"
        @update:open="(val) => { if (!val) dismiss(t.id) }"
      >
        <!-- Visual Accent Icon Badge -->
        <div
          :class="[
            'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
            getIconBadgeClass(t.variant),
          ]"
        >
          <component :is="getIcon(t.variant)" class="w-4 h-4" />
        </div>

        <!-- Content Area -->
        <div class="grid gap-0.5 flex-1 pr-4 min-w-0">
          <ToastTitle v-if="t.title">
            {{ t.title }}
          </ToastTitle>
          <template v-if="t.description">
            <ToastDescription v-if="isVNode(t.description)">
              <component :is="t.description" />
            </ToastDescription>
            <ToastDescription v-else>
              {{ t.description }}
            </ToastDescription>
          </template>
        </div>

        <template v-if="t.action">
          <ToastAction :alt-text="t.action.altText" @click="t.action.onClick">
            {{ t.action.label }}
          </ToastAction>
        </template>
        <ToastClose />
      </Toast>
    </template>
    <ToastViewport />
  </ToastProvider>
</template>
