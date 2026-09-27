import { ref } from "vue";

const TOAST_LIMIT = 5;
const DEFAULT_DURATION = 2500; // 2.5 detik
const TOAST_REMOVE_DELAY = 400; // 400ms untuk transisi fade-out / slide-out

export const toasts = ref([]);
const toastTimers = new Map();

let count = 0;
function genId() {
  count = (count + 1) % Number.MAX_VALUE;
  return count.toString();
}

function dismissToast(toastId) {
  if (toastTimers.has(toastId)) {
    clearTimeout(toastTimers.get(toastId));
    toastTimers.delete(toastId);
  }

  const index = toasts.value.findIndex((t) => t.id === toastId);
  if (index !== -1) {
    toasts.value[index].open = false;
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== toastId);
    }, TOAST_REMOVE_DELAY);
  }
}

export function useToast() {
  function toast(props) {
    const id = genId();
    const duration = props.duration ?? DEFAULT_DURATION;

    const dismiss = () => dismissToast(id);

    const update = (newProps) => {
      const index = toasts.value.findIndex((t) => t.id === id);
      if (index !== -1) {
        toasts.value[index] = { ...toasts.value[index], ...newProps };
      }
    };

    // Auto-dismiss otomatis menggunakan setTimeout
    if (duration > 0 && duration !== Infinity) {
      const timer = setTimeout(() => {
        dismiss();
      }, duration);
      toastTimers.set(id, timer);
    }

    const newToast = {
      ...props,
      id,
      open: true,
      duration,
      "onUpdate:open": (val) => {
        if (!val) dismiss();
      },
    };

    toasts.value = [newToast, ...toasts.value].slice(0, TOAST_LIMIT);

    return {
      id,
      dismiss,
      update,
    };
  }

  const toastMethods = Object.assign(toast, {
    success(title, options = {}) {
      return toast({
        title,
        description: options.description || options,
        variant: "success",
        duration: options.duration ?? 2500,
        ...options,
      });
    },
    error(title, options = {}) {
      return toast({
        title,
        description: options.description || options,
        variant: "destructive",
        duration: options.duration ?? 3000,
        ...options,
      });
    },
    warning(title, options = {}) {
      return toast({
        title,
        description: options.description || options,
        variant: "warning",
        duration: options.duration ?? 2500,
        ...options,
      });
    },
    info(title, options = {}) {
      return toast({
        title,
        description: options.description || options,
        variant: "info",
        duration: options.duration ?? 2500,
        ...options,
      });
    },
    dismiss(toastId) {
      if (toastId) {
        dismissToast(toastId);
      } else {
        toasts.value.forEach((t) => dismissToast(t.id));
      }
    },
  });

  return {
    toasts,
    toast: toastMethods,
    dismiss: toastMethods.dismiss,
  };
}

export const toast = useToast().toast;
