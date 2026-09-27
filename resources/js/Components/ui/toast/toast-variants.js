import { cva } from "class-variance-authority";

export const toastVariants = cva(
  "group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-xl border p-4 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--reka-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--reka-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full sm:data-[state=open]:slide-in-from-right-full bg-card",
  {
    variants: {
      variant: {
        default: "border-border text-foreground",
        success: "border-emerald-500/30 text-foreground shadow-emerald-500/10",
        destructive: "border-destructive/30 text-foreground shadow-destructive/10",
        warning: "border-amber-500/30 text-foreground shadow-amber-500/10",
        info: "border-blue-500/30 text-foreground shadow-blue-500/10",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);
